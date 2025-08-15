from typing import Optional
import okx.Account as Account
import okx.MarketData as MarketData
import okx.Trade as Trade
from typing import List
from fastapi import Query
import okx.Funding as Funding
from fastapi import APIRouter

from schemas import AccountBalancesModel, CommonResponse

router = APIRouter()

# 获取划转记录
@router.post("/api/okex/get_transfer_history", response_model=CommonResponse)
async def get_transfer_history(
    refugee: AccountBalancesModel,
    types: List[str] = Query(default=["131", "130"], description="账单类型列表，例如 [131, 132]"),
):
    try:
        fundingAPI = Funding.FundingAPI(
            refugee.api_key, refugee.secret_key, refugee.passphrase, False, "0"
        )

        combined_data = []
        errors = []
        
        for t in types:
            result = fundingAPI.get_bills('USDT', t)
            if result["code"] == "0":
                combined_data.extend(result["data"])
            else:
                errors.append({
                    "type": t,
                    "error_code": result["code"],
                    "error_message": result.get("msg", "Unknown error")
                })

        # 如果有错误并且完全没数据
        if not combined_data and errors:
            return CommonResponse(
                status="error",
                message="Failed to fetch transfer history",
                data=errors
            )

        # 按时间戳倒序排序（OKX返回的时间戳是字符串毫秒值）
        combined_data.sort(key=lambda x: int(x["ts"]), reverse=True)

        return CommonResponse(
            status="success",
            message="Transfer history fetched successfully",
            data=combined_data,
        )

    except Exception as e:
        return CommonResponse(
            status="error",
            message=f"An error occurred: {str(e)}",
        )

# 获取余额
@router.post("/api/okex/get_account_balances", response_model=CommonResponse)
async def get_account_balances(
    refugee: AccountBalancesModel, ccy: Optional[str] = None
):
    try:
        accountAPI = Account.AccountAPI(
            refugee.api_key, refugee.secret_key, refugee.passphrase, False, "0"
        )
        result = accountAPI.get_account_balance(ccy)
        # print(result)
        if result["code"] == "0":
            return CommonResponse(
                status="success",
                message="Account balance fetched successfully",
                data=result["data"],
            )
        else:
            return CommonResponse(
                status="error",
                message="Failed to fetch account balance",
                data={
                    "error_code": result["code"],
                    "error_message": result.get("msg", "Unknown error"),
                },
            )
    except Exception as e:
        return CommonResponse(
            status="error",
            message="An error occurred",
        )


# 获取单个产品行情信息
@router.post("/api/okex/get_market_ticker", response_model=CommonResponse)
async def get_market_ticker(refugee: AccountBalancesModel, instId: str):
    try:
        marketAPI = MarketData.MarketAPI(
            refugee.api_key, refugee.secret_key, refugee.passphrase, False, "0"
        )
        result = marketAPI.get_ticker(instId)
        if result["code"] == "0":
            return CommonResponse(
                status="success",
                message="Market ticker fetched successfully",
                data=result["data"],
            )
        else:
            return CommonResponse(
                status="error",
                message="Failed to fetch market ticker",
                data={
                    "error_code": result["code"],
                    "error_message": result.get("msg", "Unknown error"),
                },
            )
    except Exception as e:
        return CommonResponse(
            status="error",
            message="An error occurred",
        )


# 获取成交明细
@router.post("/api/okex/get_fills_history", response_model=CommonResponse)
async def get_fills_history(
    refugee: AccountBalancesModel,
    instType: str,
    uly: str = "",
    instId: str = "",
    ordId: str = "",
    after: str = "",
    before: str = "",
    limit: str = "",
    instFamily: str = "",
):
    try:
        tradeAPI = Trade.TradeAPI(
            refugee.api_key, refugee.secret_key, refugee.passphrase, False, "0"
        )
        result = tradeAPI.get_fills_history(
            instType, uly, instId, ordId, after, before, limit, instFamily
        )
        if result["code"] == "0":
            return CommonResponse(
                status="success",
                message="Fills history fetched successfully",
                data=result["data"],
            )
        else:
            return CommonResponse(
                status="error",
                message="Failed to fetch fills history",
                data={
                    "error_code": result["code"],
                    "error_message": result.get("msg", "Unknown error"),
                },
            )
    except Exception as e:
        return CommonResponse(
            status="error",
            message="An error occurred",
        )


# 查看持仓信息
@router.post("/api/okex/get_positions", response_model=CommonResponse)
async def get_positions(
    refugee: AccountBalancesModel,
    instType: str,
    instId: str = "",
):
    try:
        accountAPI = Account.AccountAPI(
            refugee.api_key, refugee.secret_key, refugee.passphrase, False, "0"
        )
        result = accountAPI.get_positions(instType, instId)
        if result["code"] == "0":
            return CommonResponse(
                status="success",
                message="Positions fetched successfully",
                data=result["data"],
            )
        else:
            return CommonResponse(
                status="error",
                message="Failed to fetch positions",
                data={
                    "error_code": result["code"],
                    "error_message": result.get("msg", "Unknown error"),
                },
            )
    except Exception as e:
        return CommonResponse(
            status="error",
            message="An error occurred",
        )
