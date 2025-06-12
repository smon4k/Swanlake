from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple

from pyapi.piggybank.db.models import Piggybank
from pyapi.piggybank.strategies.init import safe_float
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id
from pyapi.piggybank.strategies.pending_strategy import PendingStrategy

class BalanceStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config
        self.balances = {}

    def execute(self, symbol):
        # symbol = self.config.symbol
        base_token, quote_token = symbol.split('-')
        # Step 1: 获取估值
        valuation = self._get_valuation(symbol)
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])
        print(f"[估值] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
        if btc_valuation == 0 or usdt_valuation == 0:
            print("估值数据异常，跳过本轮")
            return

        # 比较两个估值的大小（相当于PHP的bccomp）
        min_max_res = 1 if usdt_valuation > btc_valuation else -1 if usdt_valuation < btc_valuation else 0            
        # 计算百分比差异（保持与PHP相同的逻辑）
        if min_max_res == 1:  # usdt估值更大
            valuation_ratio = (usdt_valuation - btc_valuation) / btc_valuation * 100
        else:  # btc估值更大或相等
            valuation_ratio = (btc_valuation - usdt_valuation) / usdt_valuation * 100

        # 如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
        if valuation_ratio > self.config.CHANGE_RATIO:
            print(f"[估值不平衡] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}, 差异比: {valuation_ratio}")
            self._cancel_open_orders(symbol)
            strategy = PendingStrategy(self.exchange, self.db_session, self.config)
            success = strategy.execute('BTC-USDT')
            print("吃单结果", success)
            if not success:
                print("吃单失败，跳过本轮")
                return

            # 重新挂单
            market_info = self.exchange.get_market_info(symbol)
            print("market_info", market_info)
            self._place_balancing_orders(market_info, valuation, symbol)
        else:
            print(f"[估值平衡] 差异比: {valuation_ratio:.2%}，无需调整")
        print("估值检测完成，开始成交检测")
        
        # Step 2: 获取当前挂单信息
        open_orders = self.exchange.fetch_open_orders(symbol)
        has_filled = any(order['filled'] > 0 for order in open_orders)

        if has_filled:
            print("[成交检测] 有订单已成交，开始盈亏配对处理")
            self._process_filled_orders(open_orders)
        else:
            balance_changed = self._check_balance_changed()
            if balance_changed:
                print("[余额变化] 有成交但未记录，撤单并重新下单")
                self._cancel_open_orders(symbol)
                market_info = self.exchange.get_market_info(symbol)
                self._place_balancing_orders(market_info, valuation, symbol)

    def _cancel_open_orders(self, symbol):
        print(f"[撤单] 撤销 {symbol} 所有挂单")
        open_orders = self.exchange.fetch_open_orders(symbol)
        for order in open_orders:
            client_order_id = order['clientOrderId']
            print(f"[撤单] 撤销订单 {client_order_id}")
            self.exchange.cancel_order(client_order_id, symbol)

    def _market_eat_order(self, base_token, quote_token, valuation, symbol):
        """市价吃掉多的币种"""
        if valuation[base_token]['usdt_value'] > valuation[quote_token]['usdt_value']:
            token_to_sell = base_token
            side = OrderSide.SELL.value
        else:
            token_to_sell = quote_token
            side = OrderSide.SELL.value

        amount = valuation[token_to_sell].get('balance', 0)
        if amount <= 0:
            print(f"[吃单跳过] {token_to_sell} 无余额")
            return

        print(f"[市价吃单] 卖出 {token_to_sell} 数量: {amount}")
        self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.MARKET.value,
            side=side,
            amount=float(amount),
            params={'tdMode': 'cross'}
        )

    def _place_balancing_orders(self, market_info, valuation, symbol):
        """根据估值重挂买卖单"""
        print("valuation", valuation)
        base_token, quote_token = symbol.split('-')
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])
        print(f"[估值] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
        if usdt_valuation > btc_valuation:
            self._place_sell_order(market_info, valuation, symbol)
        else:
            self._place_buy_order(market_info, valuation, symbol)

    def _place_sell_order(self, market_info, valuation, symbol):
        """下限价卖单"""
        usdt_balance = Decimal(valuation['usdt_balance'])
        price = market_info.get('best_bid', 0)
        if usdt_balance <= 0 or price <= 0:
            print("[限价卖单跳过] 无有效数量或价格")
            return

        print(f"[挂限价卖单] 数量: {usdt_balance}, 价格: {price}")
        self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            price=float(price),
            amount=float(usdt_balance),
            params={'tdMode': 'cross'}
        )

    def _place_buy_order(self, market_info, valuation, symbol):
        """下限价买单"""
        btc_balance = Decimal(valuation['btc_balance'])
        btc_price = Decimal(valuation['btc_price'])
        min_size = Decimal(market_info['info'].get('minSz', 0))
        if btc_balance <= 0 or btc_price <= 0:
            print("[限价买单跳过] 无有效余额或价格")
            return

        amount = btc_balance / btc_price
        print(f"[挂限价买单] 数量: {amount}, 价格: {btc_price}")
        if amount < min_size:
            self._cancel_open_orders(symbol)
            # strategy = PendingStrategy(self.exchange, self.db_session, self.config)
            # success = strategy.execute('BTC-USDT')
            # print("吃单结果", success)
            # if not success:
            #     print("吃单失败，跳过本轮")
            #     return

            # # 重新挂单
            # market_info = self.exchange.get_market_info(symbol)
            # print("market_info", market_info)
            # self._place_balancing_orders(market_info, valuation, symbol)
            return
        self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            price=float(btc_price),
            amount=float(amount),
            params={'tdMode': 'cross'}
        )

    def _check_balance_changed(self):
        """检测是否有余额变动"""
        new_balance = self.exchange.fetch_balance()
        changed = False
        for token, info in new_balance.items():
            old = self.balances.get(token, {}).get('total', 0)
            new = info.get('total', 0)
            if abs(new - old) > 1e-8:
                changed = True
                break
        self.balances = new_balance
        return changed

    def _process_filled_orders(self, orders):
        """盈亏配对逻辑（你自己已有实现）"""
        # 请在此处填入你已有的盈亏配对逻辑
        pass