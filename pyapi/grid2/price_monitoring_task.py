import asyncio
from decimal import Decimal
import json
import logging
import uuid
import time
from datetime import datetime
from common_functions import (
    get_account_balance,
    get_grid_percent_list,
    get_market_precision,
    cancel_all_orders,
    get_client_order_id,
    get_exchange,
    get_total_positions,
    get_market_price,
    get_max_position_value,
    open_position,
    milliseconds_to_local_datetime,
    fetch_order_with_retry,
    fetch_positions_with_retry,
)
from database import Database
from trading_bot_config import TradingBotConfig
from stop_loss_task import StopLossTask
from savings_task import SavingsTask
import traceback


class PriorityAccountQueue:
    """账户优先级队列管理器

    根据账户是否有未成交订单，动态分配检查优先级：
    - 高优先级：有未成交订单的账户，每轮都检查
    - 低优先级：无订单的账户，降低检查频率
    """

    def __init__(self):
        self.high_priority = []  # 有未成交订单的账户
        self.low_priority = []  # 无订单的账户
        self.last_update_time = 0  # 上次更新优先级的时间
        self.account_check_count = {}  # 记录每个账户的检查次数

    async def update_priorities(self, db: Database, all_account_ids: list):
        """更新账户优先级分类（并发优化版本）

        Args:
            db: 数据库实例
            all_account_ids: 所有账户ID列表
        """
        high = []
        low = []

        # 并发检查所有账户的订单状态
        async def check_account_orders(account_id):
            try:
                orders = await db.get_active_orders(account_id)
                return account_id, (orders and len(orders) > 0)
            except Exception as e:
                logging.error(f"❌ 更新账户 {account_id} 优先级失败: {e}")
                return account_id, False

        # 并发执行所有账户检查
        results = await asyncio.gather(
            *[check_account_orders(aid) for aid in all_account_ids],
            return_exceptions=True,
        )

        # 分类结果
        for result in results:
            if isinstance(result, Exception):
                continue
            account_id, has_orders = result
            if has_orders:
                high.append(account_id)
            else:
                low.append(account_id)

        self.high_priority = high
        self.low_priority = low
        self.last_update_time = time.time()

        logging.info(
            f"📊 优先级队列已更新: "
            f"高优先级(有订单)={len(high)}个 {high[:10]}{'...' if len(high) > 10 else ''}, "
            f"低优先级(无订单)={len(low)}个"
        )

    def get_accounts_to_check(
        self, round_counter: int, low_priority_interval: int = 5
    ) -> list:
        """获取本轮需要检查的账户列表

        Args:
            round_counter: 当前轮次计数
            low_priority_interval: 低优先级账户检查间隔（每N轮检查一次）

        Returns:
            需要检查的账户ID列表
        """
        accounts_to_check = []

        # 高优先级账户：每轮都检查
        accounts_to_check.extend(self.high_priority)

        # 低优先级账户：每N轮检查一次
        if round_counter % low_priority_interval == 0:
            accounts_to_check.extend(self.low_priority)
            logging.info(f"🔄 本轮包含低优先级账户检查 (轮次: {round_counter})")

        return accounts_to_check

    def get_stats(self) -> dict:
        """获取优先级队列统计信息"""
        return {
            "high_priority_count": len(self.high_priority),
            "low_priority_count": len(self.low_priority),
            "total_count": len(self.high_priority) + len(self.low_priority),
            "last_update_time": self.last_update_time,
        }


class PriceMonitoringTask:
    def __init__(
        self,
        config: TradingBotConfig,
        db: Database,
        signal_lock: asyncio.Lock,
        stop_loss_task: StopLossTask,
        busy_accounts: set[int],
        api_limiter=None,
    ):
        self.config = config
        self.db = db
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task  # 保留引用
        self.running = True  # 控制运行状态
        self.busy_accounts = busy_accounts  # 引用交易机器人中的忙碌账户集合
        self.api_limiter = api_limiter  # 全局API限流器
        # ✅ 账户并发限制（动态设置，确保所有账户都能被检测）
        self.account_semaphore = asyncio.Semaphore(15)  # 限制 15 个账户并发（略大于账户数）
        self.order_semaphore = asyncio.Semaphore(10)  # 订单查询并发限流
        self.market_precision_cache = {}  # 市场精度缓存

        # ⏱️ 超时配置
        self.account_check_timeout = 30.0  # 单个账户检查超时时间（秒）
        self.round_total_timeout = 90.0  # 整轮检查总超时时间（秒）

        # 🎯 优先级队列（方案3）
        self.priority_queue = PriorityAccountQueue()
        self.round_counter = 0  # 轮次计数器
        self.priority_update_interval = 3  # 每3轮更新一次优先级（20账户优化）
        self.low_priority_check_interval = 2  # 低优先级账户每2轮检查一次（20账户优化）
        self._skip_count = 0  # 连续跳过计数器（用于优化日志）

        # 📊 统计信息
        self.stats = {
            "total_checks": 0,
            "timeout_accounts": 0,
            "error_accounts": 0,
            "success_accounts": 0,
        }

    async def price_monitoring_task(self):
        """价格监控主任务（优先级队列版本 - 方案3 + 超时控制优化）

        核心优化：
        1. 优先级队列：根据账户是否有订单动态调整检查频率
           - 有订单的账户：每轮都检查（实时监控）
           - 无订单的账户：每8轮检查一次（降低频率）

        2. 多层超时控制（支持100+账户场景）：
           - 单个账户检查超时：30秒
           - 整轮检查总超时：90秒
           - API调用超时：5-15秒
           - 优先级更新超时：30秒

        3. 并发控制：
           - 账户并发数：50（信号量控制）
           - 订单查询并发：3个
           - 使用 asyncio.wait 替代 gather，支持整体超时

        4. 容错机制：
           - 超时账户自动跳过，不影响其他账户
           - 异常自动捕获和记录
           - 统计信息追踪（成功/超时/异常）
        """
        while getattr(self, "running", True):
            try:
                if self.signal_lock.locked():
                    print("⏸ 信号处理中，跳过一次监控")
                    logging.info("⏸ 信号处理中，跳过一次监控")
                    await asyncio.sleep(1)
                    continue

                # 获取所有账户ID
                all_account_ids = list(self.db.account_cache.keys())
                if not all_account_ids:
                    await asyncio.sleep(self.config.check_interval)
                    continue

                # 🎯 定期更新优先级队列（每N轮更新一次）
                if self.round_counter % self.priority_update_interval == 0:
                    try:
                        logging.info(
                            f"🔄 第 {self.round_counter} 轮，更新优先级队列..."
                        )
                        # 为优先级更新设置超时（30秒，适应100个账户）
                        await asyncio.wait_for(
                            self.priority_queue.update_priorities(
                                self.db, all_account_ids
                            ),
                            timeout=30.0,
                        )
                        stats = self.priority_queue.get_stats()
                        logging.info(
                            f"📊 当前统计: 总账户={stats['total_count']}, "
                            f"高优先级={stats['high_priority_count']}, "
                            f"低优先级={stats['low_priority_count']}"
                        )
                    except asyncio.TimeoutError:
                        logging.error(f"⏱️ 优先级队列更新超时(30秒)，使用旧优先级继续")
                    except Exception as e:
                        logging.error(f"❌ 优先级队列更新失败: {e}")

                # 🎯 获取本轮需要检查的账户
                accounts_to_check = self.priority_queue.get_accounts_to_check(
                    self.round_counter, self.low_priority_check_interval
                )

                if not accounts_to_check:
                    # 动态调整：无账户检查时，缩短睡眠时间快速进入下一轮
                    self._skip_count += 1
                    rounds_until_next_check = self.low_priority_check_interval - (
                        self.round_counter % self.low_priority_check_interval
                    )
                    sleep_time = 1.0  # 空转时只睡眠1秒，而不是完整的check_interval

                    # 只在连续跳过多次时记录日志，减少噪音
                    if self._skip_count % 5 == 1:  # 每5次记录一次
                        logging.info(
                            f"📭 无需检查的账户，{rounds_until_next_check}轮后检查 "
                            f"(已连续跳过 {self._skip_count} 次)"
                        )

                    self.round_counter += 1
                    await asyncio.sleep(sleep_time)
                    continue

                # 重置跳过计数器（有账户需要检查时）
                if self._skip_count > 0:
                    logging.info(f"✅ 恢复检查，共跳过了 {self._skip_count} 轮")
                    self._skip_count = 0

                # 记录本轮检查信息
                logging.info(
                    f"🔍 [轮次 {self.round_counter}] 本轮检查 {len(accounts_to_check)} 个账户 "
                    f"(高优先级: {len(self.priority_queue.high_priority)}, "
                    f"包含低优先级: {self.round_counter % self.low_priority_check_interval == 0})"
                )

                # ✅ 并发检查账户（使用信号量限制并发数 + 超时控制）
                async def limited_check_positions(account_id):
                    async with self.account_semaphore:
                        try:
                            # 为每个账户设置超时
                            await asyncio.wait_for(
                                self._safe_check_positions(account_id),
                                timeout=self.account_check_timeout,
                            )
                            self.stats["success_accounts"] += 1
                        except asyncio.TimeoutError:
                            self.stats["timeout_accounts"] += 1
                            logging.warning(
                                f"⏱️ 账户 {account_id} 检查超时({self.account_check_timeout}秒)，已跳过"
                            )
                        except Exception as e:
                            self.stats["error_accounts"] += 1
                            logging.error(
                                f"❌ 账户 {account_id} 检查异常: {e}",
                                exc_info=True,
                            )
                        finally:
                            self.stats["total_checks"] += 1

                tasks = [
                    asyncio.create_task(limited_check_positions(account_id))
                    for account_id in accounts_to_check
                ]

                start_time = time.time()

                # 使用 wait 替代 gather，支持整体超时控制
                try:
                    done, pending = await asyncio.wait(
                        tasks,
                        timeout=self.round_total_timeout,
                        return_when=asyncio.ALL_COMPLETED,
                    )

                    # 如果有未完成的任务，取消它们
                    if pending:
                        logging.warning(
                            f"⚠️ 本轮有 {len(pending)} 个账户检查未完成，已取消"
                        )
                        for task in pending:
                            task.cancel()
                        # 等待取消完成
                        await asyncio.gather(*pending, return_exceptions=True)

                    # 统计成功和失败的任务
                    success_count = sum(1 for t in done if not t.exception())
                    error_count = len(done) - success_count

                    elapsed = time.time() - start_time
                    logging.info(
                        f"✅ [轮次 {self.round_counter}] 监控完成，"
                        f"检查 {len(accounts_to_check)} 个账户: "
                        f"成功={success_count}, 异常={error_count}, "
                        f"超时未完成={len(pending)}, 耗时 {elapsed:.2f}秒"
                    )

                except Exception as e:
                    elapsed = time.time() - start_time
                    logging.error(
                        f"❌ [轮次 {self.round_counter}] 监控异常: {e}, 耗时 {elapsed:.2f}秒"
                    )

                # 增加轮次计数
                self.round_counter += 1

                # 📊 每20轮输出一次统计信息
                if self.round_counter % 20 == 0:
                    logging.info(
                        f"📊 [统计] 累计检查: {self.stats['total_checks']} 次, "
                        f"成功: {self.stats['success_accounts']}, "
                        f"超时: {self.stats['timeout_accounts']}, "
                        f"异常: {self.stats['error_accounts']}"
                    )

                await asyncio.sleep(self.config.check_interval)

            except Exception as e:
                print(f"❌ 价格监控主循环异常: {e}")
                logging.error(f"❌ 价格监控主循环异常: {e}")
                traceback.print_exc()
                await asyncio.sleep(5)

    async def _handle_no_position_order(
        self, order: dict, order_info: dict, account_id: int, symbol: str, exchange
    ) -> bool:
        """
        处理无持仓的订单情况

        Args:
            order: 数据库中的订单记录
            order_info: 交易所返回的订单详情
            account_id: 账户ID
            symbol: 交易对
            exchange: 交易所实例

        Returns:
            bool: True表示应该跳过该订单，False表示继续处理
        """
        state = order_info["info"]["state"]

        # 🔑 获取订单创建时间进行判断
        order_timestamp = order.get("timestamp")  # 数据库中的创建时间

        logging.debug(
            f"🔍 检查无持仓订单: 账户={account_id}, 订单={order['order_id'][:15]}..., "
            f"币种={symbol}, 方向={order['side']}, 状态={state}"
        )

        if not order_timestamp:
            # 没有时间戳信息，记录警告后跳过
            logging.warning(
                f"⚠️ 订单无timestamp字段: 账户={account_id}, 订单={order['order_id'][:15]}..., 币种={symbol}"
            )
            return True

        try:
            # 转换为时间对象（支持不同格式）
            if isinstance(order_timestamp, datetime):
                # 已经是 datetime 对象，直接使用
                order_time = order_timestamp
            elif isinstance(order_timestamp, str):
                order_time = datetime.strptime(order_timestamp, "%Y-%m-%d %H:%M:%S")
            else:
                # 假设是时间戳（秒或毫秒）
                if order_timestamp > 1e10:  # 毫秒时间戳
                    order_time = datetime.fromtimestamp(order_timestamp / 1000)
                else:  # 秒时间戳
                    order_time = datetime.fromtimestamp(order_timestamp)

            current_time = datetime.now()
            time_diff_minutes = (current_time - order_time).total_seconds() / 60

            # 设置时间阈值（5分钟）
            TIME_THRESHOLD = 5

            if time_diff_minutes < TIME_THRESHOLD:
                # 订单刚创建，可能是刚下单未成交的情况，继续等待
                logging.info(
                    f"⏳ 订单创建 {time_diff_minutes:.1f}分钟，等待成交: "
                    f"账户={account_id}, 订单={order['order_id'][:15]}..., "
                    f"币种={symbol}, 方向={order['side']}"
                )
                return True
            else:
                # 订单创建超过阈值时间，还没有持仓，可能有问题
                logging.warning(
                    f"⚠️ 订单已创建 {time_diff_minutes:.1f}分钟但无持仓: "
                    f"账户={account_id}, 订单={order['order_id'][:15]}..., "
                    f"币种={symbol}, 方向={order['side']}, 状态={state}"
                )

                # 进一步检查订单状态
                if state in ("filled", "partially_filled"):
                    # 已成交但无持仓，说明持仓被平掉了
                    filled_amount = order_info.get("filled", 0)
                    total_amount = order_info.get("amount", 0)
                    logging.error(
                        f"🚨 严重异常：订单已成交但无持仓！账户={account_id}, "
                        f"订单={order['order_id'][:15]}..., 币种={symbol}, "
                        f"方向={order['side']}, 成交量={filled_amount}/{total_amount}, "
                        f"状态={state}，持仓可能已被平掉"
                    )
                    await self.db.update_order_by_id(
                        account_id, order_info["id"], {"status": state}
                    )
                    # 可选：取消该币种的所有订单
                    # await cancel_all_orders(self, exchange, account_id, symbol)
                else:
                    # 未成交但等待时间过长
                    logging.warning(
                        f"⚠️ 订单长时间未成交: 账户={account_id}, "
                        f"订单={order['order_id'][:15]}..., 已等待={time_diff_minutes:.1f}分钟"
                    )
                    await self.db.update_order_by_id(
                        account_id, order_info["id"], {"status": "canceled"}
                    )

                return True

        except Exception as e:
            logging.error(
                f"❌ 解析订单时间失败: 账户={account_id}, "
                f"订单={order['order_id'][:15]}..., 错误={e}",
                exc_info=True,
            )
            return True

    async def get_exchange_with_markets(self, account_id: int):
        """获取交易所实例（市场数据按需自动加载）

        这个方法返回交易所实例，市场数据会在首次使用时由 CCXT 自动加载。
        避免并发预加载导致的事件循环问题，同时受益于 api_limiter 的限流保护。

        Args:
            account_id: 账户ID

        Returns:
            交易所实例
        """
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                logging.error(
                    f"❌ 账户 {account_id} get_exchange 返回 None",
                    exc_info=True,
                )
                return None
            return exchange
        except Exception as e:
            logging.error(
                f"❌ 账户 {account_id} 获取交易所实例异常: {e}",
                exc_info=True,
            )
            return None

    async def _safe_check_positions(self, account_id: int):
        """安全封装的账户检查（防止一个账户崩溃影响整体）"""
        # 检查账户是否正在被信号处理
        if account_id in self.busy_accounts:
            logging.debug(f"⏸️ 账户 {account_id} 正在被信号处理，跳过本次价格监控")
            return

        try:
            # 异常处理已在上层 limited_check_positions 中进行
            await self.check_positions(account_id)
        except Exception as e:
            logging.error(
                f"❌ _safe_check_positions: 账户 {account_id} 异常: {e}",
                exc_info=True,
            )

    async def check_positions(self, account_id: int):
        """检查指定账户的持仓与订单（优化版本：缓存 + 并发）"""
        try:
            # ✅ 使用预加载市场数据的 exchange（避免 fetch_positions 时触发 load_markets）
            exchange = await self.get_exchange_with_markets(account_id)
            if not exchange:
                logging.warning(f"⚠️ 账户 {account_id} 无法创建交易所实例")
                return

            # ✅ 获取账户配置
            account_config = self.db.account_config_cache.get(account_id)
            if not account_config:
                logging.warning(f"⚠️ 账户 {account_id} 未配置（account_config_cache）")
                # logging.info(f"⚠️ 账户未配置: {account_id}")
                return

            max_position_list = account_config.get("max_position_list", "[]")
            try:
                account_symbols_arr = json.loads(max_position_list)
            except json.JSONDecodeError:
                logging.warning(f"⚠️ 账户 {account_id} max_position_list 解析失败")
                return

            if not account_symbols_arr:
                logging.info(f"📌 账户未配置监控币种: {account_id}")
                return

            # ✅ 一次获取所有未成交订单
            open_orders = await self.db.get_active_orders(account_id)
            if not open_orders:
                # 改为 debug 级别，减少日志噪音
                logging.debug(f"📭 账户 {account_id} 无未成交订单")
                return

            logging.info(
                f"📋 账户 {account_id} 有 {len(open_orders)} 个未成交订单待检查"
            )

            # --------------------------
            # 1. 缓存 symbol -> positions
            # --------------------------
            # ✅ 直接获取所有持仓，不再为每个 symbol 重复请求
            positions_dict = {}

            # ✅ 使用带重试机制的持仓查询（防止临时性错误）
            try:
                all_positions = await fetch_positions_with_retry(
                    exchange=exchange,
                    account_id=account_id,
                    symbol="",
                    params={"instType": "SWAP"},
                    retries=3,
                    api_limiter=self.api_limiter,
                    timeout=10.0,
                )
            except Exception as e:
                logging.error(
                    f"❌ 账户 {account_id} 获取持仓异常: {e}",
                    exc_info=True,
                )
                return

            if all_positions is None:
                logging.warning(
                    f"⚠️ 账户 {account_id} 获取持仓失败（已重试），跳过本轮检查"
                )
                return  # 直接返回，等待下一轮

            logging.info(f"📊 账户 {account_id} 获取到持仓总数: {len(all_positions)}")

            # 分类整理：symbol => [pos1, pos2, ...]
            position_summary = []
            for pos in all_positions:
                sym = pos["info"].get("instId")
                if not sym:
                    continue
                contracts = pos.get("contracts", 0)
                if contracts != 0:
                    position_summary.append(f"{sym}={contracts}")
                positions_dict.setdefault(sym, []).append(pos)

            if position_summary:
                logging.info(
                    f"📊 账户 {account_id} 有持仓的币种: {', '.join(position_summary)}"
                )
            else:
                logging.warning(f"⚠️ 账户 {account_id} 当前无任何持仓")

            # --------------------------
            # 2. 并发获取订单详情（带限流 + 重试机制 + 超时控制）
            # --------------------------
            order_infos = {}

            # 使用信号量限制并发，避免触发 API 限流
            fetch_semaphore = asyncio.Semaphore(3)  # 同时最多 3 个订单查询

            async def fetch_order_info(order):
                async with fetch_semaphore:
                    try:
                        # 为单个订单查询设置5秒超时
                        info = await asyncio.wait_for(
                            fetch_order_with_retry(
                                exchange,
                                account_id,
                                order["order_id"],
                                order["symbol"],
                                {"instType": "SWAP"},
                                retries=2,  # 减少重试次数，避免累积超时
                                api_limiter=self.api_limiter,
                            ),
                            timeout=5.0,
                        )
                        order_infos[order["order_id"]] = info
                        # 每个查询后延迟，进一步缓解限流
                        await asyncio.sleep(0.1)
                    except asyncio.TimeoutError:
                        logging.warning(
                            f"⏱️ 账户 {account_id} 订单 {order['order_id']} 查询超时(5秒)"
                        )
                    except Exception as e:
                        logging.error(
                            f"❌ 账户 {account_id} 订单 {order['order_id']} 查询失败: {e}",
                            exc_info=True,
                        )

            # 为整个订单查询批次设置超时（15秒）
            try:
                await asyncio.wait_for(
                    asyncio.gather(
                        *[fetch_order_info(o) for o in open_orders],
                        return_exceptions=True,
                    ),
                    timeout=15.0,
                )
            except asyncio.TimeoutError:
                logging.warning(f"⏱️ 账户 {account_id} 订单批量查询总超时(15秒)")

            # --------------------------
            # 2.5 异常状态检测：无持仓但有挂单和止损单
            # --------------------------
            # 检查是否有异常状态：无持仓 + 有挂单 + 有止损单
            await self._check_abnormal_state(
                account_id, exchange, positions_dict, open_orders
            )

            # --------------------------
            # 3. 遍历订单（逻辑不变）
            # --------------------------
            latest_fill_time = 0
            latest_order, executed_price, fill_date_time = None, None, None
            process_grid = False

            for order in open_orders:
                symbol = order["symbol"]
                order_info = order_infos.get(order["order_id"])
                positions = positions_dict.get(symbol, [])

                if not order_info:
                    logging.warning(
                        f"⚠️ 无订单信息，跳过订单: 账户={account_id}, 订单={order['order_id']}, "
                        f"币种={symbol}, 方向={order['side']}"
                    )
                    continue

                # ⚡ 处理无持仓情况
                if not positions:
                    position_contracts = sum(
                        p.get("contracts", 0) for p in positions_dict.get(symbol, [])
                    )
                    logging.warning(
                        f"⚠️ 无持仓但有挂单: 账户={account_id}, 订单={order['order_id']}, "
                        f"币种={symbol}, 方向={order['side']}, "
                        f"持仓数量={position_contracts}, 订单创建时间={order.get('timestamp', 'N/A')}"
                    )
                    should_skip = await self._handle_no_position_order(
                        order, order_info, account_id, symbol, exchange
                    )
                    if should_skip:
                        continue
                else:
                    # 记录持仓详情
                    position_details = []
                    for pos in positions:
                        contracts = pos.get("contracts", 0)
                        side = pos.get("side", "unknown")
                        entry_price = pos.get("entryPrice", 0)
                        if contracts != 0:
                            position_details.append(f"{side}:{contracts}@{entry_price}")
                    if position_details:
                        logging.debug(
                            f"📊 账户 {account_id} 币种 {symbol} 持仓详情: {', '.join(position_details)}"
                        )

                state = order_info["info"]["state"]
                logging.info(
                    f"🔍 订单状态: {account_id} {order['order_id']} {symbol} {order['side']} {state}"
                )
                if state == "canceled":
                    logging.info(
                        f"🔍 订单已撤销，跳过订单: {account_id} {order['order_id']} {symbol} {order['side']}"
                    )
                    await self.db.update_order_by_id(
                        account_id, order_info["id"], {"status": state}
                    )
                    continue

                elif state in ("filled", "partially_filled"):
                    logging.info(
                        f"🔍 订单已成交，处理订单: {account_id} {order['order_id']} {symbol} {order['side']} {state}"
                    )
                    if state == "partially_filled":
                        total_amount = Decimal(order_info["amount"])
                        filled_amount = Decimal(order_info["filled"])
                        fill_ratio = (
                            (filled_amount / total_amount * 100)
                            if total_amount > 0
                            else 0
                        )
                        logging.warning(
                            f"⚠️ 订单部分成交: 账户={account_id}, 订单={order['order_id']}, "
                            f"币种={symbol}, 方向={order['side']}, "
                            f"总量={total_amount}, 已成交={filled_amount}, "
                            f"成交率={fill_ratio:.2f}%, 价格={order_info['info'].get('fillPx', 'N/A')}"
                        )
                        if filled_amount < total_amount * Decimal("0.7"):
                            logging.warning(
                                f"🚫 订单部分成交率低于70%阈值，跳过处理: {account_id} {order['order_id']} "
                                f"成交率={fill_ratio:.2f}% < 70%"
                            )
                            continue
                        else:
                            logging.info(
                                f"✅ 订单部分成交率达到70%阈值，继续处理: {account_id} {order['order_id']} "
                                f"成交率={fill_ratio:.2f}% >= 70%"
                            )

                    fill_time = float(order_info["info"].get("fillTime", 0))
                    if fill_time > latest_fill_time:
                        logging.info(
                            f"✅ 更新最新成交订单: 账户={account_id}, 订单={order['order_id'][:15]}..., "
                            f"币种={symbol}, 方向={order['side']}, 成交价={order_info['info']['fillPx']}, "
                            f"成交时间={await milliseconds_to_local_datetime(fill_time)}"
                        )
                        latest_fill_time = fill_time
                        latest_order = order_info
                        executed_price = order_info["info"]["fillPx"]
                        fill_date_time = await milliseconds_to_local_datetime(fill_time)
                        process_grid = True
                    else:
                        logging.debug(
                            f"📅 订单成交时间较早，跳过: 账户={account_id}, "
                            f"订单={order['order_id'][:15]}..., 成交时间={fill_time}"
                        )

            # ✅ 后续逻辑不变
            if process_grid and latest_order:
                # symbol = latest_order['symbol']
                logging.info(
                    f"✅ 订单已成交: 用户={account_id}, 币种={symbol}, 方向={latest_order['side']}, 价格={executed_price}"
                )

                logging.info(f"🔧 开始管理网格订单: 账户={account_id}, 币种={symbol}")
                managed = await self.manage_grid_orders(latest_order, account_id)

                if managed:
                    logging.info(
                        f"✅ 网格订单管理成功，更新订单状态: 账户={account_id}, "
                        f"订单={latest_order['id']}, 币种={symbol}, "
                        f"方向={latest_order['side']}, 成交价={executed_price}"
                    )
                    await self.db.update_order_by_id(
                        account_id,
                        latest_order["id"],
                        {
                            "executed_price": executed_price,
                            "status": "filled",
                            "fill_time": fill_date_time,
                        },
                    )
                    logging.info(f"🔄 开始订单配对和利润计算: 账户={account_id}")
                    await self.update_order_status(
                        latest_order, account_id, executed_price, fill_date_time, symbol
                    )
                    logging.info(f"🛡️ 触发止损任务: 账户={account_id}（立即执行）")
                    await self.stop_loss_task.accounts_stop_loss_task(
                        account_id, immediate=True
                    )
                else:
                    logging.error(
                        f"❌ 网格订单管理失败: 账户={account_id}, "
                        f"订单={latest_order['id']}, 币种={symbol}"
                    )

        except Exception as e:
            logging.error(
                f"❌ 账户 {account_id} 检查持仓失败: {e}",
                exc_info=True,
            )
        finally:
            if exchange:
                await exchange.close()

    async def update_order_status(
        self,
        order: dict,
        account_id: int,
        executed_price: float,
        fill_date_time: str,
        symbol: str,
    ):
        """更新订单状态并配对计算利润（逻辑不变）"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                logging.error(
                    f"❌ 订单配对失败：无法获取交易所实例 - 账户={account_id}"
                )
                return

            logging.info(
                f"🔄 开始匹配订单: 账户={account_id}, 币种={symbol}, "
                f"订单ID={order['id'][:15]}..., 方向={order['side']}, 成交价={executed_price}"
            )
            print("🔄 开始匹配订单")

            side = "sell" if order["side"] == "buy" else "buy"
            logging.debug(
                f"🔍 查找配对订单: 账户={account_id}, 币种={order['info']['instId']}, "
                f"成交价={executed_price}, 查找方向={side}"
            )
            matched_order = await self.db.get_order_by_price_diff_v2(
                account_id, order["info"]["instId"], executed_price, side
            )

            if matched_order:
                logging.info(
                    f"✅ 找到配对订单: {matched_order['order_id'][:15]}..., "
                    f"方向={matched_order['side']}, 价格={matched_order.get('executed_price', 'N/A')}"
                )
            else:
                logging.info(f"📭 无配对订单: 账户={account_id}, 币种={symbol}")

            profit = 0
            group_id = ""
            market_precision = await get_market_precision(self, exchange, symbol)

            if matched_order:
                logging.info(f"💰 开始计算配对利润: 账户={account_id}, 币种={symbol}")
                qty = min(float(order["amount"]), float(matched_order["quantity"]))
                contract_size = market_precision["contract_size"]

                if order["side"] == "sell":
                    profit = (
                        (
                            Decimal(str(executed_price))
                            - Decimal(str(matched_order["executed_price"]))
                        )
                        * Decimal(str(qty))
                        * Decimal(str(contract_size))
                        * Decimal("0.99998")
                    )
                    logging.info(
                        f"💰 配对利润(卖单): 账户={account_id}, "
                        f"卖价={executed_price}, 买价={matched_order['executed_price']}, "
                        f"数量={qty}, 利润={profit}"
                    )
                    print(f"📊 用户 {account_id} 配对利润 (buy): {profit}")

                elif order["side"] == "buy":
                    profit = (
                        (
                            Decimal(str(matched_order["executed_price"]))
                            - Decimal(str(executed_price))
                        )
                        * Decimal(str(qty))
                        * Decimal(str(contract_size))
                        * Decimal("0.99998")
                    )
                    logging.info(
                        f"💰 配对利润(买单): 账户={account_id}, "
                        f"卖价={matched_order['executed_price']}, 买价={executed_price}, "
                        f"数量={qty}, 利润={profit}"
                    )
                    print(f"📊 配对利润 用户 {account_id} (sell): {profit}")

                if profit != 0:
                    group_id = str(uuid.uuid4())
                    logging.info(
                        f"📦 创建配对组: 账户={account_id}, 组ID={group_id[:15]}..., 利润={profit}"
                    )
                    await self.db.update_order_by_id(
                        account_id,
                        matched_order["order_id"],
                        {"profit": profit, "position_group_id": group_id},
                    )

                logging.info(
                    f"📝 更新当前订单配对信息: 账户={account_id}, "
                    f"订单={order['id'][:15]}..., 利润={profit}, 组ID={group_id[:15] if group_id else 'N/A'}..."
                )
                await self.db.update_order_by_id(
                    account_id,
                    order["id"],
                    {
                        "executed_price": executed_price,
                        "status": order["info"]["state"],
                        "fill_time": fill_date_time,
                        "profit": profit,
                        "position_group_id": group_id,
                    },
                )

        except Exception as e:
            logging.error(
                f"❌ 配对利润计算失败: 账户={account_id}, 币种={symbol}, 错误={e}",
                exc_info=True,
            )
            print(f"❌ 配对利润计算失败: {e}")
        finally:
            if exchange:
                await exchange.close()

    async def manage_grid_orders(self, order: dict, account_id: int):
        """网格订单管理（逻辑不变，仅优化并发安全性）"""
        try:
            # ✅ 使用预加载市场数据的 exchange（避免 fetch_positions 时触发 load_markets）
            exchange = await self.get_exchange_with_markets(account_id)
            if not exchange:
                print("❌ 未找到交易所实例")
                logging.error("❌ 未找到交易所实例")
                return False

            symbol = order["info"]["instId"]
            filled_price = Decimal(order["info"]["fillPx"])
            print(f"📌 用户 {account_id} 最新订单成交价: {filled_price}")
            logging.info(f"📌 用户 {account_id} 最新订单成交价: {filled_price}")

            price = await get_market_price(
                exchange, symbol, self.api_limiter, close_exchange=False
            )
            grid_step = Decimal(
                str(self.db.account_config_cache[account_id].get("grid_step", 0.002))
            )
            price_diff_ratio = abs(filled_price - price) / price

            if price_diff_ratio > grid_step:
                filled_price = price
                print(f"🔄 用户 {account_id} 价格偏差过大，使用市价: {filled_price}")
                logging.info(
                    f"🔄 用户 {account_id} 价格偏差过大，使用市价: {filled_price}"
                )

            buy_price = filled_price * (1 - grid_step)
            sell_price = filled_price * (1 + grid_step)

            # 添加超时控制（5秒）
            try:
                positions = await asyncio.wait_for(
                    exchange.fetch_positions_for_symbol(symbol, {"instType": "SWAP"}),
                    timeout=5.0,
                )
            except asyncio.TimeoutError:
                logging.error(f"⏱️ 用户 {account_id} 获取持仓超时(5秒)")
                return False

            if not positions:
                logging.warning(
                    f"🚫 网格下单失败：无持仓 - 账户={account_id}, 币种={symbol}, "
                    f"成交价={filled_price}, 市价={price}"
                )
                print(f"🚫 用户 {account_id} 网格下单：无持仓")
                return True

            total_position_value = await get_total_positions(
                self, account_id, symbol, "SWAP"
            )
            if total_position_value <= 0:
                logging.warning(
                    f"⚠️ 持仓价值为0，跳过网格下单: 账户={account_id}, 币种={symbol}, "
                    f"持仓价值={total_position_value}"
                )
                return True

            logging.info(
                f"📊 网格下单准备: 账户={account_id}, 币种={symbol}, "
                f"持仓价值={total_position_value}, 市价={price}, 网格步长={grid_step}"
            )

            balance = await get_account_balance(exchange, symbol)
            # print(f"💰 账户余额: {balance}")
            logging.info(f"💰 用户 {account_id} 账户余额: {balance}")

            symbol_tactics = (
                symbol.replace("-SWAP", "") if symbol.endswith("-SWAP") else symbol
            )
            tactics = await self.db.get_tactics_by_account_and_symbol(
                account_id, symbol_tactics
            )
            if not tactics:
                logging.error(f"🚫 未找到策略: {account_id} {symbol_tactics}")
                return False

            signal = await self.db.get_latest_signal(symbol, tactics)
            side = "buy" if signal["direction"] == "long" else "sell"
            market_precision = await get_market_precision(self, exchange, symbol)

            total_position_quantity = (
                Decimal(total_position_value)
                * Decimal(market_precision["amount"])
                * price
            )
            logging.info(f"🗑️ 取消所有挂单: 账户={account_id}, 币种={symbol}")
            await cancel_all_orders(self, exchange, account_id, symbol)

            percent_list = await get_grid_percent_list(
                self, account_id, signal["direction"]
            )
            buy_percent = percent_list.get("buy")
            sell_percent = percent_list.get("sell")

            logging.info(
                f"📊 网格比例配置: 账户={account_id}, 方向={signal['direction']}, "
                f"买单比例={buy_percent}, 卖单比例={sell_percent}"
            )

            buy_size = (total_position_value * Decimal(str(buy_percent))).quantize(
                Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
            )
            if buy_size < market_precision["min_amount"]:
                logging.info(f"📉 用户 {account_id} 买单过小: {buy_size}")
                return False

            sell_size = (total_position_value * Decimal(str(sell_percent))).quantize(
                Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
            )
            if sell_size < market_precision["min_amount"]:
                logging.info(f"📉 用户 {account_id} 卖单过小: {sell_size}")
                return False

            max_position = await get_max_position_value(self, account_id, symbol)
            buy_total = (
                total_position_quantity
                + buy_size * market_precision["amount"] * buy_price
                - sell_size * market_precision["amount"] * sell_price
            )
            if buy_total >= max_position:
                logging.info(f"⚠️ 用户 {account_id} 超过最大持仓，取消挂单")
                return False

            group_id = str(uuid.uuid4())
            pos_side = "long"
            if side == "buy" and signal["size"] == 1:  # 开多
                pos_side = "long"
            if side == "sell" and signal["size"] == -1:  # 开空
                pos_side = "short"

            logging.info(
                f"📈 确定开仓方向: 账户={account_id}, 信号方向={signal['direction']}, "
                f"信号大小={signal['size']}, 持仓方向={pos_side}"
            )

            buy_order = None
            sell_order = None

            buy_client_order_id = ""
            sell_client_order_id = ""

            logging.info(
                f"📝 开始下网格订单: 账户={account_id}, 币种={symbol}, "
                f"买单={buy_size}@{buy_price}, 卖单={sell_size}@{sell_price}"
            )

            if buy_size > 0:
                buy_client_order_id = await get_client_order_id()
                logging.debug(
                    f"📝 下买单: 账户={account_id}, 客户端订单ID={buy_client_order_id}"
                )
                buy_order = await open_position(
                    self,
                    account_id,
                    symbol,
                    "buy",
                    pos_side,
                    float(buy_size),
                    float(buy_price),
                    "limit",
                    buy_client_order_id,
                    False,
                )

            if sell_size > 0:
                sell_client_order_id = await get_client_order_id()
                logging.debug(
                    f"📝 下卖单: 账户={account_id}, 客户端订单ID={sell_client_order_id}"
                )
                sell_order = await open_position(
                    self,
                    account_id,
                    symbol,
                    "sell",
                    pos_side,
                    float(sell_size),
                    float(sell_price),
                    "limit",
                    sell_client_order_id,
                    False,
                )

            if buy_order and sell_order:
                await self.db.add_order(
                    {
                        "account_id": account_id,
                        "symbol": symbol,
                        "order_id": buy_order["id"],
                        "clorder_id": buy_client_order_id,
                        "price": float(buy_price),
                        "executed_price": None,
                        "quantity": float(buy_size),
                        "pos_side": pos_side,
                        "order_type": "limit",
                        "side": "buy",
                        "status": "live",
                        "position_group_id": "",
                    }
                )
                await self.db.add_order(
                    {
                        "account_id": account_id,
                        "symbol": symbol,
                        "order_id": sell_order["id"],
                        "clorder_id": sell_client_order_id,
                        "price": float(sell_price),
                        "executed_price": None,
                        "quantity": float(sell_size),
                        "pos_side": pos_side,
                        "order_type": "limit",
                        "side": "sell",
                        "status": "live",
                        "position_group_id": "",
                    }
                )
                logging.info(
                    f"✅ 用户 {account_id} 已挂单: 买{buy_price}({buy_size}) 卖{sell_price})"
                )
                return True
            else:
                await cancel_all_orders(self, exchange, account_id, symbol)
                # print("❌ 网格下单失败")
                logging.error(f"❌ 用户 {account_id} 网格下单失败")
                return False

        except Exception as e:
            logging.error(
                f"❌ 网格管理失败: 账户={account_id}, 币种={symbol}, 错误={e}",
                exc_info=True,
            )
            traceback.print_exc()
            return False
        finally:
            await exchange.close()

    async def _check_abnormal_state(
        self,
        account_id: int,
        exchange,
        positions_dict: dict,
        open_orders: list,
    ):
        """
        检测异常状态：无持仓但有挂单和止损单

        Args:
            account_id: 账户ID
            exchange: 交易所实例
            positions_dict: 持仓字典 {symbol: [positions]}
            open_orders: 未成交订单列表
        """
        try:
            # 按币种分组检查
            symbols_to_check = set()
            for order in open_orders:
                symbol = order["symbol"]
                positions = positions_dict.get(symbol, [])
                # 检查该币种是否有持仓
                has_position = any(p.get("contracts", 0) != 0 for p in positions if p)
                if not has_position:
                    symbols_to_check.add(symbol)

            if not symbols_to_check:
                return

            # 对每个无持仓的币种进行检查
            for symbol in symbols_to_check:
                # 检查是否有 limit 挂单
                symbol_limit_orders = [
                    o
                    for o in open_orders
                    if o["symbol"] == symbol and o["order_type"] == "limit"
                ]

                if not symbol_limit_orders:
                    continue

                # 检查是否有止损单
                try:
                    stop_loss_order = await self.db.get_unclosed_orders(
                        account_id, symbol, "conditional"
                    )
                except Exception as e:
                    logging.error(
                        f"❌ 查询止损单失败: 账户={account_id}, 币种={symbol}, 错误={e}"
                    )
                    stop_loss_order = None

                if stop_loss_order:
                    logging.warning(
                        f"🚨 异常状态检测: 账户={account_id}, 币种={symbol}, "
                        f"无持仓但有挂单({len(symbol_limit_orders)}个)和止损单，开始清理..."
                    )

                    # 检查账户是否正在被信号处理
                    if account_id in self.busy_accounts:
                        logging.info(f"⏸️ 账户 {account_id} 正在处理信号，跳过清理")
                        continue

                    # 再次确认无持仓（双重检查）
                    try:
                        if self.api_limiter:
                            await self.api_limiter.check_and_wait()

                        current_positions = await exchange.fetch_positions(
                            "", {"instType": "SWAP"}
                        )
                        symbol_positions = [
                            p
                            for p in current_positions
                            if p["symbol"] == symbol and p["contracts"] != 0
                        ]

                        if symbol_positions:
                            logging.info(
                                f"ℹ️ 账户 {account_id} 币种 {symbol} 有持仓，跳过清理"
                            )
                            continue
                    except Exception as e:
                        logging.error(
                            f"❌ 再次检查持仓失败: 账户={account_id}, 币种={symbol}, 错误={e}"
                        )
                        continue

                    # 🔍 在撤销止损单之前，先检查并更新止损单状态
                    try:
                        if self.api_limiter:
                            await self.api_limiter.check_and_wait()

                        # 查询止损单的实际状态
                        # 将 symbol 转换为交易所格式（BTC-USDT-SWAP -> BTC/USDT:USDT）
                        exchange_symbol = (
                            symbol.replace("-SWAP", "").replace("-", "/") + ":USDT"
                        )

                        logging.info(
                            f"🔍 查询止损单状态: 账户={account_id}, "
                            f"订单ID={stop_loss_order['order_id'][:15]}..., 币种={symbol}"
                        )

                        stop_loss_order_info = await fetch_order_with_retry(
                            exchange,
                            account_id,
                            stop_loss_order["order_id"],
                            exchange_symbol,
                            {"instType": "SWAP", "trigger": "true"},
                            retries=2,
                            api_limiter=self.api_limiter,
                        )

                        if stop_loss_order_info:
                            order_state = stop_loss_order_info["info"]["state"]
                            logging.info(
                                f"📊 止损单状态: 账户={account_id}, "
                                f"订单={stop_loss_order['order_id'][:15]}..., 状态={order_state}"
                            )

                            # 如果止损单状态是 effective（已触发）或其他异常状态，更新数据库
                            if order_state in [
                                "pause",
                                "effective",
                                "canceled",
                                "order_failed",
                                "partially_failed",
                            ]:
                                # 如果状态是 effective，检查持仓是否已被平掉
                                final_status = order_state
                                if order_state == "effective":
                                    # 无持仓说明止损单已生效
                                    final_status = "filled"
                                    logging.info(
                                        f"✅ 止损单已生效（持仓已平）: 账户={account_id}, "
                                        f"订单={stop_loss_order['order_id'][:15]}..., 币种={symbol}"
                                    )

                                fill_date_time = await milliseconds_to_local_datetime(
                                    stop_loss_order_info.get("lastUpdateTimestamp", 0)
                                )

                                logging.info(
                                    f"📝 更新止损单状态: 账户={account_id}, "
                                    f"订单={stop_loss_order['order_id'][:15]}..., "
                                    f"原始状态={order_state}, 最终状态={final_status}, "
                                    f"触发价={stop_loss_order_info['info'].get('slTriggerPx', 'N/A')}"
                                )

                                # 更新数据库状态
                                try:
                                    await self.db.update_order_by_id(
                                        account_id,
                                        stop_loss_order["order_id"],
                                        {
                                            "status": final_status,
                                            "executed_price": float(
                                                stop_loss_order_info["info"].get(
                                                    "slTriggerPx", 0
                                                )
                                            ),
                                            "fill_time": fill_date_time,
                                        },
                                    )
                                    logging.info(
                                        f"✅ 止损单状态已更新: 账户={account_id}, "
                                        f"订单={stop_loss_order['order_id'][:15]}..., "
                                        f"状态={final_status}"
                                    )
                                except Exception as e:
                                    logging.error(
                                        f"❌ 更新止损单状态失败: 账户={account_id}, "
                                        f"订单={stop_loss_order['order_id'][:15]}..., "
                                        f"错误={e}",
                                        exc_info=True,
                                    )
                    except Exception as e:
                        logging.warning(
                            f"⚠️ 查询止损单状态失败，继续清理: 账户={account_id}, "
                            f"订单={stop_loss_order['order_id'][:15]}..., 错误={e}"
                        )
                        # 即使查询失败，也继续清理流程

                    # 使用 cancel_all_orders 撤销所有挂单和止损单
                    # 将 symbol 转换为交易所需要的格式（BTC-USDT-SWAP -> BTC/USDT:USDT）
                    exchange_symbol = (
                        symbol.replace("-SWAP", "").replace("-", "/") + ":USDT"
                    )

                    logging.info(
                        f"🗑️ 开始清理异常状态: 账户={account_id}, 币种={symbol}, "
                        f"挂单数={len(symbol_limit_orders)}, 有止损单=True"
                    )

                    # 撤销所有普通订单和条件单（止损单）
                    await cancel_all_orders(
                        self, exchange, account_id, exchange_symbol, True
                    )

                    # ✅ 【新增】直接更新所有 limit 挂单的数据库状态为 canceled
                    logging.info(
                        f"📝 更新所有 limit 挂单状态为 canceled: 账户={account_id}, "
                        f"币种={symbol}, 挂单数={len(symbol_limit_orders)}"
                    )

                    for limit_order in symbol_limit_orders:
                        try:
                            await self.db.update_order_by_id(
                                account_id,
                                limit_order["order_id"],
                                {"status": "canceled"},
                            )
                            logging.debug(
                                f"✅ Limit 挂单已更新为 canceled: "
                                f"账户={account_id}, 订单={limit_order['order_id'][:15]}..."
                            )
                        except Exception as e:
                            logging.error(
                                f"❌ 更新 limit 挂单状态失败: "
                                f"账户={account_id}, 订单={limit_order['order_id'][:15]}..., 错误={e}",
                                exc_info=True,
                            )

                    logging.info(
                        f"✅ 账户 {account_id} 币种 {symbol} 异常状态已清理完成"
                    )

        except Exception as e:
            logging.error(
                f"❌ 异常状态检测失败: 账户={account_id}, 错误={e}",
                exc_info=True,
            )

    # 其他方法保持不变（get_order_info, check_and_close_position 等）
    async def get_order_info(self, account_id: int, order_id: str):
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return None
        try:
            order_info = await exchange.fetch_order(
                order_id, None, None, {"instType": "SWAP"}
            )
            print(f"📋 用户 {account_id} 订单信息: {order_info}")
            logging.info(f"📋 用户 {account_id} 订单信息: {order_info}")
            return order_info
        except Exception as e:
            print(f"❌ 用户 {account_id} 获取订单失败: {e}")
            logging.error(f"❌ 用户 {account_id} 获取订单失败: {e}")
        finally:
            await exchange.close()
