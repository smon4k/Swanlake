import asyncio
from decimal import Decimal
from datetime import datetime, timedelta
import logging
from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import (
    get_exchange,
    get_market_price,
    get_market_precision,
    milliseconds_to_local_datetime,
    get_client_order_id,
    fetch_positions_with_retry,
    fetch_order_with_retry,
)


class StopLossTask:
    """交易信号处理类"""

    def __init__(
        self,
        config: TradingBotConfig,
        db: Database,
        signal_lock: asyncio.Lock,
        api_limiter=None,
        account_locks=None,
        busy_accounts=None,
        signal_processing_active: asyncio.Event = None,  # ✅ 新增参数
    ):
        self.db = db
        self.config = config
        self.running = True
        self.signal_lock = signal_lock
        self.api_limiter = api_limiter  # 全局API限流器
        self.market_precision_cache = {}  # 市场精度缓存
        self.account_locks = account_locks  # 账户锁字典
        self.busy_accounts = busy_accounts  # 忙碌账户集合

        # ✅ 【新增】任务协调标志
        self.signal_processing_active = signal_processing_active

        # ✅ 止损任务去重相关
        self.checking_accounts = set()  # 正在检查止损的账户
        self.last_check_time = {}  # 每个账户的上次检查时间
        self.min_check_interval = 10  # 最小检查间隔（秒），避免频繁重复检查

    async def stop_loss_task(self):
        """价格监控任务（分批并发版本，减少API调用峰值）"""
        while getattr(self, "running", True):
            try:
                account_ids = list(self.db.account_cache.keys())

                # ✅ 分批执行，每批3个账户
                batch_size = 3
                for i in range(0, len(account_ids), batch_size):
                    batch = account_ids[i : i + batch_size]

                    # 批内并发（3个账户同时检查）
                    tasks = [
                        asyncio.create_task(self.accounts_stop_loss_task(aid))
                        for aid in batch
                    ]
                    await asyncio.gather(*tasks, return_exceptions=True)

                    # 批次间延迟（2秒），分散API调用时间
                    if i + batch_size < len(account_ids):  # 不是最后一批
                        await asyncio.sleep(2.0)

                    # 每处理完一批，检查是否还在运行
                    if not getattr(self, "running", True):
                        break

                await asyncio.sleep(300)  # 每5分钟检查一次
            except Exception as e:
                print(f"价格监控异常: {e}")
                logging.error(f"价格监控异常: {e}")
                await asyncio.sleep(5)

    # 检查单个账户的止损
    async def accounts_stop_loss_task(self, account_id: int, immediate: bool = False):
        """检查单个账户的止损（带去重和账户锁保护，防止重复创建）

        Args:
            account_id: 账户ID
            immediate: 是否立即执行（True时绕过时间间隔检查，用于订单成交后立即触发）
        """
        from leader_copy_task import skip_stop_loss_grid_for_account

        if skip_stop_loss_grid_for_account(account_id):
            logging.info(
                f"⏭️ 账户 {account_id} 为跟单账户，跳过自动止损检查"
            )
            return

        # ✅ 【新增】优先级检查：如果信号处理正在进行且非立即执行，则推迟
        if self.signal_processing_active and not immediate:
            if self.signal_processing_active.is_set():
                logging.info(f"⏸️ 账户 {account_id} 止损检查推迟，当前信号处理优先")
                return

        # ✅ 去重检查1：如果该账户正在检查中，直接返回
        if account_id in self.checking_accounts:
            logging.debug(f"⏸️ 账户 {account_id} 止损检查正在进行中，跳过重复触发")
            return

        # ✅ 去重检查2：检查时间间隔（仅非紧急情况）
        if not immediate:
            import time

            last_check = self.last_check_time.get(account_id, 0)
            elapsed = time.time() - last_check
            if elapsed < self.min_check_interval:
                logging.debug(
                    f"⏸️ 账户 {account_id} 距离上次检查仅 {elapsed:.1f}秒，跳过（最小间隔{self.min_check_interval}秒）"
                )
                return

        # ✅ 关键日志：进入实际检查
        logging.info(
            f"🛡️ 账户 {account_id} 开始进入止损检查流程 (immediate={immediate})"
        )

        # ✅ 标记为检查中
        self.checking_accounts.add(account_id)

        try:
            # 🔐 添加账户锁保护，防止与信号处理任务冲突
            lock = self.account_locks.get(account_id) if self.account_locks else None

            if lock:
                # 检查锁是否被占用
                if lock.locked():
                    logging.warning(
                        f"⏸️ 账户 {account_id} 锁被占用（可能在处理信号），跳过本次止损检查"
                    )
                    return

                # 获取锁并执行检查
                async with lock:
                    # 再次检查账户是否正在被信号处理占用
                    if self.busy_accounts and account_id in self.busy_accounts:
                        logging.warning(
                            f"⏸️ 账户 {account_id} 正在busy_accounts集合中（正在处理信号）- busy_accounts={self.busy_accounts}，跳过本次止损检查"
                        )
                        return

                    # ✅ 关键日志：真正进入检查逻辑
                    logging.info(f"✅ 账户 {account_id} 进入 _do_stop_loss_check 逻辑")

                    # ✅ 【新增】执行实际的止损检查（带重试机制）
                    await self._do_stop_loss_check_with_retry(account_id)
            else:
                # 无锁情况下直接执行（向后兼容）
                logging.debug(f"⚠️ 账户 {account_id} 无锁保护，直接执行止损检查")
                await self._do_stop_loss_check_with_retry(account_id)

            # ✅ 更新最后检查时间
            import time

            self.last_check_time[account_id] = time.time()

        finally:
            # ✅ 移除检查中标记
            self.checking_accounts.discard(account_id)

    async def _do_stop_loss_check_with_retry(
        self, account_id: int, max_retries: int = 3
    ):
        """
        ✅ 【新增方法】带重试机制的止损检查包装

        如果 _do_stop_loss_check 超时，会自动重试最多 max_retries 次

        :param account_id: 账户ID
        :param max_retries: 最大重试次数
        """
        retry_delay = 5.0  # 重试延迟（秒）

        for attempt in range(max_retries):
            try:
                # 调用实际的止损检查
                await self._do_stop_loss_check(account_id)
                return  # 成功则返回

            except asyncio.TimeoutError:
                if attempt < max_retries - 1:
                    logging.warning(
                        f"⚠️ 账户 {account_id} 止损检查超时，"
                        f"{retry_delay}秒后重试 ({attempt + 1}/{max_retries})"
                    )
                    await asyncio.sleep(retry_delay)
                else:
                    logging.error(
                        f"❌ 账户 {account_id} 止损检查超时，已重试 {max_retries} 次，放弃"
                    )
                    raise

            except Exception as e:
                # 其他异常不重试，直接抛出
                logging.error(f"❌ 账户 {account_id} 止损检查失败: {e}", exc_info=True)
                raise

    async def _do_stop_loss_check(self, account_id: int):
        """实际的止损检查逻辑（从 accounts_stop_loss_task 中提取）"""
        exchange = None  # ✅ 在 try 外部初始化，确保 finally 块能访问

        # ✅ 【修改】增加超时限制：从30秒增加到90秒（使用wait_for兼容Python 3.7+）
        try:
            await asyncio.wait_for(
                self._do_stop_loss_check_impl(account_id), timeout=90.0
            )
        except asyncio.TimeoutError:
            logging.error(
                f"⏰ 账户 {account_id} 止损检查超时(90秒)，可能由于API限流或网络延迟"
            )
            raise  # 重新抛出，让外层重试机制处理

    async def _do_stop_loss_check_impl(self, account_id: int):
        """实际的止损检查逻辑实现"""
        exchange = None  # ✅ 在 try 外部初始化，确保 finally 块能访问
        try:
            # print(f"🛡️ 开始检查止损: 账户={account_id}")
            logging.info(f"🛡️ 开始检查止损: 账户={account_id}")
            exchange = await get_exchange(self, account_id)
            if not exchange:
                logging.error(
                    f"❌ 止损检查失败：无法获取交易所实例 - 账户={account_id}"
                )
                return

            # ✅ 使用带重试机制的持仓查询（自动处理API限流）
            all_positions = await fetch_positions_with_retry(
                exchange=exchange,
                account_id=account_id,
                symbol=None,
                params={"instType": "SWAP"},
                api_limiter=self.api_limiter,
            )

            # ✅ 如果重试3次后仍失败，跳过该账户，等待下次检查（不影响其他账户）
            if all_positions is None:
                logging.warning(
                    f"⏸️ 账户 {account_id} 获取持仓失败（已重试3次），跳过本次止损检查，等待下次"
                )
                return

            # ✅ 创建持仓缓存字典，按 symbol 分类（供后续复用，避免重复查询）
            positions_cache = {}
            for pos in all_positions:
                symbol_key = pos["symbol"]
                positions_cache.setdefault(symbol_key, []).append(pos)

            # 统计有持仓的币种
            position_count = sum(1 for pos in all_positions if pos["contracts"] != 0)
            if position_count > 0:
                logging.info(
                    f"📊 账户 {account_id} 检查到 {position_count} 个持仓需要止损保护"
                )
            else:
                logging.info(f"📊 账户 {account_id} 无持仓，跳过止损检查")
                return

            for pos in all_positions:
                if pos["contracts"] != 0:
                    symbol = pos["symbol"]
                    side = "buy" if pos["side"] == "long" else "sell"
                    entry_price = float(pos["entryPrice"])
                    mark_price = float(pos["markPrice"])
                    amount = abs(float(pos["contracts"]))
                    symbol_tactics = pos["info"]["instId"]
                    if symbol_tactics.endswith("-SWAP"):
                        symbol_tactics = symbol_tactics.replace("-SWAP", "")
                    full_symbol = symbol_tactics + "-SWAP"
                    tactics = await self.db.get_tactics_by_account_and_symbol(
                        account_id, symbol_tactics
                    )  # 获取账户币种策略配置名称
                    if not tactics:
                        # print(f"未找到策略配置: {account_id} {symbol_tactics}")
                        logging.info(f"未找到策略配置: {account_id} {symbol_tactics}")
                        return False
                    # 计算止损价
                    strategy_info = await self.db.get_strategy_info(tactics)
                    stop_loss_percent = float(
                        strategy_info.get("stop_loss_percent") or 0.458
                    )
                    stop_loss_price = (
                        entry_price * (1 - stop_loss_percent / 100)
                        if side == "buy"
                        else entry_price * (1 + stop_loss_percent / 100)
                    )  # 止损价 做多时更低，做空时更高

                    # ✅ 验证止损价是否符合OKX规则
                    if side == "buy":  # 做多持仓
                        # 止损价必须 < 当前市价
                        if stop_loss_price >= mark_price:
                            old_price = stop_loss_price
                            # 调整为市价的 99.9%（留一点余量）
                            stop_loss_price = mark_price * 0.999
                            logging.warning(
                                f"⚠️ 用户 {account_id} 做多止损价不符合规则: 原始={old_price:.2f}, 市价={mark_price:.2f}, 调整后={stop_loss_price:.2f}"
                            )
                    else:  # 做空持仓
                        # 止损价必须 > 当前市价
                        if stop_loss_price <= mark_price:
                            old_price = stop_loss_price
                            # 调整为市价的 100.1%（留一点余量）
                            stop_loss_price = mark_price * 1.001
                            logging.warning(
                                f"⚠️ 用户 {account_id} 做空止损价不符合规则: 原始={old_price:.2f}, 市价={mark_price:.2f}, 调整后={stop_loss_price:.2f}"
                            )

                    # pos_side = 'short' if pos['side'] == 'long' else 'long'  # 持仓方向与开仓方向相反
                    pos_side = pos["side"]  # 持仓方向与开仓方向相反
                    sl_side = (
                        "sell" if side == "buy" else "buy"
                    )  # 止损单方向与持仓方向相反

                    order_sl_order = await self.db.get_unclosed_orders(
                        account_id, full_symbol, "conditional"
                    )
                    # 启用调试日志以便排查问题
                    logging.info(
                        f"📊 检查止损单: 用户={account_id}, 币种={symbol}, 方向={side}, 入场价={entry_price:.2f}, "
                        f"市价={mark_price:.2f}, 止损价={stop_loss_price:.2f}, 数量={amount}, "
                        f"已有止损单={'存在(ID:' + order_sl_order.get('order_id', 'N/A')[:15] + '...)' if order_sl_order else '无'}"
                    )
                    if order_sl_order:
                        try:
                            # 先判断是否已经成交或者取消
                            logging.info(
                                f"🔍 查询止损单状态: 账户={account_id}, "
                                f"订单ID={order_sl_order['order_id'][:15]}..."
                            )
                            # ✅ 使用带重试机制的订单查询（自动处理API限流）
                            order_info = await fetch_order_with_retry(
                                exchange=exchange,
                                account_id=account_id,
                                order_id=order_sl_order["order_id"],
                                symbol=symbol,
                                params={"instType": "SWAP", "trigger": "true"},
                                api_limiter=self.api_limiter,
                            )

                            # ✅ 如果重试3次后仍失败，跳过该止损单检查（继续处理其他持仓）
                            if order_info is None:
                                logging.warning(
                                    f"⏸️ 账户 {account_id} 查询止损单失败（已重试3次），跳过该止损单，继续检查其他持仓"
                                )
                                continue  # 跳过该持仓，继续下一个

                            order_state = order_info["info"]["state"]
                            logging.info(
                                f"📊 止损单状态: 账户={account_id}, "
                                f"订单={order_sl_order['order_id'][:15]}..., 状态={order_state}"
                            )
                            if order_state in [
                                "pause",
                                "effective",
                                "canceled",
                                "order_failed",
                                "partially_failed",
                            ]:
                                logging.warning(
                                    f"⚠️ 止损单状态异常: 账户={account_id}, "
                                    f"订单={order_sl_order.get('order_id')[:15]}..., "
                                    f"状态={order_info['info']['state']}, 币种={symbol}"
                                )
                                # print(
                                #     f"已有止损单状态为 {account_id} {order_info['info']['state']}, 更新数据库状态: {symbol} {str(order_sl_order.get('order_id'))}"
                                # )

                                fill_date_time = await milliseconds_to_local_datetime(
                                    order_info["lastUpdateTimestamp"]
                                )  # 格式化成交时间

                                # 如果止损单状态是 effective（已触发），检查持仓是否已被平掉
                                final_status = order_info["info"]["state"]
                                if order_state == "effective":
                                    # ✅ 使用缓存的持仓数据，避免重复API调用
                                    try:
                                        symbol_positions_check = [
                                            p
                                            for p in positions_cache.get(symbol, [])
                                            if p["contracts"] != 0
                                        ]
                                        # 如果当前无持仓，说明止损单已生效，更新状态为 filled
                                        if not symbol_positions_check:
                                            final_status = "filled"
                                            logging.info(
                                                f"✅ 止损单已生效（持仓已平）: 账户={account_id}, "
                                                f"订单={order_sl_order['order_id'][:15]}..., 币种={symbol}"
                                            )
                                    except Exception as e:
                                        logging.warning(
                                            f"⚠️ 检查持仓失败，使用原始状态: 账户={account_id}, 错误={e}"
                                        )

                                logging.info(
                                    f"📝 更新止损单状态: 账户={account_id}, "
                                    f"订单={order_sl_order.get('order_id')[:15]}..., "
                                    f"原始状态={order_info['info']['state']}, 最终状态={final_status}, "
                                    f"触发价={order_info['info'].get('slTriggerPx', 'N/A')}, "
                                    f"更新时间={fill_date_time}"
                                )

                                # 更新数据库状态
                                try:
                                    update_data = {
                                        "status": final_status,
                                        "executed_price": float(
                                            order_info["info"]["slTriggerPx"]
                                        ),
                                        "fill_time": fill_date_time,
                                    }
                                    await self.db.update_order_by_id(
                                        account_id,
                                        order_sl_order["order_id"],
                                        update_data,
                                    )
                                    logging.info(
                                        f"✅ 止损单状态已更新: 账户={account_id}, "
                                        f"订单={order_sl_order['order_id'][:15]}..., "
                                        f"状态={final_status}"
                                    )
                                except Exception as e:
                                    logging.error(
                                        f"❌ 更新止损单状态失败: 账户={account_id}, "
                                        f"订单={order_sl_order['order_id'][:15]}..., "
                                        f"错误={e}",
                                        exc_info=True,
                                    )
                                    # 即使更新失败，也继续后续流程

                                # 🔐 使用账户锁，防止与新信号处理冲突
                                lock = (
                                    self.account_locks.get(account_id)
                                    if self.account_locks
                                    else None
                                )
                                if lock:
                                    async with lock:
                                        # 检查账户是否正在被信号处理
                                        if (
                                            self.busy_accounts
                                            and account_id in self.busy_accounts
                                        ):
                                            logging.info(
                                                f"⏸️ 账户 {account_id} 正在处理信号，跳过撤销挂单"
                                            )
                                        else:
                                            # ✅ 使用缓存的持仓数据，避免重复API调用
                                            try:
                                                symbol_positions = [
                                                    p
                                                    for p in positions_cache.get(
                                                        symbol, []
                                                    )
                                                    if p["contracts"] != 0
                                                ]

                                                if not symbol_positions:
                                                    # 无持仓，撤销反向的旧网格挂单
                                                    await self._cancel_opposite_orders(
                                                        account_id,
                                                        exchange,
                                                        full_symbol,
                                                        symbol,
                                                        sl_side,
                                                    )
                                            except Exception as e:
                                                logging.error(
                                                    f"❌ 检查持仓或撤销订单失败: 账户={account_id}, 错误={e}"
                                                )

                                logging.info(
                                    f"🔄 准备重新创建止损单: 账户={account_id}, 币种={full_symbol}"
                                )
                                await self._open_position(
                                    account_id,
                                    full_symbol,
                                    sl_side,
                                    amount,
                                    stop_loss_price,
                                    pos_side,
                                )
                            else:
                                # 如果止损单存在，且状态是 live 或者 partially_effective，则修改止损单
                                logging.info(
                                    f"🔄 准备修改止损单: 账户={account_id}, 币种={symbol}, "
                                    f"订单={order_sl_order.get('order_id')[:15]}..., "
                                    f"当前状态={order_state}, 新止损价={stop_loss_price:.2f}, 新数量={amount}"
                                )
                                print(
                                    f"已有未完成止损单，更新: {account_id} {symbol} {str(order_sl_order.get('order_id')[:15])}..."
                                )

                                await self._amend_algos_order(
                                    account_id,
                                    order_sl_order["order_id"],
                                    full_symbol,
                                    sl_side,
                                    amount,
                                    stop_loss_price,
                                    pos_side,
                                )
                        except Exception as e:
                            error_msg = str(e)
                            # ✅ 如果订单不存在（已被交易所删除或过期）
                            if (
                                "51603" in error_msg
                                or "Order does not exist" in error_msg
                            ):
                                logging.warning(
                                    f"⚠️ 订单不存在，标记为取消并创建新止损单: {account_id} {order_sl_order['order_id']}"
                                )
                                # 更新数据库状态为取消
                                await self.db.update_order_by_id(
                                    account_id,
                                    order_sl_order["order_id"],
                                    {"status": "canceled"},
                                )
                                # 重新创建止损单
                                await self._open_position(
                                    account_id,
                                    full_symbol,
                                    sl_side,
                                    amount,
                                    stop_loss_price,
                                    pos_side,
                                )
                            else:
                                logging.error(f"❌ 查询止损单失败: {account_id} {e}")
                                raise
                    else:
                        logging.info(
                            f"📝 无止损单，准备创建: 账户={account_id}, 方向={side}, "
                            f"币种={symbol}, 持仓均价={entry_price:.2f}, "
                            f"市价={mark_price:.2f}, 止损价={stop_loss_price:.2f}, 数量={amount}"
                        )
                        print(
                            f"持仓方向: {account_id} {side}, 交易对: {symbol}, 持仓均价: {entry_price}, 最新标记价格: {mark_price}"
                        )

                        await self._open_position(
                            account_id,
                            full_symbol,
                            sl_side,
                            amount,
                            stop_loss_price,
                            pos_side,
                        )
        except Exception as e:
            logging.error(
                f"❌ 止损任务失败: 账户={account_id}, 错误={e}", exc_info=True
            )
            # print(f"止损任务失败: {e}")
            return False
        finally:
            # ✅ 确保 exchange 被关闭，释放事件循环资源，避免并发冲突
            if exchange:
                try:
                    await exchange.close()
                    logging.debug(f"✅ 已关闭exchange: 账户={account_id}")
                except Exception as e:
                    logging.warning(f"⚠️ 关闭exchange失败: 账户={account_id}, {e}")

    # 下策略委托单
    async def _open_position(
        self,
        account_id: int,
        full_symbol: str,
        side: str,
        amount: float,
        price: float,
        pos_side: str,
    ):
        try:
            exchange = await get_exchange(self, account_id)
            market_precision = await get_market_precision(
                self, exchange, full_symbol
            )  # 获取市场精度

            # 处理数量精度 - 更安全的方法
            try:
                # 获取最小交易量
                min_amount = float(market_precision.get("min_amount", 0.001))

                # 确保数量不小于最小交易量
                if amount < min_amount:
                    print(f"数量 {amount} 小于最小交易量 {min_amount}，无法下单")
                    logging.warning(
                        f"数量 {amount} 小于最小交易量 {min_amount}，无法下单"
                    )
                    return None

                # 处理精度 - 使用更安全的方法
                amount_precision = int(
                    -Decimal(str(market_precision["amount"])).as_tuple().exponent
                )
                amount = round(amount, amount_precision)

                # print(f"止损单: {full_symbol}, {side}, {amount}, 止损价: {price}, 精度: {amount_precision}")

            except Exception as e:
                print(f"处理数量精度时出错: {e}")
                logging.error(f"处理数量精度时出错: {e}")
                # 使用默认精度
                amount = round(amount, 8)
                # print(f"使用默认精度: {amount}")

            client_order_id = await get_client_order_id("SL")

            # 先获取市场价格进行验证
            symbol_tactics = full_symbol.replace("-SWAP", "")
            market_price = await get_market_price(
                exchange, symbol_tactics, self.api_limiter, close_exchange=False
            )  # 获取最新市场价格（不关闭exchange，由调用方管理）

            # ✅ 如果获取市场价格失败（返回0），跳过止损单创建，避免使用错误价格
            if market_price == Decimal("0"):
                logging.error(
                    f"❌ 账户 {account_id} 无法获取市场价格（已重试3次），跳过止损单创建: {full_symbol}"
                )
                return None

            # ✅ 验证止损价是否符合OKX规则
            original_price = price
            if side == "sell":  # 做多持仓，止损是卖单
                # 止损价必须 < 当前市价
                if price >= float(market_price):
                    price = float(market_price) * 0.999
                    logging.warning(
                        f"⚠️ 用户 {account_id} 创建做多止损单，价格不符合规则: 原始={original_price:.2f}, 市价={float(market_price):.2f}, 调整后={price:.2f}"
                    )
            else:  # 做空持仓，止损是买单
                # 止损价必须 > 当前市价
                if price <= float(market_price):
                    price = float(market_price) * 1.001
                    logging.warning(
                        f"⚠️ 用户 {account_id} 创建做空止损单，价格不符合规则: 原始={original_price:.2f}, 市价={float(market_price):.2f}, 调整后={price:.2f}"
                    )

            logging.info(
                f"📝 创建止损单: 用户={account_id}, 币种={full_symbol}, 方向={side}, 数量={amount}, 止损价={price:.2f}, 市价={float(market_price):.2f}"
            )

            # 🔒 二次确认：创建前再次检查是否已有止损单（双重检查，防止并发重复创建）
            double_check_order = await self.db.get_unclosed_orders(
                account_id, full_symbol, "conditional"
            )
            if double_check_order:
                logging.warning(
                    f"⚠️ 二次检查发现已有止损单，取消创建: 账户={account_id}, "
                    f"币种={full_symbol}, 已有订单ID={double_check_order['order_id'][:15]}..."
                )
                return None

            params = {
                "posSide": pos_side,  # 持仓方向
                "attachAlgoClOrdId": client_order_id,  # 客户端订单ID
                "slTriggerPx": str(price),  # 止损触发价（已验证）
                "slTriggerPxType": "last",  # 止损触发价类型
                "slOrdPx": "-1",  # 止损委托价 -1表示市价
                "cxlOnClosePos": True,  # 平仓时取消订单
                "reduceOnly": True,  # 仅减仓
            }

            # 创建止损订单
            order = await exchange.create_order(
                symbol=full_symbol,
                type="conditional",
                side=side,
                amount=amount,
                price=market_price,
                params=params,
            )

            if order and order.get("info", {}).get("sCode") == "0":
                logging.info(
                    f"✅ 止损单创建成功: 账户={account_id}, 订单ID={order['id'][:15]}..., "
                    f"币种={full_symbol}, 方向={side}, 止损价={price:.2f}, 数量={amount}"
                )
                # print(f"止损单创建成功: {account_id} {order['id']}")
                await self.db.add_order(
                    {
                        "account_id": account_id,
                        "symbol": full_symbol,
                        "order_id": order["id"],
                        "clorder_id": client_order_id,
                        "price": float(price),  # 止损触发价
                        "executed_price": None,
                        "quantity": float(amount),
                        "pos_side": pos_side,
                        "order_type": "conditional",
                        "side": side,
                        "status": "live",
                        "position_group_id": "",
                    }
                )
                return order
            else:
                error_msg = (
                    order.get("info", {}).get("sMsg", "未知错误")
                    if order
                    else "订单创建失败"
                )
                error_code = (
                    order.get("info", {}).get("sCode", "N/A") if order else "N/A"
                )
                logging.error(
                    f"❌ 止损单创建失败: 账户={account_id}, 币种={full_symbol}, "
                    f"错误码={error_code}, 错误信息={error_msg}"
                )
                print(f"用户{account_id} 下策略单失败: {error_msg}")
                return None

        except Exception as e:
            logging.error(
                f"❌ 止损单创建异常: 账户={account_id}, 币种={full_symbol}, "
                f"方向={side}, 错误={e}",
                exc_info=True,
            )
            print(f"用户{account_id} 下策略单失败 error: {e}")
            return None
        finally:
            await exchange.close()

    # 修改委托订单
    async def _amend_algos_order(
        self,
        account_id: int,
        algo_order_id: str,
        symbol: str,
        side: str,
        amount: float,
        price: float,
        pos_side: str,
    ):
        try:
            exchange = await get_exchange(self, account_id)
            market_precision = await get_market_precision(
                self, exchange, symbol
            )  # 获取市场精度
            # 处理数量精度 - 更安全的方法
            try:
                # 获取最小交易量
                min_amount = float(market_precision.get("min_amount", 0.001))
                # 确保数量不小于最小交易量
                if amount < min_amount:
                    print(f"数量 {amount} 小于最小交易量 {min_amount}，无法下单")
                    logging.warning(
                        f"数量 {amount} 小于最小交易量 {min_amount}，无法下单"
                    )
                    return None

                # 处理精度 - 使用更安全的方法
                amount_precision = int(
                    -Decimal(str(market_precision["amount"])).as_tuple().exponent
                )
                amount = round(amount, amount_precision)

            except Exception as e:
                print(f"处理数量精度时出错: {e}")
                logging.error(f"处理数量精度时出错: {e}")
                # 使用默认精度
                amount = round(amount, 8)
                # print(f"使用默认精度: {amount}")

            market_price = await get_market_price(
                exchange, symbol, self.api_limiter, close_exchange=False
            )  # 获取最新市场价格（不关闭exchange，由调用方管理）

            # ✅ 如果获取市场价格失败（返回0），跳过止损单修改，避免使用错误价格
            if market_price == Decimal("0"):
                logging.error(
                    f"❌ 账户 {account_id} 无法获取市场价格（已重试3次），跳过止损单修改: {symbol}"
                )
                return None

            # ✅ 验证修改后的止损价是否符合OKX规则
            original_price = price
            if side == "sell":  # 做多持仓，止损是卖单
                # 止损价必须 < 当前市价
                if price >= float(market_price):
                    price = float(market_price) * 0.999
                    logging.warning(
                        f"⚠️ 用户 {account_id} 修改做多止损价不符合规则: 原始={original_price:.2f}, 市价={float(market_price):.2f}, 调整后={price:.2f}"
                    )
            else:  # 做空持仓，止损是买单
                # 止损价必须 > 当前市价
                if price <= float(market_price):
                    price = float(market_price) * 1.001
                    logging.warning(
                        f"⚠️ 用户 {account_id} 修改做空止损价不符合规则: 原始={original_price:.2f}, 市价={float(market_price):.2f}, 调整后={price:.2f}"
                    )

            logging.info(
                f"📝 修改止损单: 用户={account_id}, 币种={symbol}, 方向={side}, 数量={amount}, 新止损价={price:.2f}, 市价={float(market_price):.2f}"
            )

            params = {
                "newSlTriggerPx": str(price),  # 止损触发价
                "newSlOrdPx": "-1",  # 新止损委托价 -1表示市价
                "cxlOnFail": True,  # 平仓时取消订单
            }
            # 创建止损订单
            edit_order = await exchange.edit_order(
                id=algo_order_id,
                symbol=symbol,
                type="conditional",
                side=side,
                amount=amount,
                price=market_price,
                params=params,
            )

            if edit_order and edit_order.get("info", {}).get("sCode") == "0":
                logging.info(
                    f"✅ 止损单修改成功: 账户={account_id}, 订单ID={edit_order['id'][:15]}..., "
                    f"币种={symbol}, 新止损价={price:.2f}, 新数量={amount}"
                )
                print(f"修改止损单成功: {account_id} {edit_order['id']}")
                # fill_date_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                await self.db.update_order_by_id(
                    account_id,
                    algo_order_id,
                    {"price": float(price), "quantity": float(amount)},
                )
                return edit_order
            else:
                error_msg = (
                    edit_order.get("info", {}).get("sMsg", "未知错误")
                    if edit_order
                    else "订单修改失败"
                )
                error_code = (
                    edit_order.get("info", {}).get("sCode", "N/A")
                    if edit_order
                    else "N/A"
                )
                logging.error(
                    f"❌ 止损单修改失败: 账户={account_id}, 订单={algo_order_id[:15]}..., "
                    f"错误码={error_code}, 错误信息={error_msg}"
                )
                return None
        except Exception as e:
            logging.error(
                f"❌ 止损单修改异常: 账户={account_id}, 订单={algo_order_id[:15]}..., "
                f"币种={symbol}, 错误={e}",
                exc_info=True,
            )
            print(f"修改止损单失败: {account_id} {e}")
            return None
        finally:
            await exchange.close()

    async def _cancel_opposite_orders(
        self,
        account_id: int,
        exchange,
        full_symbol: str,
        symbol: str,
        stop_loss_side: str,
    ):
        """
        撤销反向的旧网格挂单

        Args:
            account_id: 账户ID
            exchange: 交易所实例
            full_symbol: 完整交易对（如 BTC-USDT-SWAP）
            symbol: 交易对（如 BTC/USDT:USDT）
            stop_loss_side: 止损单方向（buy/sell）
        """
        try:
            # 1. 查询该币种的所有 limit 挂单
            pending_orders = await self.db.get_active_orders(account_id)
            if not pending_orders:
                return

            symbol_orders = [
                o
                for o in pending_orders
                if o["symbol"] == full_symbol and o["order_type"] == "limit"
            ]

            if not symbol_orders:
                logging.debug(f"📭 账户 {account_id} 币种 {full_symbol} 无 limit 挂单")
                return

            # 2. 找到反方向的订单
            opposite_side = "sell" if stop_loss_side == "buy" else "buy"
            opposite_orders = [o for o in symbol_orders if o["side"] == opposite_side]

            if not opposite_orders:
                logging.debug(f"📭 账户 {account_id} 币种 {full_symbol} 无反向挂单")
                return

            # 3. 检查订单时间戳，只撤销"旧的"网格挂单（创建时间 > 5分钟）
            now = datetime.now()
            time_threshold = timedelta(minutes=5)
            canceled_count = 0

            for order in opposite_orders:
                order_time = order.get("timestamp")
                if not order_time:
                    # 没有时间戳，跳过（可能是旧数据）
                    logging.warning(
                        f"⚠️ 订单无时间戳，跳过: 账户={account_id}, "
                        f"订单={order['order_id'][:15]}..."
                    )
                    continue

                # 解析时间戳
                try:
                    if isinstance(order_time, str):
                        order_time = datetime.strptime(order_time, "%Y-%m-%d %H:%M:%S")
                    elif isinstance(order_time, datetime):
                        pass
                    else:
                        logging.warning(
                            f"⚠️ 订单时间戳格式异常: 账户={account_id}, "
                            f"订单={order['order_id'][:15]}..., 时间戳={order_time}"
                        )
                        continue
                except Exception as e:
                    logging.error(
                        f"❌ 解析订单时间戳失败: 账户={account_id}, "
                        f"订单={order['order_id'][:15]}..., 错误={e}"
                    )
                    continue

                # 计算订单存在时长
                order_age = now - order_time
                age_minutes = order_age.total_seconds() / 60

                # 只撤销创建时间超过5分钟的订单（旧的网格挂单）
                if order_age > time_threshold:
                    logging.info(
                        f"🔄 撤销旧的网格挂单: 账户={account_id}, "
                        f"订单={order['order_id'][:15]}..., "
                        f"方向={order['side']}, 创建时间={order_time.strftime('%Y-%m-%d %H:%M:%S')}, "
                        f"已存在={age_minutes:.1f}分钟"
                    )

                    try:
                        # ✅ 调用全局API限流器
                        if self.api_limiter:
                            await self.api_limiter.check_and_wait()

                        # 撤销订单
                        cancel_result = await exchange.cancel_order(
                            order["order_id"], symbol
                        )

                        if cancel_result.get("info", {}).get("sCode") == "0":
                            # 更新数据库状态
                            await self.db.update_order_by_id(
                                account_id,
                                order["order_id"],
                                {"status": "canceled"},
                            )
                            canceled_count += 1
                            logging.info(
                                f"✅ 已撤销反向挂单: 账户={account_id}, "
                                f"订单={order['order_id'][:15]}..."
                            )
                        else:
                            error_msg = cancel_result.get("info", {}).get(
                                "sMsg", "未知错误"
                            )
                            error_code = cancel_result.get("info", {}).get("sCode", "")
                            # 订单已成交、取消或不存在（51400错误码）
                            if (
                                error_code == "51400"
                                or "filled" in error_msg.lower()
                                or "canceled" in error_msg.lower()
                                or "does not exist" in error_msg.lower()
                            ):
                                logging.info(
                                    f"ℹ️ 订单已不存在或已处理，更新数据库状态: 账户={account_id}, "
                                    f"订单={order['order_id'][:15]}..., 错误={error_msg}"
                                )
                                await self.db.update_order_by_id(
                                    account_id,
                                    order["order_id"],
                                    {"status": "canceled"},
                                )
                                canceled_count += 1
                            else:
                                logging.warning(
                                    f"⚠️ 撤销订单失败: 账户={account_id}, "
                                    f"订单={order['order_id'][:15]}..., 错误={error_msg}"
                                )
                    except Exception as e:
                        error_msg = str(e)
                        # 如果订单不存在（已被交易所删除或过期）
                        if (
                            "51603" in error_msg
                            or "51400" in error_msg
                            or "Order does not exist" in error_msg
                            or "filled" in error_msg.lower()
                            or "canceled" in error_msg.lower()
                        ):
                            logging.info(
                                f"ℹ️ 订单已不存在或已处理，更新数据库状态: 账户={account_id}, "
                                f"订单={order['order_id'][:15]}..."
                            )
                            await self.db.update_order_by_id(
                                account_id,
                                order["order_id"],
                                {"status": "canceled"},
                            )
                            canceled_count += 1
                        else:
                            logging.error(
                                f"❌ 撤销订单异常: 账户={account_id}, "
                                f"订单={order['order_id'][:15]}..., 错误={e}"
                            )
                else:
                    logging.info(
                        f"⏭️ 跳过新订单: 账户={account_id}, "
                        f"订单={order['order_id'][:15]}..., "
                        f"方向={order['side']}, 创建时间={order_time.strftime('%Y-%m-%d %H:%M:%S')}, "
                        f"仅存在={age_minutes:.1f}分钟（可能是新开单）"
                    )

            if canceled_count > 0:
                logging.info(
                    f"✅ 账户 {account_id} 币种 {full_symbol} 已撤销 {canceled_count} 个反向挂单"
                )

        except Exception as e:
            logging.error(
                f"❌ 撤销反向挂单失败: 账户={account_id}, 币种={full_symbol}, 错误={e}",
                exc_info=True,
            )
