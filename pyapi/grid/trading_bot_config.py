from decimal import Decimal

import pymysql

class TradingBotConfig:
    """交易机器人配置类"""
    def __init__(self):
        # 仓位配置
        self.position_percent = Decimal('0.8')  # 开仓比例(80%)
        self.max_position = Decimal('100')      # 最大仓位张数

        # 止损止盈配置
        self.stop_profit_loss = Decimal('0.007')  # 0.7%

        # 网格交易配置
        self.grid_step = Decimal('0.002')       # 0.2%
        self.grid_sell_percent = Decimal('0.05')  # 5%
        self.grid_buy_percent = Decimal('0.04')   # 4%

        # 数据库配置
        self.db_config = {
            "host": "localhost",
            "user": "root",
            "password": "123456",
            "database": "trading_bot",
            "cursorclass": pymysql.cursors.DictCursor
        }

        # 交易配置
        self.check_interval = 3  # 价格检查间隔(秒)