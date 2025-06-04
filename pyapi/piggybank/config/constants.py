from enum import Enum

class OrderType(Enum):
    MARKET = 'market'
    LIMIT = 'limit'

class OrderSide(Enum):
    BUY = 'buy'
    SELL = 'sell'

class OrderStatus(Enum):
    PENDING = 1
    FILLED = 2
    CANCELED = 3
    PARTIALLY_FILLED = 4