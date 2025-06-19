from abc import ABC, abstractmethod
from decimal import Decimal
from typing import Dict, Optional, Tuple
from exchanges.base_exchange import BaseExchange
from db.crud import CRUD
from pyapi.piggybank.config.constants import OrderSide

class BaseStrategy(ABC):
    def __init__(self, exchange: BaseExchange, db_session):
        self.exchange = exchange
        self.db_session = db_session
        self.crud = CRUD(db_session)
    
    @abstractmethod
    def execute(self, symbol: str) -> bool:
        """执行策略"""
        pass
    
    def get_exchange_name(self) -> str:
        return self.exchange.get_exchange_name()
    
    def _get_valuation(self, symbol: str) -> Dict:
        balance = self.exchange.get_balance()
        ticker = self.exchange.get_ticker(symbol)
        currency1, currency2 = symbol.split('-') if '-' in symbol else symbol.split('/')

        details = balance['info']['data'][0].get('details', [])
        btc_balance = sum(
            Decimal(asset.get('eq', '0')) for asset in details if asset.get('ccy') == currency1
        )
        # print("valuation btc_balance", btc_balance, currency1)

        usdt_balance = sum(
            Decimal(asset.get('eq', '0')) for asset in details if asset.get('ccy') == currency2
        )
        # print("valuation usdt_balance", usdt_balance, currency2)

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