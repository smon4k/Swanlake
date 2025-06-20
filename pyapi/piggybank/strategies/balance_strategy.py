# Refactored BalanceStrategy class
from datetime import datetime
from decimal import Decimal
from pyapi.piggybank.strategies.pending_strategy import PendingStrategy
from config.constants import OrderStatus, OrderType, OrderSide
from utils.helpers import generate_client_order_id
from .base_strategy import BaseStrategy

class BalanceStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        # 初始化参数，包括交易所对象、数据库会话和配置
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol):
        """
        执行平衡策略主流程：估值比较、成交状态处理、重新挂单等
        """
        try:
            base_token, quote_token = symbol.split('-')
            exchange = self.get_exchange_name()
            market_info = self.exchange.get_market_info(symbol)
            valuation = self._get_valuation(symbol)  # 获取当前估值和余额
            pending_orders = self.crud.get_open_pending_orders(exchange)  # 获取当前挂单信息

            # 没有挂单则直接挂新单
            if not pending_orders.get('buy') or not pending_orders.get('sell'):
                return self._retry_place_pending_orders(market_info, valuation, symbol)

            # 如果估值不平衡（超出设定阈值），执行撤单-吃单流程
            if self._is_valuation_unbalanced(valuation):
                return self._handle_unbalanced_valuation(symbol)

            # 检查订单是否有一方成交
            make_side, make_order = self._check_order_status(symbol, pending_orders)
            if make_side:
                return self._handle_deal(make_side, make_order, pending_orders, market_info, valuation, symbol)

            # 检查余额是否有变化（判断是否发生部分成交或资金变动）
            if self._has_balance_changed(pending_orders['buy'], valuation):
                return self._handle_no_deal(symbol, valuation)

            print("[无成交] 挂单进行中")
            return True
        except Exception as e:
            print(f"[{exchange}] 策略执行异常: {e}")
            return False

    def _retry_place_pending_orders(self, market_info, valuation, symbol):
        """当无挂单时重新挂单"""
        print("[无挂单] 开始挂单")
        return self._place_balancing_orders(market_info, valuation, symbol)

    def _is_valuation_unbalanced(self, valuation):
        """判断BTC和USDT估值是否不平衡，超出设定阈值返回True"""
        btc_val = Decimal(valuation['btc_valuation'])
        usdt_val = Decimal(valuation['usdt_valuation'])
        threshold = Decimal(str(self.config.VALUATION_THRESHOLD))

        if btc_val == 0 or usdt_val == 0:
            return False

        diff = abs(btc_val - usdt_val)
        ratio = diff / min(btc_val, usdt_val) * 100
        return ratio > threshold

    def _handle_unbalanced_valuation(self, symbol):
        """处理估值不平衡逻辑：撤单 -> 吃单 -> 挂单"""
        print("[估值不平衡] 撤单->吃单->挂单")
        self._cancel_open_orders(symbol)
        strategy = PendingStrategy(self.exchange, self.db_session, self.config)
        success = strategy.execute(symbol)
        if success:
            return self.execute(symbol)
        return False

    def _check_order_status(self, symbol, pending_orders):
        """
        检查当前挂单的状态
        如果有订单成交超过50%，返回成交方向和订单信息
        """
        for side in ['buy', 'sell']:
            order_data = pending_orders.get(side)
            if not order_data:
                continue
            order_info = self.exchange.fetch_order(order_data.order_id, symbol)
            if not order_info:
                continue

            order_amount = Decimal(order_info['info']['sz'])
            deal_amount = Decimal(order_info['info']['accFillSz'])
            status = order_info['info']['state']

            if status == 'filled' and deal_amount >= order_amount * Decimal('0.5'):
                return (1 if side == 'buy' else 2), order_data
            if status == 'canceled':
                print(f"[{side.upper()}] 订单已撤销: {order_data.order_id}")
                self.crud.update_clinch_amount(
                    exchange=self.get_exchange_name(),
                    order_id=order_data.order_id,
                    deal_amount=0,
                    status=3
                )
                return 0, None
        return 0, None

    def _handle_deal(self, make_side, make_order, pending_orders, market_info, valuation, symbol):
        """
        处理有一方成交后的逻辑：更新数据库、撤销订单、记录利润、重新挂单
        """
        print(f"[成交] 成交方向: {make_side}")
        deal_price = make_order.price
        deal_amount = make_order.clinch_amount
        exchange = self.get_exchange_name()

        self._update_db_after_deal(make_side, make_order, pending_orders, deal_amount)
        self._cancel_open_orders(symbol)

        # 计算是否有配对订单及利润
        pair_info = self.crud.get_pair_and_calculate_profit(
            exchange=exchange,
            type=make_side,
            deal_price=float(deal_price)
        )

        profit = 0
        pair_id = 0
        if pair_info:
            pair_id = pair_info['pair_id']
            profit = pair_info['clinch_number'] * (pair_info['price'] - float(deal_price))

        new_valuation = self._get_valuation(symbol)
        self._insert_deal_record(symbol, make_side, make_order, deal_price, deal_amount, new_valuation, profit, pair_id)

        # 重新挂单
        return self._place_balancing_orders(market_info, valuation, symbol)

    def _has_balance_changed(self, buy_order_data, new_valuation):
        """判断买单时的余额和当前是否发生变化"""
        return (Decimal(buy_order_data.currency1) != Decimal(new_valuation['btc_balance']) or
                Decimal(buy_order_data.currency2) != Decimal(new_valuation['usdt_balance']))

    def _handle_no_deal(self, symbol, valuation):
        """处理无成交但余额有变化的情况，重新挂单"""
        print("[无成交] 余额变化，重新挂单")
        self._cancel_open_orders(symbol)
        strategy = PendingStrategy(self.exchange, self.db_session, self.config)
        return strategy.execute(symbol)

    def _update_db_after_deal(self, make_side, make_order, pending_orders, deal_amount):
        """更新数据库中的成交订单和撤销的订单状态"""
        exchange = self.get_exchange_name()
        status_done = 2  # 已成交
        status_cancel = 3  # 撤销

        self.crud.update_clinch_amount(exchange, make_order.order_id, deal_amount, status_done)

        # 找到另一侧订单进行撤销状态更新
        other_order = pending_orders['sell'] if make_side == 1 else pending_orders['buy']
        self.crud.update_pendord_status(exchange, other_order.order_id, 0, status_cancel)

    def _insert_deal_record(self, symbol, make_side, order, price, amount, valuation, profit, pair_id):
        """将成交记录写入 piggybank 表中"""
        exchange = self.get_exchange_name()
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        record = {
            'exchange': exchange,
            'product_name': order.product_name,
            'order_id': order.order_id,
            'order_number': order.order_number,
            'td_mode': 'cross',
            'base_ccy': symbol.split('-')[0],
            'quote_ccy': symbol.split('-')[1],
            'type': make_side,
            'order_type': 'LIMIT',
            'amount': amount,
            'clinch_number': amount,
            'price': price,
            'make_deal_price': price,
            'profit': profit,
            'currency1': valuation['btc_balance'],
            'currency2': valuation['usdt_balance'],
            'balanced_valuation': valuation['usdt_balance'],
            'pair': pair_id,
            'time': now,
            'up_time': now
        }

        inserted = self.crud.create_piggybank(record)
        if inserted and pair_id > 0:
            self.crud.update_pair_and_profit(pair_id, float(profit))

    def _cancel_open_orders(self, symbol):
        """撤销当前交易对的所有挂单"""
        try:
            open_orders = self.exchange.fetch_open_orders(symbol)
            if not open_orders:
                return True
            for order in open_orders:
                order_id = order.get('id')
                if order_id:
                    self.exchange.cancel_order(order_id, symbol)
            self.crud.revoke_all_pending_orders(exchange=self.get_exchange_name())
            return True
        except Exception as e:
            print(f"[撤单失败] {e}")
            return False
    
    # 开始挂单
    # 根据估值重挂买卖单
    def _place_balancing_orders(self, market_info, valuation, symbol):
        """根据估值重挂买卖单"""
        print("valuation", valuation)
        base_token, quote_token = symbol.split('-')
        exchange = self.get_exchange_name()
        change_ratio = self.config.CHANGE_RATIO #涨跌比例
        balance_ratio = self.config.BALANCE_RATIO #平衡比例
        balance_ratio_arr = list(map(Decimal, balance_ratio.split(':')))
        print("balance_ratio_arr", balance_ratio_arr)
        last_res_price = Decimal(str(self.crud.get_last_deal_price(exchange, symbol) or '0'))  # 上次成交价格
        # last_res_price = Decimal("106546")  # 上次成交价格
        trading_price = Decimal(valuation['btc_price'])  # BTC价格
        print("last_res_price", last_res_price, "trading_price", trading_price)
        btc_valuation = Decimal(str(valuation.get('btc_valuation', '0')))
        usdt_valuation = Decimal(str(valuation.get('usdt_valuation', '0')))
        print(f"[估值2] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
        buy_last_price = last_res_price
        sell_last_price = last_res_price
        if buy_last_price > trading_price:
            buy_last_price = trading_price
        if sell_last_price < trading_price:
            sell_last_price = trading_price
        sell_propr = (Decimal(change_ratio) / Decimal(change_ratio)) + (change_ratio / Decimal('100'))  # 出售比例
        buy_propr = (Decimal(change_ratio) / Decimal(change_ratio)) - (change_ratio / Decimal('100'))  # 购买比例
        selling_price = sell_last_price * sell_propr  # 出售价格
        buying_price = buy_last_price * buy_propr  # 购买价格
        btc_balance = Decimal(valuation['btc_balance'])  # BTC余额
        usdt_balance = Decimal(valuation['usdt_balance'])  # BUSD余额
        sell_valuation = selling_price * btc_balance  # GBTC 出售估值
        buy_valuation = buying_price * btc_balance  # BTC 购买估值

        buy_diff = 0
        if usdt_valuation > buy_valuation:
            buy_diff = usdt_valuation - buy_valuation
        else:
            buy_diff = buy_valuation - usdt_valuation
        
        sell_diff = 0
        if usdt_valuation > sell_valuation:
            sell_diff = usdt_valuation - sell_valuation
        else:
            sell_diff = sell_valuation - usdt_valuation

        buy_num = balance_ratio_arr[1] * (buy_diff / (balance_ratio_arr[0] + balance_ratio_arr[1]))
        print("buy_num", buy_num, balance_ratio_arr[1], usdt_valuation, buy_valuation)
        buy_orders_number = buy_num / buying_price
        print(f"[计算] 购买数量: {buy_orders_number}")

        sell_num = balance_ratio_arr[0] * (sell_diff / (balance_ratio_arr[0] + balance_ratio_arr[1]))
        print("sell_num", sell_num)
        sell_orders_number = sell_num / selling_price
        print(f"[计算] 出售数量: {sell_orders_number}")
        print(f"[计算] BTC 出售估值{sell_valuation}usdt估值{usdt_valuation}出售数量{sell_num}出售价格{selling_price}\n")
        print(f"{buy_orders_number}&&&{sell_orders_number}\n")

        market_info = self.exchange.get_market_info(symbol)
        min_size = Decimal(market_info['info'].get('minSz', 0))
        if buy_orders_number < min_size or sell_orders_number < min_size:
            print(f"[判断] 购买或出售出现负数或者小于最小下单量 {min_size},撤单 开始吃单")
            strategy = PendingStrategy(self.exchange, self.db_session, self.config)
            is_position_order = strategy.execute(symbol)  # 开始吃单平衡
            if is_position_order:
                print("吃单成功 开始重新挂单")
                is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)
                if is_pending_order:
                    print("已重新挂单")
                    return True
            return True
        
        per_diff_res = 0
        if usdt_valuation > btc_valuation:  # busd大
            print("""usdt大""")
            per_diff_res = (usdt_valuation - btc_valuation) / btc_valuation * 100
        else:
            print("""btc大""")
            per_diff_res = (btc_valuation - usdt_valuation) / usdt_valuation * 100
        print("per_diff_res", per_diff_res, change_ratio)
        if per_diff_res > change_ratio:  # 如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
            print("两个币种估值差大于2% 开始全部撤单")
            is_cancel_order = self._cancel_open_orders(symbol)
            if is_cancel_order:
                print("撤单成功 开始吃单")
                strategy = PendingStrategy(self.exchange, self.db_session, self.config)
                is_position_order = strategy.execute(symbol)  # 开始吃单平衡
                if is_position_order:
                    print("吃单成功 重新挂单")
                    is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)
                    if is_pending_order:
                        print("已重新挂单")
                        return True
            return True
        
        buy_order_details_arr = []
        sell_order_details_arr = []
        buy_order_id = generate_client_order_id('Zx1')
        sell_order_id = generate_client_order_id('Zx2')
        if btc_valuation > usdt_valuation:
            print("btc大于usdt,开始出售")
            sell_orders_detais = self._place_sell_order(symbol, sell_orders_number, selling_price, sell_order_id)
            if sell_orders_detais:
                sell_order_details_arr = sell_orders_detais
                sell_order_details_arr['amount'] = sell_orders_number
                sell_order_details_arr['price'] = selling_price
                sell_order_details_arr['order_id'] = sell_orders_detais['id']
                sell_order_details_arr['client_order_id'] = sell_order_id
                buy_orders_detais = self._place_buy_order(symbol, buy_orders_number, buying_price, buy_order_id)
                if buy_orders_detais:
                    buy_order_details_arr = buy_orders_detais
                    buy_order_details_arr['amount'] = buy_orders_number
                    buy_order_details_arr['price'] = buying_price
                    buy_order_details_arr['order_id'] = buy_orders_detais['id']
                    buy_order_details_arr['client_order_id'] = buy_order_id

        if btc_valuation < usdt_valuation:
            print("btc小于usdt,开始购买")
            buy_orders_detais = self._place_buy_order(symbol, buy_orders_number, buying_price, buy_order_id)
            if buy_orders_detais:
                buy_order_details_arr = buy_orders_detais
                buy_order_details_arr['amount'] = buy_orders_number
                buy_order_details_arr['price'] = buying_price
                buy_order_details_arr['order_id'] = buy_orders_detais['id']
                buy_order_details_arr['client_order_id'] = buy_order_id
                sell_orders_detais = self._place_sell_order(symbol, sell_orders_number, selling_price, sell_order_id)
                if sell_orders_detais:
                    sell_order_details_arr = sell_orders_detais
                    sell_order_details_arr['amount'] = sell_orders_number
                    sell_order_details_arr['price'] = selling_price
                    sell_order_details_arr['order_id'] = sell_orders_detais['id']
                    sell_order_details_arr['client_order_id'] = sell_order_id

        if buy_order_details_arr and sell_order_details_arr and len(buy_order_details_arr) > 0 and len(sell_order_details_arr) > 0:
            print("下单成功")
            new_valuation = self._get_valuation(symbol)
            now = datetime.now()
            timestamp = now.strftime('%Y-%m-%d %H:%M:%S')
            insert_buy_order_data = {
                'exchange': exchange,
                'product_name': symbol,
                'symbol': symbol,
                'order_id': buy_order_details_arr['order_id'],
                'order_number': buy_order_details_arr['client_order_id'],
                'type': 1,
                'order_type': 'limit',
                'amount': buy_order_details_arr['amount'],
                'price': buy_order_details_arr['price'],
                'currency1': new_valuation['btc_balance'],
                'currency2': new_valuation['usdt_balance'],
                'clinch_currency1': valuation['btc_balance'] + buy_order_details_arr['amount'],
                'clinch_currency2': valuation['usdt_balance'] - buy_order_details_arr['amount'] * buy_order_details_arr['price'],
                'status': OrderStatus.PENDING.value,
                'time': timestamp,
                'up_time': timestamp,
            }
            is_set_buy_res = self.crud.create_piggybank_pendord(insert_buy_order_data)
            if is_set_buy_res:
                now = datetime.now()
                timestamp = now.strftime('%Y-%m-%d %H:%M:%S')
                insert_sell_order_data = {
                    'exchange': exchange,
                    'product_name': symbol,
                    'symbol': symbol,
                    'order_id': sell_order_details_arr['order_id'],
                    'order_number': sell_order_details_arr['client_order_id'],
                    'type': 2,
                    'order_type': 'limit',
                    'amount': sell_order_details_arr['amount'],
                    'price': sell_order_details_arr['price'],
                    'currency1': new_valuation['btc_balance'],
                    'currency2': new_valuation['usdt_balance'],
                    'clinch_currency1': valuation['btc_balance'] - sell_order_details_arr['amount'],
                    'clinch_currency2': valuation['usdt_balance'] + sell_order_details_arr['amount'] * sell_order_details_arr['price'],
                    'status': OrderStatus.PENDING.value,
                    'time': timestamp,
                    'up_time': timestamp,
                }
                is_set_sell_res = self.crud.create_piggybank_pendord(insert_sell_order_data)
                if is_set_sell_res:
                    return True

    def _place_sell_order(self, symbol, amount=None, price=None, clorder_id=None):
        """下限价卖单"""
        print(f"[挂限价卖单] 数量: {amount}, 价格: {price}")
        result = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            price=float(price),
            amount=float(amount),
            params={'tdMode': 'cross', 'clOrdId': clorder_id}
        )
        return result

    def _place_buy_order(self, symbol, amount=None, price=None, clorder_id=None):
        """下限价买单"""
        print(f"[挂限价买单] 数量: {amount}, 价格: {price}")
        result = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            price=float(price),
            amount=float(amount),
            params={'tdMode': 'cross', 'clOrdId': clorder_id}
        )
        return result