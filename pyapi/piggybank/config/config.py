from decimal import Decimal
import os
from enum import Enum
from dotenv import load_dotenv

load_dotenv()

class ExchangeType(Enum):
    OKX = "okx"
    BINANCE = "binance"

class Config:
    # 通用配置
    CHANGE_RATIO = Decimal(os.getenv('CHANGE_RATIO', '0.5'))  # 涨跌比例 2%
    VALUATION_THRESHOLD = Decimal(os.getenv('VALUATION_THRESHOLD', '0.5'))  # 两个币种估值大于多少
    BALANCE_RATIO = os.getenv('BALANCE_RATIO', '1:1')  # 平衡比例
    
    # 数据库配置
    DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
    DB_PORT = os.getenv('DB_PORT', '3306')
    DB_USER = os.getenv('DB_USER', 'root')
    DB_PASSWORD = os.getenv('DB_PASSWORD', '123456')
    DB_NAME = os.getenv('DB_NAME', 'piggybank')