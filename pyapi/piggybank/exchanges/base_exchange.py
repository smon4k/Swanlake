from abc import ABC, abstractmethod
from typing import Dict, List, Optional

class BaseExchange(ABC):
    @abstractmethod
    def get_balance(self) -> Dict:
        """获取账户余额"""
        pass
    
    @abstractmethod
    def get_ticker(self, symbol: str) -> Dict:
        """获取交易对行情"""
        pass
    
    @abstractmethod
    def get_market_info(self, symbol: str) -> Dict:
        """获取市场信息"""
        pass
    
    @abstractmethod
    def create_order(self, symbol: str, order_type: str, side: str, 
                   amount: float, price: Optional[float] = None, params={}) -> Dict:
        """创建订单"""
        pass
    
    @abstractmethod
    def cancel_order(self, order_id: str, symbol: str) -> bool:
        """取消订单"""
        pass
    
    @abstractmethod
    def fetch_order(self, order_id: str, symbol: str) -> Dict:
        """获取订单详情"""
        pass
    
    @abstractmethod
    def fetch_open_orders(self, symbol: str = None) -> List[Dict]:
        """获取未成交订单列表"""
        pass
    
    @abstractmethod
    def get_exchange_name(self) -> str:
        """获取交易所名称"""
        pass
    
    @abstractmethod
    def normalize_symbol(self, symbol: str) -> str:
        """标准化交易对符号"""
        pass