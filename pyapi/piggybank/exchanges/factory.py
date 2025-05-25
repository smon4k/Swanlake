from .okx import OkxExchange
from .binance import BinanceExchange
from config.config import ExchangeType

class ExchangeFactory:
    @staticmethod
    def create_exchange(exchange_type: ExchangeType):
        if exchange_type == ExchangeType.OKX:
            return OkxExchange()
        elif exchange_type == ExchangeType.BINANCE:
            return BinanceExchange()
        else:
            raise ValueError(f"Unsupported exchange type: {exchange_type}")