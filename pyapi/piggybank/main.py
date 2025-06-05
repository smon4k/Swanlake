import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent.parent))  # 调整到 pyapi 目录
import argparse
from db import SessionLocal
from exchanges.factory import ExchangeFactory
from strategies.balance_strategy import BalanceStrategy
from strategies.pending_strategy import PendingStrategy
from config.config import Config, ExchangeType

def main():
    # 解析命令行参数
    parser = argparse.ArgumentParser(description='量化交易系统')
    parser.add_argument('--exchange', type=str, required=True, 
                       choices=['okx', 'binance'], help='选择交易所')
    parser.add_argument('--symbol', type=str, required=True, 
                       help='交易对，例如 BTC-USDT 或 BTC/USDT')
    parser.add_argument('--strategy', type=str, default='balance', 
                       choices=['balance', 'pending'], help='选择策略')
    
    args = parser.parse_args()
    
    # 初始化数据库会话
    db_session = SessionLocal()
    
    try:
        # 创建交易所实例
        exchange_type = ExchangeType.OKX if args.exchange == 'okx' else ExchangeType.BINANCE
        exchange = ExchangeFactory.create_exchange(exchange_type)
        
        # 创建策略实例
        config = Config()
        if args.strategy == 'balance': # 执行平衡策略
            strategy = BalanceStrategy(exchange, db_session, config)
        elif args.strategy == 'pending': # 执行挂单策略
            strategy = PendingStrategy(exchange, db_session, config)
        else:
            raise ValueError(f"未知策略: {args.strategy}")
        
        # 执行策略
        success = strategy.execute(args.symbol)
        print(f"[{exchange.get_exchange_name()}] 策略执行{'成功' if success else '失败'}")
        
    except Exception as e:
        print(f"程序执行出错: {str(e)}")
    finally:
        db_session.close()

if __name__ == '__main__':
    main()