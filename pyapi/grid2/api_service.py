from decimal import Decimal
import os
from fastapi import FastAPI, Query, Request
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from typing import Optional
from datetime import datetime, timezone
import logging
import uvicorn

# 模块依赖
from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import fetch_positions_history, fetch_current_positions, get_account_balance, get_exchange

# 日志配置
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


# ✅ 封装业务逻辑类
class PositionService:
    def __init__(self, config: TradingBotConfig, db: Database):
        self.db = db
        self.config = config
    
    # 接口：处理写入信号的API请求
    async def insert_signal(self, account_id: int, symbol: str, side: str, price: Decimal, size: float):
        """接口：处理写入信号的API请求"""
        try:
            # data = await request.json()
            # 解析请求体中的参数
            # symbol = data.get('symbol')
            # account_id = os.getenv("ACCOUNT_ID", 1)
            direction = 'long' if side == 'buy' else 'short'  # 假设请求体中的'side'对应数据库中的'direction'
            # price = Decimal(data.get('price', 0))  # 假设请求体中的'price'对应数据库中的'price'
            # size = float(data.get('size', 0))  # 假设请求体中的'size'对应数据库中的'size'
            # 当前时间的格式化字符串
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

            if not symbol or not direction:
                return JSONResponse(status_code=500, content={"success": False, "error": 'Missing required parameters'})
            
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
            return result
        except Exception as e:
            return JSONResponse(status_code=500, content={"success": False, "error": str(e)})
    
    async def get_positions_history(self, account_id: int, inst_id: Optional[str], inst_type: str, limit: str, after: str = None, before: str = None):
        try:
            result = await fetch_positions_history(
                self,
                account_id=account_id,
                inst_id=inst_id,
                inst_type=inst_type,
                limit=limit,
                after=after,
                before=before
            )
            return {"success": True, "data": result}
        except Exception as e:
            logger.error(f"获取历史持仓出错: {e}")
            return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

    async def get_current_positions(self, account_id: int, inst_id: Optional[str], inst_type: str):
        try:
            # 获取当前持仓数据
            result = await fetch_current_positions(
                self,  # 如果将来有 bot 实例，这里可传入
                account_id=account_id,
                symbol=inst_id,
                inst_type=inst_type
            )

            # 过滤出 contracts > 0 的持仓数据
            filtered_result = [position for position in result if position.get("contracts", 0) > 0]

            # 如果过滤后没有符合条件的数据，返回空列表
            if not filtered_result:
                return {"success": True, "data": []}

            # 返回过滤后的数据
            return {"success": True, "data": filtered_result}

        except Exception as e:
            logger.error(f"获取当前持仓出错: {e}")
            return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

    async def get_account_balances(self, account_id: int, inst_id: Optional[str]):
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return None
            # 获取账户余额数据
            result = await get_account_balance(
                exchange,
                inst_id
            )
            return {"success": True, "data": result}
        except Exception as e:
            logger.error(f"获取账户余额出错: {e}")
            return JSONResponse(status_code=500, content={"success": False, "error": str(e)})
    
    async def refresh_config_cache(self, account_id: int):
        try:
            # 刷新配置缓存数据
            config = await self.db.get_config_by_account_id(account_id)
            if not config:
                return JSONResponse(status_code=404, content={"success": False, "error": "配置不存在"})
            return {"success": True, "message": "配置缓存数据已刷新"}
        except Exception as e:
            logger.error(f"刷新配置缓存数据出错: {e}")
            return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

# ✅ 初始化应用
config = TradingBotConfig()
db = Database(config.db_config)
service = PositionService(config, db)

app = FastAPI()

# ✅ 跨域配置
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ✅ 根路径测试
@app.get("/")
async def root():
    return {"message": "OKX 持仓服务已启动"}

# ✅ 获取历史持仓
@app.get("/get_positions_history")
async def get_positions_history(
    account_id: int = Query(..., description="账户ID"),
    inst_id: Optional[str] = Query(None, description="交易对ID，例如 BTC-USDT-SWAP"),
    inst_type: str = Query("SWAP", description="合约类型"),
    limit: int = Query(100, description="分页数量"),
    after: Optional[str] = Query(None, description="仓位更新之前的内容 时间戳 uTime"),
    before: Optional[str] = Query(None, description="仓位更新之后的内容 时间戳 uTime")
):
    return await service.get_positions_history(account_id, inst_id, inst_type, limit, after, before)

# ✅ 获取当前持仓
@app.get("/get_current_positions")
async def get_current_positions(
    account_id: int = Query(..., description="账户ID"),
    inst_id: Optional[str] = Query(None, description="交易对ID"),
    inst_type: str = Query("SWAP", description="合约类型")
):
    return await service.get_current_positions(account_id, inst_id, inst_type)

#获取账户余额
@app.get("/get_account_over")
async def get_account_over(
    account_id: int = Query(..., description="账户ID"),
    inst_id: Optional[str] = Query(None, description="交易对ID"),
):
    try:
        # 获取账户余额数据
        balance = await service.get_account_balances(account_id, inst_id)
        return {"success": True, "data": balance}
    except Exception as e:
        logger.error(f"获取账户余额出错: {e}")
        return JSONResponse(status_code=500, content={"success": False, "error": str(e)})
    

# 刷新配置缓存数据
@app.get("/refresh_config")
async def refresh_config(account_id: int = Query(..., description="账户ID"),):
    try:
        await service.refresh_config_cache(account_id)
        return {"success": True, "message": "配置缓存数据已刷新"}
    except Exception as e:
        logger.error(f"刷新配置缓存数据出错: {e}")

@app.post("/insert_signal")
async def handle_insert_signal(request: Request):
    try:
        data = await request.json()
        # 解析请求体中的参数
        symbol = data.get('symbol')
        account_id = os.getenv("ACCOUNT_ID", 1)
        price = Decimal(data.get('price', 0))  # 假设请求体中的'price'对应数据库中的'price'
        size = float(data.get('size', 0))  # 假设请求体中的'size'对应数据库中的'size'
        result = await service.insert_signal(account_id, symbol, data.get('side'), price, size)
        if result['status'] == 'success':
            return {"success": True, "data": result}
        else:
            return JSONResponse(status_code=500, content={"success": False, "error": 'error'})
    except Exception as e:
        return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

# ✅ 启动服务
if __name__ == "__main__":
    logger.info("启动 持仓 服务...")
    uvicorn.run("api_service:app", host="0.0.0.0", port=8082, reload=True)
