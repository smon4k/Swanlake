import asyncio
from decimal import Decimal
import json
import logging
import uuid
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
    milliseconds_to_local_datetime
)
from database import Database
from trading_bot_config import TradingBotConfig
from stop_loss_task import StopLossTask
from savings_task import SavingsTask
import traceback


class PriceMonitoringTask:
    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock, stop_loss_task: StopLossTask, busy_accounts: set[int]):
        self.config = config
        self.db = db
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task  # 保留引用
        self.running = True  # 控制运行状态
        self.busy_accounts = busy_accounts  # 引用交易机器人中的忙碌账户集合

    async def price_monitoring_task(self):
        """价格监控主任务（支持并发账户）"""
        # while self.running:
        while getattr(self, 'running', True):
            try:
                if self.signal_lock.locked():
                    print("⏸ 信号处理中，跳过一次监控")
                    logging.info("⏸ 信号处理中，跳过一次监控")
                    await asyncio.sleep(1)
                    continue

                # 获取所有账户 ID
                account_ids = list(self.db.account_cache.keys())
                if not account_ids:
                    await asyncio.sleep(self.config.check_interval)
                    continue

                # 并发执行每个账户的持仓检查
                tasks = [
                    self._safe_check_positions(account_id) for account_id in account_ids
                ]
                await asyncio.gather(*tasks, return_exceptions=True)

                await asyncio.sleep(self.config.check_interval)

            except Exception as e:
                print(f"❌ 价格监控主循环异常: {e}")
                logging.error(f"❌ 价格监控主循环异常: {e}")
                await asyncio.sleep(5)

    async def _safe_check_positions(self, account_id: int):
        """安全封装的账户检查（防止一个账户崩溃影响整体）"""
        if account_id in self.busy_accounts:
            print(f"⏸️ 账户 {account_id} 正在被信号处理，跳过本次价格监控")
            logging.info(f"⏸️ 账户 {account_id} 正在被信号处理，跳过本次价格监控")
            return
        
        try:
            await self.check_positions(account_id)
        except Exception as e:
            print(f"❌ 账户 {account_id} 检查持仓失败: {e}")
            logging.error(f"❌ 账户 {account_id} 检查持仓失败: {e}")
            traceback.print_exc()

    async def check_positions(self, account_id: int):
        """检查指定账户的持仓与订单（优化版本：缓存 + 并发）"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return

            # ✅ 获取账户配置
            account_config = self.db.account_config_cache.get(account_id)
            if not account_config:
                logging.info(f"⚠️ 账户未配置: {account_id}")
                return

            max_position_list = account_config.get('max_position_list', '[]')
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
                return

            # --------------------------
            # 1. 缓存 symbol -> positions
            # --------------------------
            # ✅ 直接获取所有持仓，不再为每个 symbol 重复请求
            positions_dict = {}

            try:
                all_positions = await exchange.fetch_positions('', {'instType': 'SWAP'})
                # logging.info(f"🔍 账户 {account_id} 持仓数: {len(all_positions)}")

                # 分类整理：symbol => [pos1, pos2, ...]
                for pos in all_positions:
                    sym = pos['info'].get('instId')
                    if not sym:
                        continue
                    positions_dict.setdefault(sym, []).append(pos)

            except Exception as e:
                logging.error(f"⚠️ 获取所有持仓失败 {account_id}: {e}")

            # --------------------------
            # 2. 并发获取订单详情
            # --------------------------
            order_infos = {}
            async def fetch_order_info(order):
                try:
                    info = await exchange.fetch_order(order['order_id'], order['symbol'], {'instType': 'SWAP'})
                    order_infos[order['order_id']] = info
                except Exception as e:
                    logging.error(f"⚠️ 查询订单失败 {account_id}/{order['symbol']}: {e}")
                    order_infos[order['order_id']] = None
            await asyncio.gather(*[fetch_order_info(o) for o in open_orders])

            # --------------------------
            # 3. 遍历订单（逻辑不变）
            # --------------------------
            latest_fill_time = 0
            latest_order, executed_price, fill_date_time = None, None, None
            process_grid = False

            for order in open_orders:
                symbol = order['symbol']
                order_info = order_infos.get(order['order_id'])
                positions = positions_dict.get(symbol, [])

                if not order_info:
                    continue

                # ⚡ 处理无持仓情况
                if not positions:
                    logging.info(f"🔍 无持仓，取消订单: {account_id} {order['order_id']} {symbol} {order['side']}")
                    await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
                    await cancel_all_orders(self, exchange, account_id, symbol)
                    continue

                state = order_info['info']['state']
                if state == 'canceled':
                    await self.db.update_order_by_id(account_id, order_info['id'], {'status': state})
                    continue

                elif state in ('filled', 'partially_filled'):
                    if state == 'partially_filled':
                        total_amount = Decimal(order_info['amount'])
                        filled_amount = Decimal(order_info['filled'])
                        if filled_amount < total_amount * Decimal('0.7'):
                            continue

                    fill_time = float(order_info['info'].get('fillTime', 0))
                    if fill_time > latest_fill_time:
                        latest_fill_time = fill_time
                        latest_order = order_info
                        executed_price = order_info['info']['fillPx']
                        fill_date_time = await milliseconds_to_local_datetime(fill_time)
                        process_grid = True

            # ✅ 后续逻辑不变
            if process_grid and latest_order:
                # symbol = latest_order['symbol']
                logging.info(f"✅ 订单已成交: 用户={account_id}, 币种={symbol}, 方向={latest_order['side']}, 价格={executed_price}")
                managed = await self.manage_grid_orders(latest_order, account_id)
                if managed:
                    await self.db.update_order_by_id(
                        account_id,
                        latest_order['id'],
                        {'executed_price': executed_price, 'status': 'filled', 'fill_time': fill_date_time}
                    )
                    await self.update_order_status(latest_order, account_id, executed_price, fill_date_time, symbol)
                    await self.stop_loss_task.accounts_stop_loss_task(account_id)

        except Exception as e:
            logging.error(f"❌ 账户 {account_id} 检查持仓失败: {e}", exc_info=True)
        finally:
            if exchange:
                await exchange.close()


    async def update_order_status(self, order: dict, account_id: int, executed_price: float, fill_date_time: str, symbol: str):
        """更新订单状态并配对计算利润（逻辑不变）"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return

            print("🔄 开始匹配订单")
            logging.info(f"🔄 账户 {account_id} 开始匹配订单")

            side = 'sell' if order['side'] == 'buy' else 'buy'
            matched_order = await self.db.get_order_by_price_diff_v2(account_id, order['info']['instId'], executed_price, side)
            logging.info(f"配对订单: {matched_order['order_id'] if matched_order else '无'}")

            profit = 0
            group_id = ""
            market_precision = await get_market_precision(exchange, symbol)

            if matched_order:
                qty = min(float(order['amount']), float(matched_order['quantity']))
                contract_size = market_precision['contract_size']

                if order['side'] == 'sell':
                    profit = (Decimal(str(executed_price)) - Decimal(str(matched_order['executed_price']))) \
                             * Decimal(str(qty)) * Decimal(str(contract_size)) * Decimal('0.99998')
                    print(f"📊 用户 {account_id} 配对利润 (buy): {profit}")
                    logging.info(f"📊 用户 {account_id} 配对利润 (buy): {profit}")

                elif order['side'] == 'buy':
                    profit = (Decimal(str(matched_order['executed_price'])) - Decimal(str(executed_price))) \
                             * Decimal(str(qty)) * Decimal(str(contract_size)) * Decimal('0.99998')
                    print(f"📊 配对利润 用户 {account_id} (sell): {profit}")
                    logging.info(f"📊 用户 {account_id} 配对利润 (sell): {profit}")

                if profit != 0:
                    group_id = str(uuid.uuid4())
                    await self.db.update_order_by_id(account_id, matched_order['order_id'], {
                        'profit': profit,
                        'position_group_id': group_id
                    })

                await self.db.update_order_by_id(account_id, order['id'], {
                    'executed_price': executed_price,
                    'status': order['info']['state'],
                    'fill_time': fill_date_time,
                    'profit': profit,
                    'position_group_id': group_id
                })

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

            symbol = order['info']['instId']
            filled_price = Decimal(order['info']['fillPx'])
            print(f"📌 用户 {account_id} 最新订单成交价: {filled_price}")
            logging.info(f"📌 用户 {account_id} 最新订单成交价: {filled_price}")

            price = await get_market_price(exchange, symbol)
            grid_step = Decimal(str(self.db.account_config_cache[account_id].get('grid_step', 0.002)))
            price_diff_ratio = abs(filled_price - price) / price

            if price_diff_ratio > grid_step:
                filled_price = price
                print(f"🔄 用户 {account_id} 价格偏差过大，使用市价: {filled_price}")
                logging.info(f"🔄 用户 {account_id} 价格偏差过大，使用市价: {filled_price}")

            buy_price = filled_price * (1 - grid_step)
            sell_price = filled_price * (1 + grid_step)

            positions = await exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            if not positions:
                print("🚫 网格下单：无持仓")
                return True

            total_position_value = await get_total_positions(self, account_id, symbol, 'SWAP')
            if total_position_value <= 0:
                return True

            balance = await get_account_balance(exchange, symbol)
            # print(f"💰 账户余额: {balance}")
            logging.info(f"💰 用户 {account_id} 账户余额: {balance}")

            symbol_tactics = symbol.replace('-SWAP', '') if symbol.endswith('-SWAP') else symbol
            tactics = await self.db.get_tactics_by_account_and_symbol(account_id, symbol_tactics)
            if not tactics:
                print(f"🚫 未找到策略: {account_id} {symbol_tactics}")
                return False

            signal = await self.db.get_latest_signal(symbol, tactics)
            side = 'buy' if signal['direction'] == 'long' else 'sell'
            market_precision = await get_market_precision(exchange, symbol)

            total_position_quantity = Decimal(total_position_value) * Decimal(market_precision['amount']) * price
            await cancel_all_orders(self, exchange, account_id, symbol)

            percent_list = await get_grid_percent_list(self, account_id, signal['direction'])
            buy_percent = percent_list.get('buy')
            sell_percent = percent_list.get('sell')

            buy_size = (total_position_value * Decimal(str(buy_percent))).quantize(
                Decimal(market_precision['amount']), rounding='ROUND_DOWN'
            )
            if buy_size < market_precision['min_amount']:
                print(f"📉 用户 {account_id} 买单过小: {buy_size}")
                logging.info(f"📉 用户 {account_id} 买单过小: {buy_size}")
                return False

            sell_size = (total_position_value * Decimal(str(sell_percent))).quantize(
                Decimal(market_precision['amount']), rounding='ROUND_DOWN'
            )
            if sell_size < market_precision['min_amount']:
                print(f"📉 用户 {account_id} 卖单过小: {sell_size}")
                logging.info(f"📉 用户 {account_id} 卖单过小: {sell_size}")
                return False

            max_position = await get_max_position_value(self, account_id, symbol)
            buy_total = total_position_quantity + buy_size * market_precision['amount'] * buy_price - sell_size * market_precision['amount'] * sell_price
            if buy_total >= max_position:
                print(f"⚠️ 用户 {account_id} 超过最大持仓，取消挂单")
                logging.info(f"⚠️ 用户 {account_id} 超过最大持仓，取消挂单")
                return False

            group_id = str(uuid.uuid4())
            pos_side = 'long'
            if side == 'buy' and signal['size'] == 1: # 开多
                pos_side = 'long'
            if side == 'sell' and signal['size'] == -1: # 开空
                pos_side = 'short'
            # print("📈 开仓方向:", pos_side)

            buy_order = None
            sell_order = None
            
            buy_client_order_id = ''
            sell_client_order_id = ''
            if buy_size > 0:
                buy_client_order_id = await get_client_order_id()
                buy_order = await open_position(
                    self, account_id, symbol, 'buy', pos_side, float(buy_size), float(buy_price),
                    'limit', buy_client_order_id, False
                )

            if sell_size > 0:
                sell_client_order_id = await get_client_order_id()
                sell_order = await open_position(
                    self, account_id, symbol, 'sell', pos_side, float(sell_size), float(sell_price),
                    'limit', sell_client_order_id, False
                )

            if buy_order and sell_order:
                await self.db.add_order({
                    'account_id': account_id, 'symbol': symbol, 'order_id': buy_order['id'],
                    'clorder_id': buy_client_order_id, 'price': float(buy_price), 'executed_price': None, 'quantity': float(buy_size),
                    'pos_side': pos_side, 'order_type': 'limit', 'side': 'buy', 'status': 'live', 'position_group_id': ''
                })
                await self.db.add_order({
                    'account_id': account_id, 'symbol': symbol, 'order_id': sell_order['id'],
                    'clorder_id': sell_client_order_id, 'price': float(sell_price), 'executed_price': None, 'quantity': float(sell_size),
                    'pos_side': pos_side, 'order_type': 'limit', 'side': 'sell', 'status': 'live', 'position_group_id': ''
                })
                print(f"✅ 用户 {account_id} 已挂单: 买{buy_price}({buy_size}) 卖{sell_price}({sell_size})")
                logging.info(f"✅ 用户 {account_id} 已挂单: 买{buy_price}({buy_size}) 卖{sell_price})")
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
            order_info = await exchange.fetch_order(order_id, None, None, {'instType': 'SWAP'})
            print(f"📋 用户 {account_id} 订单信息: {order_info}")
            logging.info(f"📋 用户 {account_id} 订单信息: {order_info}")
            return order_info
        except Exception as e:
            print(f"❌ 用户 {account_id} 获取订单失败: {e}")
            logging.error(f"❌ 用户 {account_id} 获取订单失败: {e}")
        finally:
            await exchange.close()