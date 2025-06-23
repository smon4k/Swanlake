# api_service.py
import logging
import sys
import uvicorn
from fastapi import FastAPI, Query
from typing import Dict
from exchanges.factory import ExchangeFactory
from config.config import Config, ExchangeType
from db import SessionLocal
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent.parent))  # 调整到 pyapi 目录
from pyapi.piggybank.strategies.base_strategy import BaseStrategy
import os

app = FastAPI()
config = Config()

# 初始化交易所和数据库
exchange_type = ExchangeType.OKX
exchange = ExchangeFactory.create_exchange(exchange_type)
db_session = SessionLocal()

# ✅ 临时类：只用于调用 _get_valuation（避免实例化抽象类）
class ReadOnlyStrategy(BaseStrategy):
    def execute(self, symbol):
        pass  # 不执行任何策略，只为使用 _get_valuation()

@app.get("/symbol-info")
def get_symbol_info(symbol: str = Query(..., description="如 BTC-USDT")) -> Dict:
    """
    获取交易对信息，包括账户余额估值和最小下单量
    """
    strategy = ReadOnlyStrategy(exchange, db_session)
    valuation = strategy._get_valuation(symbol)
    market_info = exchange.get_market_info(symbol)

    return {
        "symbol": symbol,
        "valuation": valuation,
        "min_order_size": market_info['info'].get('minSz'),
        "base_currency": market_info['info'].get('baseCcy', symbol.split('-')[0]),
        "quote_currency": market_info['info'].get('quoteCcy', symbol.split('-')[1])
    }

# ✅ 启动服务
if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    logging.info("启动 持仓 服务...")

    # 支持从环境变量或默认值读取端口号
    api_port = int(os.environ.get("API_PORT", 8000))

    uvicorn.run("api_service:app", host="0.0.0.0", port=api_port, reload=True)
