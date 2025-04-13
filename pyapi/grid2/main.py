import asyncio
from decimal import Decimal, getcontext
import os

from dotenv import load_dotenv
from database import Database
from trading_bot_config import TradingBotConfig
from signal_processing_task import SignalProcessingTask
from price_monitoring_task import PriceMonitoringTask
from common_functions import fetch_current_positions, fetch_positions_history, get_exchange
from aiohttp import web
from datetime import datetime, timezone
import logging

load_dotenv()

# 设置Decimal精度
getcontext().prec = 8

# 日志文件路径
log_file_path = os.getenv("LOG_PATH")

# 检查日志文件是否存在，如果存在则清空
if os.path.exists(log_file_path):
    with open(log_file_path, 'w', encoding='utf-8') as f:
        f.truncate(0)  # 清空文件内容
class InfoAndErrorFilter(logging.Filter):
    def filter(self, record):
        # 只允许 INFO 和 ERROR 级别的日志通过
        return record.levelname in ['INFO', 'ERROR']
    
# 设置日志配置
logging.basicConfig(
    filename=log_file_path,  # 指定日志文件
    level=logging.INFO,  # 设置日志级别
    format='%(asctime)s - %(levelname)s - %(message)s',  # 日志格式
    encoding='utf-8'  # 指定编码为 UTF-8
)

# 获取根日志器并添加过滤器
logger = logging.getLogger()
logger.addFilter(InfoAndErrorFilter())

class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        self.config = config
        self.db = Database(config.db_config)
        self.signal_task = SignalProcessingTask(config, self.db)
        self.price_task = PriceMonitoringTask(config, self.db)
        self.app = web.Application()
        self.app.add_routes([
            web.post('/insert_signal', self.handle_insert_signal),  # 新增路由
            web.get('/get_positions_history', self.get_positions_history),  # 分页获取历史持仓列表
            web.get('/get_current_positions', self.get_current_positions),  # 获取当前持仓信息
        ])

    async def handle_insert_signal(self, request):
        """接口：处理写入信号的API请求"""
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
    
    async def get_positions_history(self, request):
        """接口：分页获取历史持仓列表"""
        try:
            data = await request.json()

            # 提取参数并处理默认值
            account_id = int(data.get("account_id"))
            inst_id = data.get("inst_id")  # 可以为 None
            inst_type = data.get("inst_type", "SWAP")  # 默认是 SWAP
            limit = int(data.get("limit", 100))  # 默认每页 100 条

            # 调用内部业务逻辑
            result = await fetch_positions_history(
                self,
                account_id=account_id,
                inst_type=inst_type,
                inst_id=inst_id,
                limit=limit
            )

            return web.json_response({"success": True, "data": result})
        
        except Exception as e:
            return web.json_response({"success": False, "error": str(e)}, status=500)
    
    async def get_current_positions(request):
        """接口：获取当前持仓信息"""
        try:
            data = await request.json()
            account_id = int(data.get("account_id"))
            inst_type = data.get("inst_type", "SWAP")

            positions = await fetch_current_positions(account_id, inst_type)

            return web.json_response({
                "success": True,
                "data": positions
            })

        except Exception as e:
            return web.json_response({
                "success": False,
                "error": str(e)
            }, status=500)
        
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
                    await self.db.get_config_by_account_id(account_id)  # 加载到缓存
                    await get_exchange(self, account_id)  # 初始化交易所实例
        except Exception as e:
            print(f"初始化账户失败: {e}")
            logging.error(f"初始化账户失败: {e}")
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
        logging.info("API服务器已启动，监听端口8082")

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
        logging.info("程序安全退出")