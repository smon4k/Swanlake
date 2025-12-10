from decimal import Decimal
from dotenv import load_dotenv
import os

import pymysql

class TradingBotConfig:
    """交易机器人配置类"""
    def __init__(self):

        # 加载.env文件中的环境变量
        load_dotenv()

        # 仓位配置
        self.multiple = Decimal('3')             # 多空倍数
        self.position_percent = Decimal('0.8')  # 开仓比例(80%)
        self.max_position = Decimal('2000')      # 最大仓位数usdt
        self.total_position = Decimal('5000')      # 总仓位数

        # 止损止盈配置
        self.stop_profit_loss = Decimal('0.007')  # 0.7%

        # 网格交易配置
        self.grid_step = Decimal('0.002')       # 0.2%
        # self.grid_sell_percent = Decimal('0.05')  # 5%
        # self.grid_buy_percent = Decimal('0.04')   # 4%
        self.grid_percent_config = { # 网格比例配置
            'long': {   # 做多逻辑：低买高卖
                'buy': Decimal('0.04'),
                'sell': Decimal('0.05')
            },
            'short': {  # 做空逻辑：高卖低买
                'buy': Decimal('0.05'),
                'sell': Decimal('0.04')
            }
        }
        self.commission_price_difference = 50  # 委托价格差值(USDT)

        # 数据库配置
        self.db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "user": os.getenv("DB_USER", "root"),
            "password": os.getenv("DB_PASSWORD", "123456"),
            "database": os.getenv("DB_DATABASE", "trading_bot"),
            "cursorclass": pymysql.cursors.DictCursor
        }

        # 交易配置
        self.check_interval = 3  # 价格检查间隔(秒
        self.signal_check_interval = 1  # 信号检查间隔(秒