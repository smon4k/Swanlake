import asyncio
import logging
from decimal import Decimal
import traceback

from common_functions import (
    get_account_balance, get_client_order_id, get_exchange, cancel_all_orders, get_grid_percent_list, get_market_precision, get_market_price, get_max_position_value, get_total_positions, milliseconds_to_local_datetime, open_position
)
from trading_bot_config import TradingBotConfig
from database import Database
from stop_loss_task import StopLossTask


class PriceMonitoringTask:
    """监控价格与订单状态"""

    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock, stop_loss_task: StopLossTask):
        self.config = config
        self.db = db
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task
        self.logger = logging.getLogger(__name__)
        self.running = True

    async def price_monitoring_task(self):
        """价格监控主循环"""
        while self.running:
            try:
                if self.signal_lock.locked():
                    self.logger.info("⏸ 信号处理中，跳过一次监控")
                    await asyncio.sleep(1)
                    continue

                for account_id in self.db.account_cache:
                    await self.check_positions(account_id)

                await asyncio.sleep(self.config.check_interval)

            except Exception as e:
                self.logger.error(f"价格监控异常: {e}", exc_info=True)
                await asyncio.sleep(5)

    async def check_positions(self, account_id: int):
        """检查持仓 & 订单状态"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return

            open_orders = await self.db.get_active_orders(account_id)
            if not open_orders:
                return

            latest_order = None
            latest_fill_time = 0

            for order in open_orders:
                try:
                    symbol = order['symbol']
                    order_info = exchange.fetch_order(order['order_id'], symbol, {'instType': 'SWAP'})
                    positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
                    pos_contracts = positions[0]['contracts'] if positions else 0
                    state = order_info['info']['state']

                    # === 按状态分流处理 ===
                    if state == 'live':
                        await self._handle_live_order(account_id, order_info, positions, pos_contracts, symbol)
                    elif state == 'canceled':
                        await self._handle_canceled_order(account_id, order_info, positions, pos_contracts, symbol)
                    elif state in ('filled', 'partially_filled'):
                        latest_order, latest_fill_time = await self._handle_filled_order(
                            account_id, order, order_info, positions,
                            latest_order, latest_fill_time
                        )
                    else:
                        await self._handle_unknown_order(account_id, order_info, positions, pos_contracts, symbol)

                except Exception as e:
                    self.logger.error(f"[订单处理异常] account={account_id}, order={order['order_id']}, err={e}", exc_info=True)
                    continue

        except Exception as e:
            self.logger.error(f"检查持仓失败: {e}", exc_info=True)

    # === 状态处理函数 ===
    async def _handle_live_order(self, account_id, order_info, positions, pos_contracts, symbol):
        if not positions or pos_contracts <= 0:
            self.logger.info(f"[撤销] 无持仓，取消订单 {order_info['id']} {symbol}")
            await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
            await cancel_all_orders(self, account_id, symbol)

    async def _handle_canceled_order(self, account_id, order_info, positions, pos_contracts, symbol):
        self.logger.info(f"[订单已撤销] {order_info['id']} {symbol}")
        await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
        if not positions or pos_contracts <= 0:
            await cancel_all_orders(self, account_id, symbol)

    async def _handle_filled_order(self, account_id, order, order_info, positions, latest_order, latest_fill_time):
        """处理成交或部分成交订单"""
        state = order_info['info']['state']
        total_amount = Decimal(order_info['amount'])
        filled_amount = Decimal(order_info['filled'])

        if state == 'partially_filled' and filled_amount < total_amount * Decimal('0.7'):
            self.logger.info(f"[部分成交不足70%] {order['order_id']} {order['symbol']}")
            return latest_order, latest_fill_time

        fill_time = float(order_info['info'].get('fillTime', 0))
        if fill_time > latest_fill_time:
            latest_fill_time = int(fill_time)
            latest_order = order_info

            executed_price = order_info['info']['fillPx']
            self.logger.info(f"[成交] account={account_id}, symbol={latest_order['symbol']}, side={latest_order['side']}, price={executed_price}")

            # === 更新数据库 + 网格管理 + 止损检查 ===
            fill_date_time = await milliseconds_to_local_datetime(fill_time)
            await self.manage_grid_orders(latest_order, account_id)
            await self.db.update_order_by_id(account_id, order_info['id'], {
                'executed_price': executed_price,
                'status': state,
                'fill_time': fill_date_time
            })
            await self.update_order_status(order_info, account_id, executed_price, fill_date_time, order['symbol'])
            await self.stop_loss_task.accounts_stop_loss_task(account_id)

        return latest_order, latest_fill_time

    async def _handle_unknown_order(self, account_id, order_info, positions, pos_contracts, symbol):
        self.logger.warning(f"[未知状态] {order_info['id']} {symbol} state={order_info['info']['state']}")
        await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
        if not positions or pos_contracts <= 0:
            await cancel_all_orders(self, account_id, symbol)

    # === 数据库订单状态更新 ===
    async def update_order_status(self, order_info, account_id, executed_price, fill_time, symbol):
        """更新数据库订单状态"""
        try:
            self.logger.info(f"[更新订单状态] {order_info['id']} {symbol} 成交价={executed_price}")
            await self.db.update_order_by_id(account_id, order_info['id'], {
                'executed_price': executed_price,
                'status': order_info['info']['state'],
                'fill_time': fill_time
            })
        except Exception as e:
            self.logger.error(f"更新订单状态失败: {e}", exc_info=True)

    # === 网格管理逻辑 ===
    async def manage_grid_orders(self, order: dict, account_id: int) -> bool:
        """基于订单成交价进行撤单和网格管理，计算挂单数量"""
        try:
            logging.info("=== 开始执行 manage_grid_orders ===")

            # Step 1: 获取交易所实例和 symbol
            exchange, symbol = await self._get_exchange_and_symbol(order, account_id)
            if not exchange:
                return False

            # Step 2: 获取成交价，并做价格修正
            filled_price = await self._get_filled_price(order) # 最新成交价
            price = await self._get_market_price(exchange, symbol) # 最新市场价
            filled_price = await self._adjust_filled_price(filled_price, price, account_id) # 修正成交价

            # Step 3: 获取持仓与余额
            total_position_value, balance = await self._get_positions_and_balance(exchange, symbol, account_id)
            if not total_position_value:
                return True  # 没仓位就不用挂单

            # Step 4: 获取策略信号
            signal, side, market_precision = await self._get_strategy_signal(exchange, symbol, account_id)
            if not signal:
                return False

            # Step 5: 计算挂单数量
            buy_price, sell_price, buy_size, sell_size, total_position_quantity = \
                await self._calculate_grid_sizes(filled_price, price, total_position_value, market_precision, account_id, signal)

            if not buy_size or not sell_size:
                return False

            # Step 6: 检查最大持仓限制
            is_buy = await self._check_position_limits(total_position_quantity, buy_size, sell_size, buy_price, sell_price, account_id, symbol)
            if not is_buy:
                return False

            # Step 7: 取消旧订单，创建新网格单
            await cancel_all_orders(self, account_id, symbol)
            return await self._place_grid_orders(signal, side, account_id, symbol, buy_price, sell_price, buy_size, sell_size)

        except Exception as e:
            logging.error(f"网格订单管理失败: {str(e)}")
            traceback.print_exc()
            return False

    # ----------------- 子方法 -----------------

    async def _get_exchange_and_symbol(self, order, account_id):
        exchange = await get_exchange(self, account_id)
        if not exchange:
            logging.warning("未找到交易所实例")
            return None, None
        return exchange, order['info']['instId']

    async def _get_filled_price(self, order):
        filled_price = Decimal(order['info']['fillPx'])
        logging.info(f"最新订单成交价: {filled_price}")
        return filled_price

    async def _get_market_price(self, exchange, symbol):
        price = await get_market_price(exchange, symbol)
        logging.info(f"最新市场价格: {price}")
        return price

    async def _adjust_filled_price(self, filled_price, price, account_id):
        grid_step = Decimal(str(self.db.account_config_cache[account_id].get('grid_step')))
        price_diff_ratio = abs(filled_price - price) / price
        if price_diff_ratio > grid_step:
            filled_price = price
            logging.info(f"价格差超过 {grid_step * 100}%，使用最新价格作为成交价: {filled_price}")
        return filled_price

    async def _get_positions_and_balance(self, exchange, symbol, account_id):
        positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
        if not positions:
            logging.warning("网格下单 无持仓信息")
            return None, None

        total_position_value = await get_total_positions(self, account_id, symbol, 'SWAP')
        if total_position_value <= 0:
            logging.warning("网格下单 无持仓信息")
            return None, None

        balance = await get_account_balance(exchange, symbol)
        logging.info(f"账户余额: {balance}")
        return total_position_value, balance

    async def _get_strategy_signal(self, exchange, symbol, account_id):
        symbol_tactics = symbol.replace('-SWAP', '') if symbol.endswith('-SWAP') else symbol
        tactics = await self.db.get_tactics_by_account_and_symbol(account_id, symbol_tactics)
        if not tactics:
            logging.warning(f"未找到策略配置: {account_id} {symbol_tactics}")
            return None, None, None

        signal = await self.db.get_latest_signal(symbol, tactics)
        side = 'buy' if signal['direction'] == 'long' else 'sell'
        market_precision = await get_market_precision(exchange, symbol)
        return signal, side, market_precision

    async def _calculate_grid_sizes(self, filled_price, price, total_position_value, market_precision, account_id, signal):
        percent_list = await get_grid_percent_list(self, account_id, signal['direction'])
        buy_percent, sell_percent = percent_list.get('buy'), percent_list.get('sell')

        buy_price = filled_price * (Decimal('1') - Decimal(str(self.db.account_config_cache[account_id]['grid_step'])))
        sell_price = filled_price * (Decimal('1') + Decimal(str(self.db.account_config_cache[account_id]['grid_step'])))

        # 买单数量
        buy_size = (total_position_value * Decimal(str(buy_percent))).quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')
        if buy_size < market_precision['min_amount']:
            logging.warning(f"买单数量小于最小下单量: {buy_size} < {market_precision['min_amount']}")
            return None, None, None, None, None

        # 卖单数量
        sell_size = (total_position_value * Decimal(str(sell_percent))).quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')
        if sell_size < market_precision['min_amount']:
            logging.warning(f"卖单数量小于最小下单量: {sell_size} < {market_precision['min_amount']}")
            return None, None, None, None, None

        total_position_quantity = Decimal(total_position_value) * Decimal(market_precision['amount']) * price
        logging.info(f"计算挂单量: 卖{sell_size} 买{buy_size}")
        return buy_price, sell_price, buy_size, sell_size, total_position_quantity

    async def _check_position_limits(self, total_position_quantity, buy_size, sell_size, buy_price, sell_price, account_id, symbol):
        max_position = await get_max_position_value(self, account_id, symbol)
        buy_value = total_position_quantity + (Decimal(buy_size) * buy_price) - (Decimal(sell_size) * sell_price)

        if buy_value >= max_position:
            logging.warning("下单量超过最大持仓，不执行挂单")
            return False
        return True

    async def _place_grid_orders(self, signal, side, account_id, symbol, buy_price, sell_price, buy_size, sell_size):
        pos_side = 'long' if (side == 'buy' and signal['size'] == 1) else 'short'
        logging.info(f"方向: {pos_side}")

        buy_order = sell_order = None
        sell_client_order_id = None
        buy_client_order_id = None
        if buy_size and float(buy_size) > 0:
            buy_client_order_id = await get_client_order_id()
            buy_order = await open_position(self, account_id, symbol, 'buy', pos_side, float(buy_size), float(buy_price), 'limit', buy_client_order_id, False)

        if sell_size and float(sell_size) > 0:
            sell_client_order_id = await get_client_order_id()
            sell_order = await open_position(self, account_id, symbol, 'sell', pos_side, float(sell_size), float(sell_price), 'limit', sell_client_order_id, False)

        if buy_order and sell_order:
            await self._record_order(account_id, symbol, buy_order, buy_client_order_id, buy_price, buy_size, pos_side, 'buy')
            await self._record_order(account_id, symbol, sell_order, sell_client_order_id, sell_price, sell_size, pos_side, 'sell')
            return True

        logging.warning("网格下单失败，未能成功挂买单或卖单")
        return False

    async def _record_order(self, account_id, symbol, order, client_order_id, price, size, pos_side, side):
        await self.db.add_order({
            'account_id': account_id,
            'symbol': symbol,
            'order_id': order['id'],
            'clorder_id': client_order_id,
            'price': float(price),
            'executed_price': None,
            'quantity': float(size),
            'pos_side': pos_side,
            'order_type': 'limit',
            'side': side,
            'status': 'live',
            'position_group_id': '',
        })
        logging.info(f"已挂{side}单: 价格{price} 数量{size}")