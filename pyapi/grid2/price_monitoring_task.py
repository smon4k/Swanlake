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
        # ✅ 提高账户并发限制，支持更多账户同时处理（100个账户场景）
        self.account_semaphore = asyncio.Semaphore(50)  # 限制 50 个账户并发
        self.order_semaphore = asyncio.Semaphore(10)  # 订单查询并发限流
        self.market_precision_cache = {}  # 市场精度缓存

        # ⏱️ 超时配置
        self.account_check_timeout = 30.0  # 单个账户检查超时时间（秒）
        self.round_total_timeout = 90.0  # 整轮检查总超时时间（秒）

        # 🎯 优先级队列（方案3）
        self.priority_queue = PriorityAccountQueue()
        self.round_counter = 0  # 轮次计数器
        self.priority_update_interval = 8  # 每8轮更新一次优先级
        self.low_priority_check_interval = 8  # 低优先级账户每8轮检查一次

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
                    logging.info("📭 本轮无需检查的账户，跳过")
                    self.round_counter += 1
                    await asyncio.sleep(self.config.check_interval)
                    continue

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
                            logging.error(f"❌ 账户 {account_id} 检查异常: {e}")
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

        if not order_timestamp:
            # 没有时间戳信息，记录警告后跳过
            logging.warning(
                f"⚠️ 订单无timestamp字段: {account_id} {order['order_id']} {symbol}"
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
                    f"📝 订单刚创建 {time_diff_minutes:.1f}分钟，暂无持仓是正常的: "
                    f"{account_id} {order['order_id']} {symbol} {order['side']}"
                )
                return True
            else:
                # 订单创建超过阈值时间，还没有持仓，可能有问题
                logging.warning(
                    f"⚠️ 订单已创建 {time_diff_minutes:.1f}分钟但无持仓: "
                    f"{account_id} {order['order_id']} {symbol} {order['side']} 状态={state}"
                )

                # 进一步检查订单状态
                if state in ("filled", "partially_filled"):
                    # 已成交但无持仓，说明持仓被平掉了
                    logging.warning(
                        f"⚠️ 订单已成交但无持仓，可能已被平仓: {account_id} {order['order_id']}"
                    )
                    await self.db.update_order_by_id(
                        account_id, order_info["id"], {"status": state}
                    )
                    # 可选：取消该币种的所有订单
                    # await cancel_all_orders(self, exchange, account_id, symbol)
                else:
                    # 未成交但等待时间过长
                    logging.warning(
                        f"⚠️ 订单长时间未成交: {account_id} {order['order_id']}"
                    )

                return True

        except Exception as e:
            logging.error(f"⚠️ 解析订单时间失败: {account_id} {order['order_id']} - {e}")
            return True

    async def _safe_check_positions(self, account_id: int):
        """安全封装的账户检查（防止一个账户崩溃影响整体）"""
        # 检查账户是否正在被信号处理
        if account_id in self.busy_accounts:
            logging.debug(f"⏸️ 账户 {account_id} 正在被信号处理，跳过本次价格监控")
            return

        # 异常处理已在上层 limited_check_positions 中进行
        await self.check_positions(account_id)

    async def check_positions(self, account_id: int):
        """检查指定账户的持仓与订单（优化版本：缓存 + 并发）"""
        try:
            exchange = await get_exchange(self, account_id)
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
                logging.warning(f"⚠️ 账户 {account_id} 无未成交订单")
                return

            # --------------------------
            # 1. 缓存 symbol -> positions
            # --------------------------
            # ✅ 直接获取所有持仓，不再为每个 symbol 重复请求
            positions_dict = {}

            try:
                # ✅ 调用全局API限流器
                if self.api_limiter:
                    await self.api_limiter.check_and_wait()

                # 添加超时控制（10秒）
                all_positions = await asyncio.wait_for(
                    exchange.fetch_positions("", {"instType": "SWAP"}), timeout=10.0
                )
                # logging.info(f"🔍 账户 {account_id} 持仓数: {len(all_positions)}")

                # 分类整理：symbol => [pos1, pos2, ...]
                for pos in all_positions:
                    sym = pos["info"].get("instId")
                    if not sym:
                        continue
                    positions_dict.setdefault(sym, []).append(pos)

            except asyncio.TimeoutError:
                logging.error(f"⏱️ 获取所有持仓超时(10秒) {account_id}")
            except Exception as e:
                logging.error(f"⚠️ 获取所有持仓失败 {account_id}: {e}")

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
                            f"❌ 账户 {account_id} 订单 {order['order_id']} 查询失败: {e}"
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
            # 3. 遍历订单（逻辑不变）
            # --------------------------
            latest_fill_time = 0
            latest_order, executed_price, fill_date_time = None, None, None
            process_grid = False

            for order in open_orders:
                symbol = order["symbol"]
                order_info = order_infos.get(order["order_id"])
                positions = positions_dict.get(symbol, [])
                # logging.info(f"🔍 账户 {account_id} 持仓信息: {positions}")
                if not order_info:
                    continue

                # ⚡ 处理无持仓情况
                if not positions:
                    should_skip = await self._handle_no_position_order(
                        order, order_info, account_id, symbol, exchange
                    )
                    if should_skip:
                        continue

                state = order_info["info"]["state"]
                logging.info(
                    f"🔍 订单状态: {account_id} {order['order_id']} {symbol} {order['side']} {state}"
                )
                if state == "canceled":
                    await self.db.update_order_by_id(
                        account_id, order_info["id"], {"status": state}
                    )
                    continue

                elif state in ("filled", "partially_filled"):
                    if state == "partially_filled":
                        total_amount = Decimal(order_info["amount"])
                        filled_amount = Decimal(order_info["filled"])
                        if filled_amount < total_amount * Decimal("0.7"):
                            continue

                    fill_time = float(order_info["info"].get("fillTime", 0))
                    if fill_time > latest_fill_time:
                        latest_fill_time = fill_time
                        latest_order = order_info
                        executed_price = order_info["info"]["fillPx"]
                        fill_date_time = await milliseconds_to_local_datetime(fill_time)
                        process_grid = True

            # ✅ 后续逻辑不变
            if process_grid and latest_order:
                # symbol = latest_order['symbol']
                logging.info(
                    f"✅ 订单已成交: 用户={account_id}, 币种={symbol}, 方向={latest_order['side']}, 价格={executed_price}"
                )
                managed = await self.manage_grid_orders(latest_order, account_id)
                if managed:
                    await self.db.update_order_by_id(
                        account_id,
                        latest_order["id"],
                        {
                            "executed_price": executed_price,
                            "status": "filled",
                            "fill_time": fill_date_time,
                        },
                    )
                    await self.update_order_status(
                        latest_order, account_id, executed_price, fill_date_time, symbol
                    )
                    await self.stop_loss_task.accounts_stop_loss_task(account_id)

        except Exception as e:
            logging.error(f"❌ 账户 {account_id} 检查持仓失败: {e}", exc_info=True)
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
                return

            print("🔄 开始匹配订单")
            logging.info(f"🔄 账户 {account_id} 开始匹配订单")

            side = "sell" if order["side"] == "buy" else "buy"
            matched_order = await self.db.get_order_by_price_diff_v2(
                account_id, order["info"]["instId"], executed_price, side
            )
            logging.info(
                f"配对订单: {matched_order['order_id'] if matched_order else '无'}"
            )

            profit = 0
            group_id = ""
            market_precision = await get_market_precision(self, exchange, symbol)

            if matched_order:
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
                    print(f"📊 用户 {account_id} 配对利润 (buy): {profit}")
                    logging.info(f"📊 用户 {account_id} 配对利润 (buy): {profit}")

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
                    print(f"📊 配对利润 用户 {account_id} (sell): {profit}")
                    logging.info(f"📊 用户 {account_id} 配对利润 (sell): {profit}")

                if profit != 0:
                    group_id = str(uuid.uuid4())
                    await self.db.update_order_by_id(
                        account_id,
                        matched_order["order_id"],
                        {"profit": profit, "position_group_id": group_id},
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
            print(f"❌ 配对利润计算失败: {e}")
            logging.error(f"❌ 配对利润计算失败: {e}")
        finally:
            if exchange:
                await exchange.close()

    async def manage_grid_orders(self, order: dict, account_id: int):
        """网格订单管理（逻辑不变，仅优化并发安全性）"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                print("❌ 未找到交易所实例")
                logging.error("❌ 未找到交易所实例")
                return False

            symbol = order["info"]["instId"]
            filled_price = Decimal(order["info"]["fillPx"])
            print(f"📌 用户 {account_id} 最新订单成交价: {filled_price}")
            logging.info(f"📌 用户 {account_id} 最新订单成交价: {filled_price}")

            price = await get_market_price(exchange, symbol)
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
                print("🚫 网格下单：无持仓")
                return True

            total_position_value = await get_total_positions(
                self, account_id, symbol, "SWAP"
            )
            if total_position_value <= 0:
                return True

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
                print(f"🚫 未找到策略: {account_id} {symbol_tactics}")
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
            await cancel_all_orders(self, exchange, account_id, symbol)

            percent_list = await get_grid_percent_list(
                self, account_id, signal["direction"]
            )
            buy_percent = percent_list.get("buy")
            sell_percent = percent_list.get("sell")

            buy_size = (total_position_value * Decimal(str(buy_percent))).quantize(
                Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
            )
            if buy_size < market_precision["min_amount"]:
                print(f"📉 用户 {account_id} 买单过小: {buy_size}")
                logging.info(f"📉 用户 {account_id} 买单过小: {buy_size}")
                return False

            sell_size = (total_position_value * Decimal(str(sell_percent))).quantize(
                Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
            )
            if sell_size < market_precision["min_amount"]:
                print(f"📉 用户 {account_id} 卖单过小: {sell_size}")
                logging.info(f"📉 用户 {account_id} 卖单过小: {sell_size}")
                return False

            max_position = await get_max_position_value(self, account_id, symbol)
            buy_total = (
                total_position_quantity
                + buy_size * market_precision["amount"] * buy_price
                - sell_size * market_precision["amount"] * sell_price
            )
            if buy_total >= max_position:
                print(f"⚠️ 用户 {account_id} 超过最大持仓，取消挂单")
                logging.info(f"⚠️ 用户 {account_id} 超过最大持仓，取消挂单")
                return False

            group_id = str(uuid.uuid4())
            pos_side = "long"
            if side == "buy" and signal["size"] == 1:  # 开多
                pos_side = "long"
            if side == "sell" and signal["size"] == -1:  # 开空
                pos_side = "short"
            # print("📈 开仓方向:", pos_side)

            buy_order = None
            sell_order = None

            buy_client_order_id = ""
            sell_client_order_id = ""
            if buy_size > 0:
                buy_client_order_id = await get_client_order_id()
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
                print(
                    f"✅ 用户 {account_id} 已挂单: 买{buy_price}({buy_size}) 卖{sell_price}({sell_size})"
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
            # print(f"❌ 网格管理失败: {e}")
            logging.error(f"❌ 用户 {account_id} 网格管理失败: {e}")
            traceback.print_exc()
            return False
        finally:
            await exchange.close()

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
