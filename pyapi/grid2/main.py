import asyncio
from decimal import Decimal, getcontext
import os
from database import Database
from trading_bot_config import TradingBotConfig
from signal_processing_task import SignalProcessingTask
from price_monitoring_task import PriceMonitoringTask
from common_functions import get_exchange
from aiohttp import web
from datetime import datetime, timezone

# 设置Decimal精度
getcontext().prec = 8

class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        self.config = config
        self.db = Database(config.db_config)
        self.signal_task = SignalProcessingTask(config, self.db)
        self.price_task = PriceMonitoringTask(config, self.db)
        self.app = web.Application()
        self.app.add_routes([
            web.post('/insert_signal', self.handle_insert_signal)  # 新增路由
        ])

    async def handle_insert_signal(self, request):
        """处理写入信号的API请求"""
        try:
            data = await request.json()
            # 解析请求体中的参数
            symbol = data.get('symbol')
            account_id = os.getenv("ACCOUNT_ID", 1)
            direction = 'long' if data.get('side') == 'buy' else 'short'  # 假设请求体中的'side'对应数据库中的'direction'
            price = Decimal(data.get('price', 0))  # 假设请求体中的'price'对应数据库中的'price'
            size = float(data.get('size', 0))  # 假设请求体中的'size'对应数据库中的'size'
            # 当前时间的格式化字符串
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

            if not symbol or not direction:
                return web.json_response({"error": "Missing required parameters"}, status=400)

            # 调用数据库方法写入信号
            result = await self.db.insert_signal({
                'account_id': account_id,  # 假设account_id是从请求头中获取的
                'symbol': symbol,
                'direction': direction,
                'price': price,  # 假设价格为0，实际使用时需要根据需求设置
                'size': size,  # 假设大小为0，实际使用时需要根据需求设置
                'status': 'pending',
                'timestamp': timestamp,
            })
            if result['status'] == 'success':
                return web.json_response(result, status=200)
            else:
                return web.json_response(result, status=500)
        except Exception as e:
            return web.json_response({"error": str(e)}, status=500)
        
    async def initialize_accounts(self):
        """初始化所有活跃账户"""
        conn = None
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"SELECT id FROM g_accounts")
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

    async def start_api_server(self):
        """启动API服务器"""
        runner = web.AppRunner(self.app)
        await runner.setup()
        site = web.TCPSite(runner, 'localhost', 8082)  # 指定监听的地址和端口
        await site.start()
        print("API服务器已启动，监听端口8082")

    async def run(self):
        """运行主程序"""

        # 启动API服务器
        asyncio.create_task(self.start_api_server())

        # 初始化账户和交易所
        await self.initialize_accounts()

        signal_task = asyncio.create_task(self.signal_task.signal_processing_task())
        # await asyncio.gather(signal_task)

        price_task = asyncio.create_task(self.price_task.price_monitoring_task())
        # await asyncio.gather(price_task)

        await asyncio.gather(signal_task, price_task)

if __name__ == "__main__":
    config = TradingBotConfig()
    bot = OKXTradingBot(config)

    try:
        asyncio.run(bot.run())
    except KeyboardInterrupt:
        print("程序安全退出")