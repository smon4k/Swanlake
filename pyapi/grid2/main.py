import asyncio
from decimal import Decimal, getcontext
from database import Database
from trading_bot_config import TradingBotConfig
from signal_processing_task import SignalProcessingTask
from price_monitoring_task import PriceMonitoringTask
from common_functions import get_exchange, get_market_price

# 设置Decimal精度
getcontext().prec = 8

class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        self.config = config
        self.db = Database(config.db_config)
        self.signal_task = SignalProcessingTask(config, self.db)
        self.price_task = PriceMonitoringTask(config, self.db)

    async def initialize_accounts(self):
        """初始化所有活跃账户"""
        conn = None
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id FROM accounts")
                accounts = cursor.fetchall()
                for account in accounts:
                    account_id = account['id']
                    await self.db.get_account_info(account_id)  # 加载到缓存
                    await get_exchange(self, account_id)  # 初始化交易所实例
        except Exception as e:
            print(f"初始化账户失败: {e}")
        finally:
            if conn:
                conn.close()

    async def run(self):
        # 初始化账户和交易所
        await self.initialize_accounts()

        """运行主程序"""
        # signal_task = asyncio.create_task(self.signal_task.signal_processing_task())
        price_task = asyncio.create_task(self.price_task.price_monitoring_task())

        # await asyncio.gather(signal_task, price_task)
        await asyncio.gather(price_task)

if __name__ == "__main__":
    config = TradingBotConfig()
    bot = OKXTradingBot(config)

    try:
        asyncio.run(bot.run())
    except KeyboardInterrupt:
        print("程序安全退出")