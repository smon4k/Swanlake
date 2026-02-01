import asyncio
import sys
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent.parent))  # 调整到 pyapi 目录
from pyapi.piggybank.utils.logger import setup_logger
from db import SessionLocal
from exchanges.factory import ExchangeFactory
from pyapi.piggybank.strategies.main_strategy import MainStrategy
from config.config import Config, ExchangeType


class Args:
    exchange = "okx"
    symbol = "BTC-USDT"
    strategy = "pending"


async def run_strategy_loop(args, db_session):
    exchange_type = ExchangeType.OKX if args.exchange == "okx" else ExchangeType.BINANCE
    exchange = ExchangeFactory.create_exchange(exchange_type)
    config = Config()

    strategy = MainStrategy(exchange, db_session, config)
    while True:
        try:
            print(f"\n[运行策略] {args.strategy} @ {args.symbol}")
            success = strategy.execute(args.symbol)
            exchange_name = exchange.get_exchange_name()
            print(f"[{exchange_name}] 策略执行{'成功' if success else '失败'}")
        except Exception as e:
            print(f"[错误] 策略执行出错: {str(e)}")

        await asyncio.sleep(10)  # 每10秒执行一次


def main():
    args = Args()
    setup_logger("my_app", capture_print=True)
    db_session = SessionLocal()
    asyncio.run(run_strategy_loop(args, db_session))


if __name__ == "__main__":
    main()
