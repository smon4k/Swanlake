from fastapi import FastAPI, Query
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from typing import Optional
import logging
import uvicorn

# 模块依赖
from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import fetch_positions_history, fetch_current_positions

# 日志配置
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


# ✅ 封装业务逻辑类
class PositionService:
    def __init__(self, config: TradingBotConfig, db: Database):
        self.db = db
        self.config = config

    async def get_positions_history(self, account_id: int, inst_id: Optional[str], inst_type: str, limit: int):
        try:
            result = await fetch_positions_history(
                self,
                account_id=account_id,
                inst_id=inst_id,
                inst_type=inst_type,
                limit=limit
            )
            return {"success": True, "data": result}
        except Exception as e:
            logger.error(f"获取历史持仓出错: {e}")
            return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

    async def get_current_positions(self, account_id: int, inst_id: Optional[str], inst_type: str):
        try:
            result = await fetch_current_positions(
                self,  # 如果将来有 bot 实例，这里可传入
                account_id=account_id,
                symbol=inst_id,
                inst_type=inst_type
            )
            return {"success": True, "data": result}
        except Exception as e:
            logger.error(f"获取当前持仓出错: {e}")
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
    limit: int = Query(100, description="分页数量")
):
    return await service.get_positions_history(account_id, inst_id, inst_type, limit)

# ✅ 获取当前持仓
@app.get("/get_current_positions")
async def get_current_positions(
    account_id: int = Query(..., description="账户ID"),
    inst_id: Optional[str] = Query(None, description="交易对ID"),
    inst_type: str = Query("SWAP", description="合约类型")
):
    return await service.get_current_positions(account_id, inst_id, inst_type)

# ✅ 启动服务
if __name__ == "__main__":
    logger.info("启动 OKX 持仓服务...")
    uvicorn.run("api_service:app", host="0.0.0.0", port=8083, reload=True)
