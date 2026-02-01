import ccxt
from typing import Dict, List, Optional
from .base_exchange import BaseExchange
from config.okx_config import OkxConfig


class OkxExchange(BaseExchange):
    def __init__(self):
        self.config = OkxConfig()
        client_config = {
            "apiKey": self.config.API_KEY,
            "secret": self.config.SECRET_KEY,
            "password": self.config.PASSPHRASE,
            "enableRateLimit": True,
            "options": {
                "defaultType": "spot",  # 默认市场类型为现货
                "adjustForTimeDifference": True,
            },
        }

        # ✅ 如果是本地运行环境，添加代理
        # if getattr(self.config, 'IS_LOCAL', False):
        #     client_config['proxies'] = {
        #         'http': 'http://127.0.0.1:7890',
        #         'https': 'http://127.0.0.1:7890',
        #     }

        self.client = ccxt.okx(client_config)
        is_simulation = self.config.IS_SIMULATION
        if is_simulation == "1":  # 1表示模拟环境
            self.client.set_sandbox_mode(True)

    def get_balance(self) -> Dict:
        return self.client.fetch_balance()

    def get_ticker(self, symbol: str) -> Dict:
        return self.client.fetch_ticker(symbol)

    def is_spot_market(self, symbol: str) -> bool:
        market = self.client.market(symbol)
        return market.get("type") == "spot"

    def get_market_info(self, symbol: str) -> Dict:
        markets = self.client.fetch_markets_by_type("SPOT", {"instId": symbol})
        return markets[0] if markets else None

    def create_order(
        self,
        symbol: str,
        order_type: str,
        side: str,
        amount: float,
        price: Optional[float] = None,
        params={},
    ) -> Dict:
        return self.client.create_order(symbol, order_type, side, amount, price, params)

    def cancel_order(self, order_id: str, symbol: str) -> bool:
        result = self.client.cancel_order(order_id, symbol)
        return result["info"]["sCode"] == "0"

    def fetch_order(self, order_id: str, symbol: str) -> Dict:
        return self.client.fetch_order(order_id, symbol)

    def fetch_open_orders(self, symbol: str = None) -> List[Dict]:
        return self.client.fetch_open_orders(symbol)

    def get_exchange_name(self) -> str:
        return "OKX"

    def normalize_symbol(self, symbol: str) -> str:
        """将BTC-USDT格式化为OKX的标准格式"""
        return symbol.replace("/", "-")
