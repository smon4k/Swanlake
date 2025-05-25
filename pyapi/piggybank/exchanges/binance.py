import ccxt
from typing import Dict, List, Optional
from .base_exchange import BaseExchange
from config.binance_config import BinanceConfig

class BinanceExchange(BaseExchange):
    def __init__(self):
        self.config = BinanceConfig()
        self.client = ccxt.binance({
            'apiKey': self.config.API_KEY,
            'secret': self.config.SECRET_KEY,
            'enableRateLimit': True
        })
    
    def get_balance(self) -> Dict:
        return self.client.fetch_balance()
    
    def get_ticker(self, symbol: str) -> Dict:
        return self.client.fetch_ticker(symbol)
    
    def get_market_info(self, symbol: str) -> Dict:
        markets = self.client.fetch_markets()
        return next((m for m in markets if m['symbol'] == symbol), None)
    
    def create_order(self, symbol: str, order_type: str, side: str, 
                   amount: float, price: Optional[float] = None) -> Dict:
        return self.client.create_order(symbol, order_type, side, amount, price)
    
    def cancel_order(self, order_id: str, symbol: str) -> bool:
        result = self.client.cancel_order(order_id, symbol)
        return 'status' in result and result['status'] == 'canceled'
    
    def fetch_order(self, order_id: str, symbol: str) -> Dict:
        return self.client.fetch_order(order_id, symbol)
    
    def fetch_open_orders(self, symbol: str = None) -> List[Dict]:
        return self.client.fetch_open_orders(symbol)
    
    def get_exchange_name(self) -> str:
        return 'Binance'