from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple

from pyapi.piggybank.db.models import Piggybank
from pyapi.piggybank.strategies.init import safe_float
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id


class BalanceStrategy:
    def __init__(self, config, exchange):
        self.config = config
        self.exchange = exchange
        self.balances = {}

    def execute(self, symbol):
        # symbol = self.config.symbol
        base_token, quote_token = symbol.split('/')

        # Step 1: 获取估值
        valuation = self.get_token_valuation()
        base_valuation = valuation.get(base_token, {}).get('usdt_value', 0)
        quote_valuation = valuation.get(quote_token, {}).get('usdt_value', 0)

        if base_valuation == 0 or quote_valuation == 0:
            print("估值数据异常，跳过本轮")
            return

        valuation_diff = abs(base_valuation - quote_valuation)
        valuation_ratio = valuation_diff / max(base_valuation, quote_valuation)

        if valuation_ratio >= Decimal('0.02'):
            print(f"[估值不平衡] {base_token}:{base_valuation}, {quote_token}:{quote_valuation}, 差异比: {valuation_ratio:.2%}")
            self._cancel_open_orders(symbol)
            self._market_eat_order(base_token, quote_token, valuation)

            # 重新挂单
            market_info = self.exchange.get_market_info(symbol)
            self._place_balancing_orders(market_info, valuation)
        else:
            print(f"[估值平衡] 差异比: {valuation_ratio:.2%}，无需调整")

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
                self._place_balancing_orders(market_info, valuation)

    def _cancel_open_orders(self, symbol):
        print(f"[撤单] 撤销 {symbol} 所有挂单")
        self.exchange.cancel_all_orders(symbol)

    def _market_eat_order(self, base_token, quote_token, valuation):
        """市价吃掉多的币种"""
        symbol = self.config.symbol
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

    def _place_balancing_orders(self, market_info, valuation):
        """根据估值重挂买卖单"""
        base_token, quote_token = self.config.symbol.split('/')
        base_usdt = valuation.get(base_token, {}).get('usdt_value', 0)
        quote_usdt = valuation.get(quote_token, {}).get('usdt_value', 0)

        if base_usdt > quote_usdt:
            self._place_sell_order(market_info, valuation)
        else:
            self._place_buy_order(market_info, valuation)

    def _place_sell_order(self, market_info, valuation):
        """下限价卖单"""
        base_token, _ = self.config.symbol.split('/')
        amount = valuation[base_token].get('balance', 0)
        price = market_info.get('best_bid', 0)
        if amount <= 0 or price <= 0:
            print("[限价卖单跳过] 无有效数量或价格")
            return

        print(f"[挂限价卖单] 数量: {amount}, 价格: {price}")
        self.exchange.create_order(
            symbol=self.config.symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            price=float(price),
            amount=float(amount),
            params={'tdMode': 'cross'}
        )

    def _place_buy_order(self, market_info, valuation):
        """下限价买单"""
        _, quote_token = self.config.symbol.split('/')
        quote_balance = valuation[quote_token].get('balance', 0)
        ask_price = market_info.get('best_ask', 0)
        if quote_balance <= 0 or ask_price <= 0:
            print("[限价买单跳过] 无有效余额或价格")
            return

        amount = quote_balance / ask_price
        print(f"[挂限价买单] 数量: {amount}, 价格: {ask_price}")
        self.exchange.create_order(
            symbol=self.config.symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            price=float(ask_price),
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

    def get_token_valuation(self):
        """返回格式：
        {
            "BTC": {"balance": 0.1, "usdt_value": 5000},
            "USDT": {"balance": 5000, "usdt_value": 5000}
        }
        """
        # 请实现你自己的估值计算逻辑
        raise NotImplementedError("请实现 get_token_valuation 方法")