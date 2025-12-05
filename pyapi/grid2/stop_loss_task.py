import asyncio
from decimal import Decimal
import logging
from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import (
    get_exchange,
    get_market_price,
    get_market_precision,
    milliseconds_to_local_datetime,
    get_client_order_id,
)


class StopLossTask:
    """交易信号处理类"""

    def __init__(
        self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock
    ):
        self.db = db
        self.config = config
        self.running = True
        self.signal_lock = signal_lock
        self.market_precision_cache = {}  # 市场精度缓存

    async def stop_loss_task(self):
        """价格监控任务"""
        while getattr(self, "running", True):
            try:
                for account_id in self.db.account_cache:
                    await self.accounts_stop_loss_task(account_id)
                await asyncio.sleep(300)  # 每5分钟检查一次
            except Exception as e:
                print(f"价格监控异常: {e}")
                logging.error(f"价格监控异常: {e}")
                await asyncio.sleep(5)

    # 检查单个账户的止损
    async def accounts_stop_loss_task(self, account_id: int):
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            positions = await exchange.fetch_positions("", {"instType": "SWAP"})
            # print(positions)
            # return

            for pos in positions:
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
                        print(f"未找到策略配置: {account_id} {symbol_tactics}")
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
                        f"已有止损单={'存在' if order_sl_order else '无'}"
                    )
                    if order_sl_order:
                        try:
                            # 先判断是否已经成交或者取消
                            order_info = await exchange.fetch_order(
                                order_sl_order["order_id"],
                                symbol,
                                {"instType": "SWAP", "trigger": "true"},
                            )
                            # print("order_info", order_info)
                            if order_info["info"]["state"] in [
                                "pause",
                                "effective",
                                "canceled",
                                "order_failed",
                                "partially_failed",
                            ]:
                                print(
                                    f"已有止损单状态为 {account_id} {order_info['info']['state']}, 更新数据库状态: {symbol} {str(order_sl_order.get('order_id'))}"
                                )
                                logging.info(
                                    f"已有止损单状态为 {account_id} {order_info['info']['state']}, 更新数据库状态: {symbol} {str(order_sl_order.get('order_id'))}"
                                )
                                fill_date_time = await milliseconds_to_local_datetime(
                                    order_info["lastUpdateTimestamp"]
                                )  # 格式化成交时间
                                # print(f"止损单成交时间: {fill_date_time}")
                                await self.db.update_order_by_id(
                                    account_id,
                                    order_sl_order["order_id"],
                                    {
                                        "status": order_info["info"]["state"],
                                        "executed_price": float(
                                            order_info["info"]["slTriggerPx"]
                                        ),
                                        "fill_time": fill_date_time,
                                    },
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
                                print(
                                    f"已有未完成止损单，更新: {account_id} {symbol} {str(order_sl_order.get('order_id'))}"
                                )
                                logging.info(
                                    f"已有未完成止损单，更新: {account_id} {symbol} {str(order_sl_order.get('order_id'))}"
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
                        print(
                            f"持仓方向: {account_id} {side}, 交易对: {symbol}, 持仓均价: {entry_price}, 最新标记价格: {mark_price}"
                        )
                        logging.info(
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
            print(f"止损任务失败: {e}")
            logging.error(f"止损任务失败: {e}")
            return False
        finally:
            await exchange.close()

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
                exchange, symbol_tactics
            )  # 获取最新市场价格

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
                print(f"止损单创建成功: {account_id} {order['id']}")
                logging.info(f"止损单创建成功: {account_id} {order['id']}")
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
                print(f"用户{account_id} 下策略单失败: {error_msg}")
                logging.error(f"用户{account_id} 下策略单失败: {error_msg}")
                return None

        except Exception as e:
            print(f"用户{account_id} 下策略单失败 error: {e}")
            logging.error(f"用户{account_id} 下策略单失败 error: {e}")
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

            market_price = await get_market_price(exchange, symbol)  # 获取最新市场价格

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
            # print('修改止损单结果', edit_order)
            if edit_order and edit_order.get("info", {}).get("sCode") == "0":
                print(f"修改止损单成功: {account_id} {edit_order['id']}")
                logging.info(f"修改止损单成功: {account_id} {edit_order['id']}")
                # fill_date_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                await self.db.update_order_by_id(
                    account_id,
                    algo_order_id,
                    {"price": float(price), "quantity": float(amount)},
                )
                return edit_order
        except Exception as e:
            print(f"修改止损单失败: {account_id} {e}")
            logging.error(f"修改止损单失败: {account_id} {e}")
            return None
        finally:
            await exchange.close()
