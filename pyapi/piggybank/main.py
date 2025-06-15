import asyncio
import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent.parent))  # 调整到 pyapi 目录
import argparse
from db import SessionLocal
from exchanges.factory import ExchangeFactory
from strategies.balance_strategy import BalanceStrategy
from strategies.pending_strategy import PendingStrategy
from config.config import Config, ExchangeType

async def run_strategy_loop(args, db_session):
    exchange_type = ExchangeType.OKX if args.exchange == 'okx' else ExchangeType.BINANCE
    exchange = ExchangeFactory.create_exchange(exchange_type)
    config = Config()

    if args.strategy == 'balance':
        strategy = BalanceStrategy(exchange, db_session, config)
    elif args.strategy == 'pending':
        strategy = PendingStrategy(exchange, db_session, config)
    else:
        raise ValueError(f"未知策略: {args.strategy}")

    # while True:
    try:
        print(f"\n[运行策略] {args.strategy} @ {args.symbol}")
        success = strategy.execute(args.symbol)
        exchange_name = exchange.get_exchange_name()
        print(f"[{exchange_name}] 策略执行{'成功' if success else '失败'}")
    except Exception as e:
        print(f"[错误] 策略执行出错: {str(e)}")

    await asyncio.sleep(10)  # 每10秒执行一次，可以调整

def main():
    parser = argparse.ArgumentParser(description='量化交易系统')
    parser.add_argument('--exchange', type=str, required=True, 
                        choices=['okx', 'binance'], help='选择交易所')
    parser.add_argument('--symbol', type=str, required=True, 
                        help='交易对，例如 BTC-USDT 或 BTC/USDT')
    parser.add_argument('--strategy', type=str, default='balance', 
                        choices=['balance', 'pending'], help='选择策略')

    args = parser.parse_args()
    db_session = SessionLocal()

    asyncio.run(run_strategy_loop(args, db_session))

if __name__ == '__main__':
    main()