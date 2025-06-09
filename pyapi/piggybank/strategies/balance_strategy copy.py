from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple

from pyapi.piggybank.db.models import Piggybank
from pyapi.piggybank.strategies.init import safe_float
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id


class BalanceStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol: str) -> bool:
        """执行平衡策略"""
        try:
            normalized_symbol = self.exchange.normalize_symbol(symbol)
            valuation = self._get_valuation(normalized_symbol)
            print("valuation", valuation)
            market_info = self.exchange.get_market_info(normalized_symbol)
            if not market_info:
                print(f"[{self.get_exchange_name()}] 无法获取市场信息")
                return False

            min_size = Decimal(market_info['info'].get('minSz', '0'))
            change_ratio = self._calculate_change_ratio(valuation, normalized_symbol)
            print("change_ratio", change_ratio)
            if change_ratio > self.config.CHANGE_RATIO:
                client_order_id = generate_client_order_id()
                print(f"[{self.get_exchange_name()}] 下单ID: {client_order_id}")

                btc_amount, usdt_amount = self._calculate_order_amounts(valuation)
                print("BTC 订单数量:", btc_amount, "USDT 订单数量:", usdt_amount)

                if btc_amount > min_size:
                    print("btc_amount", btc_amount)
                    return self._place_sell_order(normalized_symbol, float(btc_amount), client_order_id, market_info, valuation)
                elif usdt_amount > min_size:
                    print("usdt_amount", usdt_amount)
                    return self._place_buy_order(normalized_symbol, float(usdt_amount), client_order_id, market_info, valuation)
                else:
                    print(f"[{self.get_exchange_name()}] 下单数量小于最小下单量 {min_size}，停止下单")
                    return False
            else:
                print(f"[{self.get_exchange_name()}] 涨跌幅度小于 {self.config.CHANGE_RATIO}%，停止下单")
                return False
        except Exception as e:
            print(f"[{self.get_exchange_name()}] 平衡策略执行错误: {str(e)}")
            return False

    def _get_valuation(self, symbol: str) -> Dict:
        balance = self.exchange.get_balance()
        ticker = self.exchange.get_ticker(symbol)

        currency1, currency2 = symbol.split('-') if '-' in symbol else symbol.split('/')

        details = balance['info']['data'][0].get('details', [])

        btc_balance = sum(
            Decimal(asset.get('availBal', '0')) for asset in details if asset.get('ccy') == currency1
        )
        print("btc_balance", btc_balance, currency1)

        usdt_balance = sum(
            Decimal(asset.get('availBal', '0')) for asset in details if asset.get('ccy') == currency2
        )
        print("usdt_balance", usdt_balance, currency2)

        btc_price = Decimal(str(ticker['last']))
        btc_valuation = btc_balance * btc_price
        usdt_valuation = usdt_balance

        return {
            'btc_price': btc_price,
            'btc_balance': btc_balance,
            'usdt_balance': usdt_balance,
            'btc_valuation': btc_valuation,
            'usdt_valuation': usdt_valuation
        }

    def _calculate_change_ratio(self, valuation: Dict, symbol: str) -> float:
        last_balanced = self.crud.get_last_piggybank(self.get_exchange_name(), symbol)
        btc_val = Decimal(str(valuation['btc_valuation']))
        usdt_val = Decimal(str(valuation['usdt_valuation']))

        if last_balanced:
            last_val = Decimal(str(last_balanced.balanced_valuation))
        else:
            last_val = usdt_val if usdt_val > 0 else Decimal('1')

        ratio = (abs(btc_val - usdt_val) / last_val) * Decimal('100')
        return float(ratio)

    def _calculate_order_amounts(self, valuation: Dict) -> Tuple[float, float]:
        ratio_parts = [Decimal(x) for x in self.config.BALANCE_RATIO.split(':')]
        total_ratio = sum(ratio_parts)

        if valuation['btc_valuation'] > valuation['usdt_valuation']:
            diff = valuation['btc_valuation'] - valuation['usdt_valuation']
            sell_value = ratio_parts[0] * diff / total_ratio
            sell_amount = sell_value / valuation['btc_price'] if valuation['btc_price'] > 0 else Decimal('0')
            return float(sell_amount), 0.0
        else:
            diff = valuation['usdt_valuation'] - valuation['btc_valuation']
            buy_amount = ratio_parts[1] * diff / total_ratio
            return 0.0, float(buy_amount)

    def _place_sell_order(self, symbol: str, amount: float, client_order_id: str,
                          market_info: Dict, valuation: Dict) -> bool:
        order = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            amount=amount,
            params={'clOrdId': client_order_id, 'tdMode': 'cross'}
        )

        if order['info'].get('sCode', '0') == '0' or order.get('status') == 'filled':
            print(f"[{self.get_exchange_name()}] 卖出订单成功")
            return self._process_order_result(order, symbol, client_order_id, OrderSide.SELL.value, market_info, valuation)
        else:
            print(f"[{self.get_exchange_name()}] 卖出订单失败: {order}")
        return False

    def _place_buy_order(self, symbol: str, amount: float, client_order_id: str,
                         market_info: Dict, valuation: Dict) -> bool:
        order = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            amount=amount,
            params={'clOrdId': client_order_id, 'tdMode': 'cross'}
        )

        if order['info'].get('sCode', '0') == '0' or order.get('status') == 'filled':
            print(f"[{self.get_exchange_name()}] 买入订单成功")
            return self._process_order_result(order, symbol, client_order_id, OrderSide.BUY.value, market_info, valuation)
        else:
            print(f"[{self.get_exchange_name()}] 买入订单失败: {order}")
        return False

    def _process_order_result(self, order: Dict, symbol: str, client_order_id: str,
                              side: str, market_info: Dict, valuation: Dict) -> bool:
        order_details = self.exchange.fetch_order(order['id'], symbol)
        filled_amount = Decimal(str(order_details['info'].get('accFillSz', order_details.get('filled', 0))))
        avg_price = Decimal(str(order_details['info'].get('avgPx', order_details.get('average', 0))))
        last_price = Decimal(str(order_details['info'].get('fillPx', avg_price))) if order_details['info'].get('fillPx') else avg_price

        pair_id, profit = self._get_pair_info(side, last_price, filled_amount, symbol)
        new_valuation = self._get_valuation(symbol)

        order_data = {
            'exchange': self.get_exchange_name(),
            'product_name': symbol,
            'order_id': order['id'],
            'order_number': client_order_id,
            'td_mode': 'cross',
            'base_ccy': market_info['info'].get('baseCcy', symbol.split('-')[0]),
            'quote_ccy': market_info['info'].get('quoteCcy', symbol.split('-')[1]),
            'type': 1 if side == OrderSide.BUY.value else 2,
            'order_type': OrderType.MARKET.value,
            'amount': safe_float(order['amount']),
            'clinch_number': filled_amount,
            'price': last_price,
            'profit': profit,
            'pair': pair_id if pair_id else 0,
            'currency1': valuation['btc_balance'],
            'currency2': valuation['usdt_balance'],
            'balanced_valuation': new_valuation['usdt_valuation'],
            'make_deal_price': avg_price,
            'time': datetime.now()
        }

        order_record = self.crud.create_piggybank(order_data)

        if pair_id:
            self.crud.db.query(Piggybank).filter(Piggybank.id == pair_id).update({
                'pair': order_record.id,
                'profit': profit
            })
            self.crud.db.commit()

        return True

    def _get_pair_info(self, side: str, price: Decimal, amount: Decimal, symbol: str) -> Tuple[Optional[int], float]:
        last_order = self.crud.get_last_piggybank(self.get_exchange_name(), symbol)
        if not last_order or last_order.pair != 0:
            return None, 0.0

        # 卖出且卖价高于买价，或买入且买价低于卖价，计算利润
        if (side == OrderSide.SELL.value and price > last_order.price) or \
           (side == OrderSide.BUY.value and price < last_order.price):
            if side == OrderSide.SELL.value:
                profit = float(amount * (price - last_order.price))
            else:
                profit = float(last_order.clinch_number * (last_order.price - price))
            return last_order.id, profit

        return None, 0.0
