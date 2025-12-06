import asyncio
from collections import defaultdict
from decimal import Decimal, getcontext
import os
from dotenv import load_dotenv
from database import Database
from trading_bot_config import TradingBotConfig
from signal_processing_task import SignalProcessingTask
from price_monitoring_task import PriceMonitoringTask
from stop_loss_task import StopLossTask
from common_functions import get_exchange
from api_rate_limiter import SimpleRateLimiter
import logging
from logging.handlers import TimedRotatingFileHandler

load_dotenv()
getcontext().prec = 8

# ----------------- 日志配置 -----------------
log_file_path = os.getenv("LOG_PATH", "bot.log")
if os.path.exists(log_file_path):
    with open(log_file_path, "w", encoding="utf-8") as f:
        f.truncate(0)


class InfoAndErrorFilter(logging.Filter):
    def filter(self, record):
        return record.levelname in ["INFO", "ERROR"]


log_handler = TimedRotatingFileHandler(
    filename=log_file_path, when="midnight", interval=1, backupCount=7, encoding="utf-8"
)
log_handler.setFormatter(logging.Formatter("%(asctime)s - %(levelname)s - %(message)s"))
logger = logging.getLogger()
logger.setLevel(logging.INFO)
logger.addHandler(log_handler)
logger.addFilter(InfoAndErrorFilter())


# ----------------- 交易机器人 -----------------
class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        self.config = config
        self.db = Database(config.db_config)
        self.signal_lock = asyncio.Lock()
        self.signal_queue = asyncio.Queue()

        # ✅ 创建全局API限流器
        self.api_limiter = SimpleRateLimiter(max_requests=60, time_window=2.0)

        self.stop_loss_task = StopLossTask(
            config, self.db, self.signal_lock, self.api_limiter
        )

        # 🔐 新增：记录哪些账户正在被 signal 处理
        self.busy_accounts: set[int] = set()
        self.account_locks = defaultdict(asyncio.Lock)  # 每个账户独立锁
        self.market_precision_cache = {}  # 市场精度缓存

        self.signal_task = SignalProcessingTask(
            config,
            self.db,
            self.signal_lock,
            self.stop_loss_task,
            self.account_locks,
            self.busy_accounts,
            self.api_limiter,
        )
        self.price_task = PriceMonitoringTask(
            config,
            self.db,
            self.signal_lock,
            self.stop_loss_task,
            self.busy_accounts,
            self.api_limiter,
        )

        # API Server 可选
        # self.app = web.Application()
        # self.app.add_routes([
        #     web.post('/insert_signal', self.handle_insert_signal),
        #     web.get('/get_positions_history', self.get_positions_history),
        #     web.get('/get_current_positions', self.get_current_positions),
        # ])

    # ----------------- 初始化账户 -----------------
    async def initialize_accounts_once(self):
        """启动时一次性初始化"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id FROM g_accounts")
                accounts = cursor.fetchall()
                # 并发初始化每个账户
                await asyncio.gather(
                    *[
                        self._initialize_single_account(account["id"])
                        for account in accounts
                    ]
                )
            await self.db.get_account_max_position()
            # logging.info("账户初始化完成")
        except Exception as e:
            logging.error(f"初始化账户失败: {e}", exc_info=True)
        finally:
            if conn:
                conn.close()

    async def _initialize_single_account(self, account_id):
        """初始化单个账户（可并发）"""
        await self.db.get_account_info(account_id)
        await self.db.get_config_by_account_id(account_id)
        await get_exchange(self, account_id)

    # ----------------- 循环刷新配置 -----------------
    async def refresh_config_loop(self, interval: int = 60):
        """后台定时刷新配置"""
        while True:
            try:
                await self.initialize_accounts_once()  # 循环刷新
                # logging.info("刷新账户配置成功")
            except Exception as e:
                logging.error(f"刷新配置失败: {e}", exc_info=True)
            await asyncio.sleep(interval)

    # ----------------- 主运行方法 -----------------
    async def run(self):
        logging.info("启动持仓机器人任务")

        # ✅ 启动时一次性初始化账户
        await self.initialize_accounts_once()

        # ✅ 创建长期任务
        tasks = [
            asyncio.create_task(
                self.signal_task.signal_processing_task(), name="signal_task"
            ),
            asyncio.create_task(
                self.price_task.price_monitoring_task(), name="price_task"
            ),
            asyncio.create_task(self.refresh_config_loop(), name="refresh_config_task"),
            asyncio.create_task(
                self.stop_loss_task.stop_loss_task(), name="stop_loss_task"
            ),
        ]
        # consumer_tasks = [
        #     asyncio.create_task(self.signal_task.consumer(i))
        #     for i in range(self.signal_task.max_workers)
        # ]

        # ✅ 异常隔离7
        try:
            await asyncio.gather(*tasks)
        except Exception as e:
            logging.error(f"主任务运行异常: {e}", exc_info=True)
        finally:
            for task in tasks:
                task.cancel()
            logging.info("机器人任务已安全退出")


# ----------------- 启动 -----------------
if __name__ == "__main__":
    config = TradingBotConfig()
    bot = OKXTradingBot(config)
    try:
        asyncio.run(bot.run())
    except KeyboardInterrupt:
        logging.info("程序安全退出")
