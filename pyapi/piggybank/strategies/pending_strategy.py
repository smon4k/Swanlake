# 挂单 策略
from datetime import datetime
from decimal import Decimal
from config.constants import OrderStatus, OrderType, OrderSide
from pyapi.piggybank.strategies.balanced_strategy import BalancedStrategy
from utils.helpers import generate_client_order_id
from pyapi.piggybank.strategies.base_strategy import BaseStrategy

class PendingStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol):
        """
        执行策略主流程：
        1. 获取市场信息与估值
        2. 根据当前估值与配置执行挂单逻辑
        """
        try:
            base_token, quote_token = symbol.split('-')
            exchange = self.get_exchange_name()
            market_info = self.exchange.get_market_info(symbol)
            valuation = self._get_valuation(symbol)
            return self._place_balancing_orders(market_info, valuation, symbol)
        except Exception as e:
            print(f"[{exchange}] 策略执行异常: {e}")
            return False

    def _place_balancing_orders(self, market_info, valuation, symbol):
        """
        挂单主逻辑：
        - 根据估值与配置计算买卖价格和数量
        - 判断是否吃单或强制重新挂单
        - 发出限价挂单
        """
        exchange = self.get_exchange_name()
        change_ratio = Decimal(str(self.config.CHANGE_RATIO))
        balance_ratio_arr = list(map(Decimal, self.config.BALANCE_RATIO.split(':')))

        last_price = self._get_last_price(exchange, symbol, valuation)
        buy_price, sell_price = self._calculate_trade_prices(last_price, change_ratio)

        btc_balance = Decimal(valuation['btc_balance'])
        usdt_balance = Decimal(valuation['usdt_balance'])
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])

        buy_amount, sell_amount = self._calculate_order_amounts(
            btc_balance, usdt_valuation, buy_price, sell_price, balance_ratio_arr
        )

        # 判断是否触发吃单条件（数量过小）
        min_size = Decimal(market_info['info'].get('minSz', '0'))
        if self._should_market_fill(buy_amount, sell_amount, min_size):
            print(f"[判断] 下单数量过小 (min: {min_size})，执行吃单逻辑")
            return self._re_place_orders(symbol, BalancedStrategy)

        # 判断是否触发强制重平衡（估值偏离）
        if self._should_force_rebalance(btc_valuation, usdt_valuation, change_ratio):
            print(f"[估值过大] 执行吃单逻辑")
            self._cancel_open_orders(symbol)
            return self._re_place_orders(symbol, BalancedStrategy)

        # 执行限价挂单
        return self._place_pending_orders(symbol, buy_price, buy_amount, sell_price, sell_amount, valuation)
    
    def _get_last_price(self, exchange, symbol, valuation):
        """
        获取参考价格：
        - 优先使用上次成交价
        - 若无成交价则使用当前市价
        """
        last_price = Decimal(str(self.crud.get_last_deal_price(exchange, symbol) or '0'))
        market_price = Decimal(valuation['btc_price'])
        return min(last_price, market_price) if last_price > 0 else market_price

    def _calculate_trade_prices(self, last_price, change_ratio):
        """
        根据变动比率计算买入价与卖出价
        """
        sell_price = last_price * (1 + change_ratio / Decimal('100'))
        buy_price = last_price * (1 - change_ratio / Decimal('100'))
        return buy_price, sell_price

    def _calculate_order_amounts(self, btc_balance, usdt_valuation, buy_price, sell_price, ratio_arr):
        """
        根据估值差和配置比例计算挂单数量
        """
        buy_valuation = buy_price * btc_balance
        sell_valuation = sell_price * btc_balance
        buy_diff = abs(usdt_valuation - buy_valuation)
        sell_diff = abs(usdt_valuation - sell_valuation)
        total_ratio = ratio_arr[0] + ratio_arr[1]
        buy_amount = (ratio_arr[1] * buy_diff / total_ratio) / buy_price
        sell_amount = (ratio_arr[0] * sell_diff / total_ratio) / sell_price
        return buy_amount, sell_amount

    def _should_market_fill(self, buy_amount, sell_amount, min_size):
        """
        判断是否因为挂单量过小而改为吃单
        """
        return buy_amount < min_size or sell_amount < min_size

    def _should_force_rebalance(self, btc_valuation, usdt_valuation, change_ratio):
        """
        判断两个币种估值差是否超过配置阈值，决定是否强制重平衡
        """
        diff_percent = abs(btc_valuation - usdt_valuation) / min(btc_valuation, usdt_valuation) * 100
        return diff_percent > change_ratio

    def _place_pending_orders(self, symbol, buy_price, buy_amount, sell_price, sell_amount, old_valuation):
        """
        实际执行限价挂单，并记录挂单信息至数据库
        """
        exchange = self.get_exchange_name()
        buy_id = generate_client_order_id('Zx1')
        sell_id = generate_client_order_id('Zx2')

        # 下买单
        buy_result = self._place_buy_order(symbol, buy_amount, buy_price, buy_id)
        if not buy_result:
            return False
        # 下卖单
        sell_result = self._place_sell_order(symbol, sell_amount, sell_price, sell_id)
        if not sell_result:
            return False

        new_valuation = self._get_valuation(symbol)
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        # 构造买单记录
        buy_data = {
            'exchange': exchange, 'product_name': symbol, 'symbol': symbol,
            'order_id': buy_result['id'], 'order_number': buy_id,
            'type': 1, 'order_type': 'limit', 'amount': buy_amount, 'price': buy_price,
            'currency1': new_valuation['btc_balance'], 'currency2': new_valuation['usdt_balance'],
            'clinch_currency1': old_valuation['btc_balance'] + buy_amount,
            'clinch_currency2': old_valuation['usdt_balance'] - buy_amount * buy_price,
            'status': OrderStatus.PENDING.value, 'time': timestamp, 'up_time': timestamp
        }

        # 构造卖单记录
        sell_data = {
            'exchange': exchange, 'product_name': symbol, 'symbol': symbol,
            'order_id': sell_result['id'], 'order_number': sell_id,
            'type': 2, 'order_type': 'limit', 'amount': sell_amount, 'price': sell_price,
            'currency1': new_valuation['btc_balance'], 'currency2': new_valuation['usdt_balance'],
            'clinch_currency1': old_valuation['btc_balance'] - sell_amount,
            'clinch_currency2': old_valuation['usdt_balance'] + sell_amount * sell_price,
            'status': OrderStatus.PENDING.value, 'time': timestamp, 'up_time': timestamp
        }

        return self.crud.create_piggybank_pendord(buy_data) and self.crud.create_piggybank_pendord(sell_data)

    def _place_sell_order(self, symbol, amount=None, price=None, clorder_id=None):
        """发送限价卖单"""
        print(f"[挂限价卖单] 数量: {amount}, 价格: {price}")
        return self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            price=float(price),
            amount=float(amount),
            params={'clOrdId': clorder_id}
        )

    def _place_buy_order(self, symbol, amount=None, price=None, clorder_id=None):
        """发送限价买单"""
        print(f"[挂限价买单] 数量: {amount}, 价格: {price}")
        return self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            price=float(price),
            amount=float(amount),
            params={'clOrdId': clorder_id}
        )
