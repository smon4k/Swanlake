from abc import ABC, abstractmethod
from typing import Dict, Optional
from exchanges.base_exchange import BaseExchange
from db.crud import CRUD

class BaseStrategy(ABC):
    def __init__(self, exchange: BaseExchange, db_session):
        self.exchange = exchange
        self.crud = CRUD(db_session)
    
    @abstractmethod
    def execute(self, symbol: str) -> bool:
        """执行策略"""
        pass
    
    def get_exchange_name(self) -> str:
        return self.exchange.get_exchange_name()