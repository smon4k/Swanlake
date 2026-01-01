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

# ✅ 方案1：导入 SignalProcessingTask，用于调用 cleanup_opposite_positions
# 注意：避免循环导入，需要在运行时注入
if __name__ != "__main__":
    try:
        from signal_processing_task import SignalProcessingTask
    except ImportError:
        # 延迟导入以避免循环依赖
        SignalProcessingTask = None


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
        signal_processing_task=None,  # ✅ 新增：SignalProcessingTask 实例
        api_limiter=None,
        signal_processing_active: asyncio.Event = None,  # ✅ 新增参数
    ):
        self.config = config
        self.db = db
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task  # 保留引用
        self.signal_processing_task = (
            signal_processing_task  # ✅ 保存 SignalProcessingTask 实例
        )
        self.running = True  # 控制运行状态
        self.busy_accounts = busy_accounts  # 引用交易机器人中的忙碌账户集合
        self.api_limiter = api_limiter  # 全局API限流器

        # ✅ 【新增】任务协调标志
        self.signal_processing_active = signal_processing_active
        # ✅ 账户并发限制（动态设置，确保所有账户都能被检测）
        self.account_semaphore = asyncio.Semaphore(
            15
        )  # 限制 15 个账户并发（略大于账户数）
        self.order_semaphore = asyncio.Semaphore(10)  # 订单查询并发限流
        self.market_precision_cache = {}  # 市场精度缓存

        # ⏱️ 超时配置
        self.account_check_timeout = (
            45.0  # 单个账户检查超时时间（秒）- 增加到45秒以适应网格单创建
        )
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
                # ✅ 【新增】优先级1：检查信号处理是否活跃
                if self.signal_processing_active:
                    if self.signal_processing_active.is_set():
                        logging.info("⏸️ 信号处理优先级高于价格监控，暂停2秒")
                        await asyncio.sleep(2)
                        continue

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

                    # ✅ 【新增】定期恢复失败的信号（每个优先级更新周期执行一次）
                    try:
                        await self.recover_failed_signal_accounts()
                    except Exception as e:
                        logging.error(f"❌ 恢复失败信号异常: {e}")

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
        # ✅ 【新增】优先级检查1：如果信号处理活跃，跳过价格监控
        if self.signal_processing_active:
            if self.signal_processing_active.is_set():
                logging.debug(f"⏸️ 账户 {account_id} 价格监控推迟，信号处理优先")
                return

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
        exchange = None  # ✅ 在 try 外部初始化，确保 finally 块能访问
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

            # ✅ 【改进流程】先获取持仓信息，独立于订单检查
            # 这样补救检查可以不依赖 open_orders 的存在

            # --------------------------
            # 1. 先获取持仓（不依赖是否有订单）
            # --------------------------
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
                return

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
            # else:
            #     logging.warning(f"⚠️ 账户 {account_id} 当前无任何持仓")

            # --------------------------
            # 2. 执行补救检查（基于持仓，不依赖 open_orders）
            # --------------------------
            # ✅ 【关键改进】补救检查独立执行，即使没有活跃订单也会运行
            await self._check_incomplete_grid_orders(
                account_id, exchange, positions_dict
            )

            # --------------------------
            # 3. 获取未成交订单（用于订单状态检查）
            # --------------------------
            open_orders = await self.db.get_active_orders(account_id)
            if not open_orders:
                # 改为 debug 级别，减少日志噪音
                logging.debug(f"📭 账户 {account_id} 无未成交订单")
                return

            logging.info(
                f"📋 账户 {account_id} 有 {len(open_orders)} 个未成交订单待检查"
            )

            # --------------------------
            # 4. 并发获取订单详情（带限流 + 重试机制 + 超时控制）
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

                # ✅ 【方案2改进】网格单创建单独设置超时（30秒，给买卖单留足时间）
                try:
                    managed = await asyncio.wait_for(
                        self.manage_grid_orders(latest_order, account_id),
                        timeout=30.0,
                    )
                except asyncio.TimeoutError:
                    logging.error(
                        f"⏱️ 账户 {account_id} 网格单创建超时(30秒)，将在下一轮补救检查中处理"
                    )
                    managed = False
                except Exception as e:
                    logging.error(
                        f"❌ 账户 {account_id} 网格单创建异常: {e}",
                        exc_info=True,
                    )
                    managed = False

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
            # ✅ 确保 exchange 被关闭，释放事件循环资源，避免并发冲突
            if exchange:
                try:
                    await exchange.close()
                    logging.debug(f"✅ 已关闭exchange: 账户={account_id}")
                except Exception as e:
                    logging.warning(f"⚠️ 关闭exchange失败: 账户={account_id}, {e}")

    async def update_order_status(
        self,
        order: dict,
        account_id: int,
        executed_price: float,
        fill_date_time: str,
        symbol: str,
    ):
        """更新订单状态并配对计算利润（逻辑不变）"""
        exchange = None  # ✅ 在 try 外部初始化，确保 finally 块能访问
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
            # ✅ 确保 exchange 被关闭，释放事件循环资源，避免并发冲突
            if exchange:
                try:
                    await exchange.close()
                    logging.debug(f"✅ 已关闭exchange: 账户={account_id}")
                except Exception as e:
                    logging.warning(f"⚠️ 关闭exchange失败: 账户={account_id}, {e}")

    async def manage_grid_orders(self, order: dict, account_id: int):
        """网格订单管理（逻辑不变，仅优化并发安全性）"""
        symbol = None  # ✅ 提前初始化，防止异常处理中未定义
        exchange = None  # ✅ 提前初始化，防止finally中未定义
        try:
            # ✅ 使用预加载市场数据的 exchange（避免 fetch_positions 时触发 load_markets）
            exchange = await self.get_exchange_with_markets(account_id)
            if not exchange:
                print("❌ 未找到交易所实例")
                logging.error("❌ 未找到交易所实例")
                return False

            # ✅ 安全地获取 symbol，兼容两种格式（CCXT 和数据库格式）
            if (
                "info" in order
                and isinstance(order["info"], dict)
                and "instId" in order["info"]
            ):
                # CCXT 格式
                symbol = order["info"]["instId"]
            elif "symbol" in order:
                # 数据库格式
                symbol = order["symbol"]
            else:
                raise ValueError(
                    f"❌ Order 格式不正确，无法获取 symbol。"
                    f"有效的 key: {list(order.keys())}"
                )

            # ✅ 安全地获取成交价，兼容两种格式
            if (
                "info" in order
                and isinstance(order["info"], dict)
                and "fillPx" in order["info"]
            ):
                # CCXT 格式
                filled_price = Decimal(order["info"]["fillPx"])
            elif "executed_price" in order:
                # 数据库格式
                filled_price = Decimal(str(order["executed_price"]))
            else:
                raise ValueError(
                    f"❌ Order 格式不正确，无法获取成交价。"
                    f"有效的 key: {list(order.keys())}"
                )

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
            logging.info(f"用户 {account_id} 总持仓数量: {total_position_quantity}")

            max_position = await get_max_position_value(self, account_id, symbol)
            # 总持仓数量如果小于最大仓位的5%的话要平掉所有仓位
            min_position_threshold = max_position * Decimal("0.05")  # 最大仓位的5%
            logging.info(
                f"用户 {account_id} 最小持仓数量阈值: {min_position_threshold}"
            )
            if total_position_quantity < min_position_threshold:
                logging.info(
                    f"🗑️ 总持仓数量小于最大仓位的5%，平掉所有仓位: 账户={account_id}, 币种={symbol}"
                )
                await self.signal_processing_task.cleanup_opposite_positions(
                    account_id, symbol, side
                )

                # 取消所有未成交订单
                await cancel_all_orders(self, exchange, account_id, symbol, True)

                return False

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
            buy_success = False
            sell_success = False

            buy_client_order_id = ""
            sell_client_order_id = ""

            logging.info(
                f"📝 开始下网格订单: 账户={account_id}, 币种={symbol}, "
                f"买单={buy_size}@{buy_price}, 卖单={sell_size}@{sell_price}"
            )

            # ✅ 【方案1改进】第1步：先下买单，加独立超时防止卡死
            if buy_size > 0:
                try:
                    buy_client_order_id = await get_client_order_id()
                    logging.info(
                        f"📝 下买单: 账户={account_id}, 币种={symbol}, "
                        f"数量={buy_size}, 价格={buy_price}"
                    )
                    # ✅ 新增：买单API独立超时10秒，快速失败
                    buy_order = await asyncio.wait_for(
                        open_position(
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
                        ),
                        timeout=10.0,
                    )

                    if buy_order:
                        buy_success = True
                        logging.info(
                            f"✅ 买单下单成功: 账户={account_id}, 订单ID={buy_order['id'][:15]}..."
                        )

                        # ✅ 优化：缩短延迟到0.1秒，节省时间
                        await asyncio.sleep(0.1)

                    else:
                        logging.error(
                            f"❌ 买单下单失败: 账户={account_id}, 币种={symbol}"
                        )

                except asyncio.TimeoutError:
                    logging.error(
                        f"❌ 买单API超时(10秒): 账户={account_id}, 币种={symbol}"
                    )
                except Exception as e:
                    logging.error(
                        f"❌ 买单下单异常: 账户={account_id}, 错误={e}", exc_info=True
                    )

            # ✅ 【方案1改进】第2步：再下卖单（独立处理，不受买单影响），加独立超时
            if sell_size > 0:
                try:
                    sell_client_order_id = await get_client_order_id()
                    logging.info(
                        f"📝 下卖单: 账户={account_id}, 币种={symbol}, "
                        f"数量={sell_size}, 价格={sell_price}"
                    )
                    # ✅ 新增：卖单API独立超时10秒，快速失败
                    sell_order = await asyncio.wait_for(
                        open_position(
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
                        ),
                        timeout=10.0,
                    )

                    if sell_order:
                        sell_success = True
                        logging.info(
                            f"✅ 卖单下单成功: 账户={account_id}, 订单ID={sell_order['id'][:15]}..."
                        )

                    else:
                        logging.error(
                            f"❌ 卖单下单失败: 账户={account_id}, 币种={symbol}"
                        )
                        # ⚠️ 【关键】卖单失败不取消买单，因为买单已独立成功

                except asyncio.TimeoutError:
                    logging.error(
                        f"❌ 卖单API超时(10秒): 账户={account_id}, 币种={symbol}"
                    )
                    # ⚠️ 卖单超时也不取消买单，会被补救机制处理
                except Exception as e:
                    logging.error(
                        f"❌ 卖单下单异常: 账户={account_id}, 错误={e}", exc_info=True
                    )
                    # ⚠️ 卖单异常也不取消买单

            # ✅ 【方案1改进】第3步：分级处理结果
            if buy_success and sell_success:
                # 🟢 完全成功：存储两个订单
                logging.info(f"🟢 网格订单完全成功: 账户={account_id}, 币种={symbol}")
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
                    f"✅ 用户 {account_id} 已挂单: 买{buy_price}({buy_size}) 卖{sell_price}({sell_size})"
                )
                return True

            elif buy_success and not sell_success:
                # 🟡 部分成功：只存储买单（关键改进点）
                logging.warning(
                    f"🟡 网格订单部分成功: 账户={account_id}, 币种={symbol}, "
                    f"买单成功，卖单失败，保存买单并标记待重试卖单"
                )
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
                        "status": "live",  # 标记为已挂出
                        "position_group_id": "",
                    }
                )
                logging.info(
                    f"✅ 用户 {account_id} 买单已挂出: 买{buy_price}({buy_size}), "
                    f"下次检查时将继续创建卖单"
                )
                # ⚠️ 返回 True 表示操作成功（买单成功），卖单会在后续轮次重试
                return True

            elif not buy_success and sell_success:
                # 🟠 异常情况：卖单成功但买单失败（极少见，可能是买单在最后阶段失败）
                logging.error(
                    f"🟠 异常状态: 账户={account_id}, 币种={symbol}, "
                    f"卖单成功但买单失败，将卖单也保存作为孤立订单"
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
                # ⚠️ 返回 False 让上层知道网格不完整
                return False

            else:
                # 🔴 完全失败：买单和卖单都失败
                logging.error(
                    f"🔴 网格订单完全失败: 账户={account_id}, 币种={symbol}, "
                    f"买单失败={not buy_success}, 卖单失败={not sell_success}"
                )
                # ⚠️ 只在完全失败时才取消所有订单
                await cancel_all_orders(self, exchange, account_id, symbol)
                return False

        except Exception as e:
            logging.error(
                f"❌ 网格管理失败: 账户={account_id}, 币种={symbol}, 错误={e}",
                exc_info=True,
            )
            traceback.print_exc()
            return False
        finally:
            # ✅ 防御性检查：确保 exchange 已初始化再关闭
            if exchange:
                try:
                    await exchange.close()
                except Exception as e:
                    logging.warning(f"⚠️ 关闭 exchange 失败: {e}")

    async def _check_incomplete_grid_orders(
        self,
        account_id: int,
        exchange,
        positions_dict: dict,
    ):
        """
        检测异常状态：有持仓但缺少对应的网格单（补救方案）

        场景：开仓订单已成交，但网格单创建失败/超时导致缺失
        解决：重新触发网格单创建

        Args:
            account_id: 账户ID
            exchange: 交易所实例
            positions_dict: 持仓字典 {symbol: [positions]}
        """
        try:
            # 遍历所有有持仓的币种
            for symbol, positions in positions_dict.items():
                # 检查是否有实际持仓
                has_position = any(p.get("contracts", 0) != 0 for p in positions if p)
                if not has_position:
                    continue

                logging.info(
                    f"🔍 检查网格单完整性: 账户={account_id}, 币种={symbol}, 有持仓"
                )

                # 查找该币种最近的已成交开仓订单
                try:
                    recent_filled_order = await self.db.get_recent_filled_open_order(
                        account_id, symbol, minutes_back=30
                    )

                    if not recent_filled_order:
                        logging.debug(
                            f"📭 账户 {account_id} 币种 {symbol} 无最近的已成交开仓订单"
                        )
                        continue

                    # 检查是否已有对应的网格单
                    # ✅ 改进：传入开仓订单的成交时间，只查该时间之后的网格单
                    # 这样避免了历史订单的干扰，精确关联开仓订单与网格单
                    open_order_time = recent_filled_order.get(
                        "updated_at"
                    ) or recent_filled_order.get("created_at")

                    has_active_buy_grid = await self.db.has_pending_order(
                        account_id,
                        symbol,
                        "buy",
                        include_all=False,
                        after_time=open_order_time,
                    )
                    has_active_sell_grid = await self.db.has_pending_order(
                        account_id,
                        symbol,
                        "sell",
                        include_all=False,
                        after_time=open_order_time,
                    )
                    # logging.debug(f"has_active_buy_grid: {has_active_buy_grid}")
                    # logging.debug(f"has_active_sell_grid: {has_active_sell_grid}")
                    # 🟢 正常情况：既有买单又有卖单
                    if has_active_buy_grid and has_active_sell_grid:
                        logging.debug(
                            f"✅ 账户 {account_id} 币种 {symbol} 网格单完整 "
                            f"(买=True, 卖=True)"
                        )
                        continue

                    # 🚨 异常情况1：只有买单，缺卖单
                    has_missing_sell = has_active_buy_grid and not has_active_sell_grid
                    # 🚨 异常情况2：只有卖单，缺买单
                    has_missing_buy = not has_active_buy_grid and has_active_sell_grid
                    # 🚨 异常情况3：都没有
                    has_no_grid = not has_active_buy_grid and not has_active_sell_grid
                    # logging.debug(f"has_missing_sell: {has_missing_sell}")
                    # logging.debug(f"has_missing_buy: {has_missing_buy}")
                    # logging.debug(f"has_no_grid: {has_no_grid}")
                    if has_missing_sell or has_missing_buy or has_no_grid:
                        # 如果都没有，检查是否曾经有过网格单
                        if has_no_grid:
                            has_ever_buy = await self.db.has_pending_order(
                                account_id,
                                symbol,
                                "buy",
                                include_all=True,
                                after_time=open_order_time,
                            )
                            has_ever_sell = await self.db.has_pending_order(
                                account_id,
                                symbol,
                                "sell",
                                include_all=True,
                                after_time=open_order_time,
                            )

                            if has_ever_buy and has_ever_sell:
                                # 曾经都有过，现在都没有 → 被撤销，不动作
                                logging.warning(
                                    f"⚠️ 账户={account_id}, 币种={symbol}, "
                                    f"网格单已被撤销（曾有买和卖），保持持仓不动作"
                                )
                                continue

                        # 异常情况需要补救
                        missing_desc = (
                            "缺卖单"
                            if has_missing_sell
                            else ("缺买单" if has_missing_buy else "缺全部网格单")
                        )
                        logging.warning(
                            f"🚨 异常检测: 账户={account_id}, 币种={symbol}, "
                            f"有持仓但{missing_desc}，订单={recent_filled_order['order_id'][:15]}..., "
                            f"成交价={recent_filled_order.get('executed_price', 'N/A')}"
                        )
                    else:
                        # 不应该到达这里的情况
                        continue

                    # 检查是否距离上次创建网格单不久（避免频繁重试）
                    last_attempt_time = recent_filled_order.get(
                        "updated_at"
                    ) or recent_filled_order.get("created_at")
                    if last_attempt_time:
                        time_elapsed = (
                            datetime.now() - last_attempt_time
                        ).total_seconds()
                        if time_elapsed < 60:  # 60秒内不重试
                            logging.info(
                                f"⏳ 上次网格单创建失败距今 {time_elapsed:.0f}秒，"
                                f"等待冷却期后重试: 账户={account_id}, 币种={symbol}"
                            )
                            continue

                    # 重新触发网格单创建
                    logging.info(
                        f"🔧 开始补救创建网格单: 账户={account_id}, "
                        f"币种={symbol}, 订单={recent_filled_order['order_id'][:15]}..."
                    )

                    try:
                        managed = await asyncio.wait_for(
                            self.manage_grid_orders(recent_filled_order, account_id),
                            timeout=20.0,
                        )

                        if managed:
                            logging.info(
                                f"✅ 补救网格单创建成功: 账户={account_id}, "
                                f"币种={symbol}"
                            )
                        else:
                            logging.error(
                                f"❌ 补救网格单创建失败: 账户={account_id}, "
                                f"币种={symbol}，将在下轮继续尝试"
                            )

                    except asyncio.TimeoutError:
                        logging.error(
                            f"⏱️ 补救网格单创建超时(20秒): 账户={account_id}, "
                            f"币种={symbol}"
                        )
                        # ✅ 继续处理下一个币种
                        continue

                    except Exception as e:
                        logging.error(
                            f"❌ 补救网格单创建异常: 账户={account_id}, "
                            f"币种={symbol}, 错误={e}",
                            exc_info=True,
                        )
                        # ✅ 继续处理下一个币种
                        continue

                except Exception as e:
                    # ✅ 此时 symbol 肯定有值，因为我们在循环内
                    logging.error(
                        f"❌ 检查网格单完整性异常: 账户={account_id}, "
                        f"币种={symbol}, 错误={e}",
                        exc_info=True,
                    )
                    # ✅ 继续处理下一个币种，不要中断整个函数
                    continue

        except Exception as e:
            # ✅ 外层异常处理：处理循环外或其他意外异常
            logging.error(
                f"❌ 检查不完整网格单异常: 账户={account_id}, 错误={e}",
                exc_info=True,
            )

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

    async def recover_failed_signal_accounts(self):
        """
        恢复 processing 状态的失败信号中的账户

        新逻辑（V2）：
        1. 查询所有 status='processing' 的信号（有 failed_accounts）
        2. 对每个失败账户检查实际仓位
        3. 无仓位（开仓）→ 调用 handle_open_position；有仓位（平仓）→ 调用 cleanup_opposite_positions
        4. 成功 → 移出failed_accounts，加入success_accounts
        5. 全部成功 → status='processed'；有失败 → 继续保持processing
        6. 达到超时（10分钟）→ 标记为 failed
        """
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                # 查询所有 processing 信号且有失败账户的信号
                cursor.execute(
                    """SELECT id, failed_accounts, success_accounts, direction, symbol, price, size, last_update_time
                       FROM g_signals 
                       WHERE status='processing' 
                       AND failed_accounts IS NOT NULL 
                       AND failed_accounts != '[]'
                       ORDER BY last_update_time ASC 
                       LIMIT 10"""
                )
                processing_signals = cursor.fetchall()

            if not processing_signals:
                logging.debug("✅ 无 processing 信号需要恢复")
                return

            logging.info(
                f"🔄 发现 {len(processing_signals)} 个 processing 信号需要恢复"
            )

            for signal_row in processing_signals:
                signal_id = signal_row["id"]
                failed_accounts_json = signal_row["failed_accounts"]
                success_accounts_json = signal_row["success_accounts"]
                direction = signal_row["direction"]
                symbol = signal_row["symbol"]
                price = Decimal(str(signal_row["price"]))
                size = signal_row["size"]
                last_update_time = signal_row["last_update_time"]

                try:
                    # 检查超时（10分钟）
                    if last_update_time:
                        elapsed = (datetime.now() - last_update_time).total_seconds()
                        if elapsed > 600:  # 10分钟
                            logging.warning(
                                f"⏱️ 信号 {signal_id} 超时({elapsed}秒 > 600秒)，标记为failed"
                            )
                            conn2 = self.db.get_db_connection()
                            with conn2.cursor() as cursor2:
                                cursor2.execute(
                                    "UPDATE g_signals SET status='failed' WHERE id=%s",
                                    (signal_id,),
                                )
                            conn2.commit()
                            conn2.close()
                            continue

                    failed_accounts = json.loads(failed_accounts_json or "[]")
                    success_accounts = json.loads(success_accounts_json or "[]")

                    if not failed_accounts:
                        continue

                    is_close_signal = size == 0
                    signal_type = "平仓" if is_close_signal else "开仓"

                    logging.info(
                        f"📊 恢复{signal_type}信号: ID={signal_id}, "
                        f"失败账户={len(failed_accounts)}, 成功账户={len(success_accounts)}"
                    )

                    newly_recovered = []

                    for account_info in failed_accounts:
                        # ✅ 处理两种格式：整数 (2) 或字典 ({"account_id": 2})
                        if isinstance(account_info, dict):
                            account_id = account_info.get("account_id")
                        else:
                            account_id = account_info

                        try:
                            # 检查账户实际仓位
                            actual_positions = await get_total_positions(
                                self, account_id, symbol, "SWAP"
                            )

                            if is_close_signal:
                                # 平仓信号：应该无仓位
                                if (
                                    actual_positions is not None
                                    and actual_positions > 0
                                ):
                                    logging.info(
                                        f"🔄 恢复平仓: 信号={signal_id}, 账户={account_id}, "
                                        f"币种={symbol}, 仓位={actual_positions}"
                                    )

                                    try:
                                        if self.signal_processing_task:
                                            await self.signal_processing_task.cleanup_opposite_positions(
                                                account_id, symbol, "long"
                                            )
                                            newly_recovered.append(account_id)
                                            logging.info(
                                                f"✅ 账户 {account_id} 恢复平仓成功"
                                            )
                                        else:
                                            logging.warning(
                                                f"⚠️ signal_processing_task 未注入"
                                            )
                                    except Exception as e:
                                        logging.error(
                                            f"❌ 账户 {account_id} 恢复平仓失败: {e}"
                                        )
                                else:
                                    # 已无仓位，平仓成功
                                    logging.info(
                                        f"✅ 账户 {account_id} 已无仓位，平仓验证成功"
                                    )
                                    newly_recovered.append(account_id)

                            else:
                                # 开仓信号：应该有仓位
                                if actual_positions is None or actual_positions == 0:
                                    logging.info(
                                        f"🔄 恢复开仓: 信号={signal_id}, 账户={account_id}, "
                                        f"币种={symbol}"
                                    )

                                    try:
                                        pos_side = (
                                            "long" if direction == "long" else "short"
                                        )
                                        side = "buy" if direction == "long" else "sell"

                                        if self.signal_processing_task:
                                            # ✅ 关键修复：捕获返回值
                                            result = await self.signal_processing_task.handle_open_position(
                                                account_id=account_id,
                                                symbol=symbol,
                                                pos_side=pos_side,
                                                side=side,
                                                price=price,
                                                open_coefficient=Decimal(size),
                                            )

                                            # ✅ 检查返回值，只有成功才标记为已恢复
                                            if result:  # True 表示开仓成功
                                                newly_recovered.append(account_id)
                                                logging.info(
                                                    f"✅ 账户 {account_id} 恢复开仓成功"
                                                )
                                            else:  # False 或 None 表示开仓失败
                                                logging.error(
                                                    f"❌ 账户 {account_id} 恢复开仓失败，handle_open_position 返回 {result}，将在下次继续尝试"
                                                )
                                        else:
                                            logging.warning(
                                                f"⚠️ signal_processing_task 未注入"
                                            )

                                    except Exception as e:
                                        logging.error(
                                            f"❌ 账户 {account_id} 恢复开仓异常: {e}",
                                            exc_info=True,
                                        )
                                else:
                                    # 已有仓位，开仓成功
                                    logging.info(
                                        f"✅ 账户 {account_id} 已有仓位({actual_positions})，开仓验证成功"
                                    )
                                    newly_recovered.append(account_id)

                        except Exception as e:
                            logging.error(
                                f"❌ 处理失败账户异常: 账户={account_id}, 信号={signal_id}, 错误={e}"
                            )

                    # 更新信号状态
                    if newly_recovered:
                        self._update_signal_recovery_status_v2(
                            signal_id,
                            failed_accounts,
                            newly_recovered,
                            success_accounts,
                        )

                except json.JSONDecodeError as e:
                    logging.error(
                        f"❌ 解析失败账户列表失败: 信号={signal_id}, 错误={e}"
                    )
                except Exception as e:
                    logging.error(
                        f"❌ 恢复 processing 信号异常: 信号={signal_id}, 错误={e}",
                        exc_info=True,
                    )

        except Exception as e:
            logging.error(f"❌ 恢复 processing 信号总体异常: {e}", exc_info=True)
        finally:
            try:
                conn.close()
            except:
                pass

    def _update_signal_recovery_status_v2(
        self, signal_id, all_failed_accounts, newly_recovered, current_success_accounts
    ):
        """
        更新信号的恢复状态（V2版本）

        - 新增的恢复账户加入 success_accounts
        - 从 failed_accounts 中移除已恢复的
        - 如果 failed_accounts 为空 → status='processed'
        """
        try:
            # 移除已恢复的账户
            remaining_failed = [
                acc
                for acc in all_failed_accounts
                if (
                    (
                        isinstance(acc, dict)
                        and acc.get("account_id") not in newly_recovered
                    )
                    or (isinstance(acc, int) and acc not in newly_recovered)
                )
            ]

            # 新的成功账户列表
            updated_success = list(set(current_success_accounts + newly_recovered))

            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                if not remaining_failed:
                    # 全部恢复：清除failed_accounts，status='processed'
                    cursor.execute(
                        """UPDATE g_signals 
                           SET status='processed',
                               success_accounts=%s,
                               failed_accounts=NULL,
                               last_update_time=NOW()
                           WHERE id=%s""",
                        (json.dumps(updated_success), signal_id),
                    )
                    logging.info(
                        f"✅ 信号 {signal_id} 全部恢复成功 "
                        f"(恢复了{len(newly_recovered)}个，"
                        f"累计成功{len(updated_success)}个)"
                    )
                else:
                    # 部分恢复：更新 failed_accounts 和 success_accounts
                    cursor.execute(
                        """UPDATE g_signals 
                           SET status='processing',
                               success_accounts=%s,
                               failed_accounts=%s,
                               last_update_time=NOW()
                           WHERE id=%s""",
                        (
                            json.dumps(updated_success),
                            json.dumps(remaining_failed),
                            signal_id,
                        ),
                    )
                    logging.info(
                        f"⚠️ 信号 {signal_id} 部分恢复 "
                        f"(本次恢复{len(newly_recovered)}个，"
                        f"累计成功{len(updated_success)}个，"
                        f"仍有{len(remaining_failed)}个失败)"
                    )

            conn.commit()
            conn.close()
        except Exception as e:
            logging.error(f"❌ 更新信号恢复状态失败: 信号={signal_id}, 错误={e}")

    # 其他方法保持不变（get_order_info, check_and_close_position 等）
    async def get_order_info(self, account_id: int, order_id: str):
        exchange = None  # ✅ 初始化为 None
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return None

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
            # ✅ 确保 exchange 被关闭，释放事件循环资源，避免并发冲突
            if exchange:
                try:
                    await exchange.close()
                    logging.debug(f"✅ 已关闭exchange: 账户={account_id}")
                except Exception as e:
                    logging.warning(f"⚠️ 关闭exchange失败: 账户={account_id}, {e}")
