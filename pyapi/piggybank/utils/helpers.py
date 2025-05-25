import uuid
from datetime import datetime

def generate_client_order_id(prefix: str = 'Zx') -> str:
    """生成客户端订单ID"""
    date_str = datetime.now().strftime('%Y%m%d')
    unique_part = str(uuid.uuid4().int)[:8]
    return f"{prefix}{date_str}{unique_part}"

def normalize_symbol(exchange: str, symbol: str) -> str:
    """标准化交易对符号"""
    if exchange.lower() == 'okx':
        return symbol.replace('/', '-')
    elif exchange.lower() == 'binance':
        return symbol.replace('-', '/')
    return symbol