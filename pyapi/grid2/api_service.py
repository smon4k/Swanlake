from fastapi import FastAPI, Query
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from typing import Optional
import logging

# 假设你已经有这两个函数，也可以用 mock 替代
from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import fetch_positions_history, fetch_current_positions

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# ✅ 跨域设置
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # 允许前端访问的域名
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

config = TradingBotConfig()
db = Database(config)


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
    try:
        result = await fetch_positions_history(
            db=db,  # 如果你有 bot 实例可以传，这里暂设为 None
            account_id=account_id,
            inst_id=inst_id,
            inst_type=inst_type,
            limit=limit
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"获取历史持仓出错: {e}")
        return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

# ✅ 获取当前持仓
@app.get("/get_current_positions")
async def get_current_positions(
    account_id: int = Query(..., description="账户ID"),
    inst_id: Optional[str] = Query(None, description="交易对ID"),
    inst_type: str = Query("SWAP", description="合约类型")
):
    try:
        result = await fetch_current_positions(
            None,  # 同样这里根据你的实现可能传 bot 实例
            account_id=account_id,
            inst_id=inst_id,
            inst_type=inst_type
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"获取当前持仓出错: {e}")
        return JSONResponse(status_code=500, content={"success": False, "error": str(e)})

# ✅ 启动入口
if __name__ == "__main__":
    import uvicorn

    logger.info("启动 OKX 持仓服务...")
    uvicorn.run("api_service:app", host="0.0.0.0", port=8082, reload=True)
