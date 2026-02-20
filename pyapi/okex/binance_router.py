from typing import Optional, List, Any, Dict
from fastapi import APIRouter, Query
import ccxt

from schemas import AccountBalancesModel, CommonResponse

router = APIRouter()


def _build_exchange(refugee: AccountBalancesModel) -> ccxt.binance:
    return ccxt.binance(
        {
            "apiKey": refugee.api_key,
            "secret": refugee.secret_key,
            "enableRateLimit": True,
            "options": {"defaultType": "spot"},
        }
    )


def _success(message: str, data: Any = None) -> CommonResponse:
    return CommonResponse(status="success", message=message, data=data)


def _error(message: str, err: Any = None) -> CommonResponse:
    payload = {"error_message": str(err)} if err is not None else None
    return CommonResponse(status="error", message=message, data=payload)


@router.post("/api/binance/get_transfer_history", response_model=CommonResponse)
async def get_transfer_history(
    refugee: AccountBalancesModel,
    types: List[str] = Query(default=["MAIN_UMFUTURE", "UMFUTURE_MAIN"]),
):
    """
    Binance 划转记录:
    - MAIN_UMFUTURE: 现货->U本位合约
    - UMFUTURE_MAIN: U本位合约->现货
    """
    try:
        exchange = _build_exchange(refugee)
        combined_data: List[Dict[str, Any]] = []
        errors: List[Dict[str, str]] = []

        for transfer_type in types:
            try:
                res = exchange.sapi_get_asset_transfer(
                    {"type": transfer_type, "asset": "USDT"}
                )
                rows = res.get("rows", []) if isinstance(res, dict) else []
                combined_data.extend(rows)
            except Exception as e:
                errors.append({"type": transfer_type, "error_message": str(e)})

        if not combined_data and errors:
            return CommonResponse(
                status="error",
                message="Failed to fetch transfer history",
                data={"errors": errors},
            )

        combined_data.sort(
            key=lambda x: int(x.get("timestamp") or x.get("time") or 0), reverse=True
        )
        return _success("Transfer history fetched successfully", combined_data)
    except Exception as e:
        return _error("An error occurred", e)


@router.post("/api/binance/get_account_balances", response_model=CommonResponse)
async def get_account_balances(
    refugee: AccountBalancesModel, ccy: Optional[str] = None
):
    try:
        exchange = _build_exchange(refugee)
        balance = exchange.fetch_balance({"type": "spot"})
        data = balance.get(ccy, {}) if ccy else balance.get("total", {})
        return _success("Account balance fetched successfully", data)
    except Exception as e:
        return _error("Failed to fetch account balance", e)


@router.post("/api/binance/get_funding_balances", response_model=CommonResponse)
async def get_funding_balances(
    refugee: AccountBalancesModel, ccy: Optional[str] = None
):
    try:
        exchange = _build_exchange(refugee)
        params = {"asset": ccy} if ccy else {}
        result = exchange.sapi_post_asset_get_funding_asset(params)
        return _success("Funding balance fetched successfully", result)
    except Exception as e:
        return _error("Failed to fetch funding balance", e)


@router.post("/api/binance/get_saving_balance", response_model=CommonResponse)
async def get_saving_balance(refugee: AccountBalancesModel, ccy: Optional[str] = None):
    """
    简单收益余额（不同账号权限/版本可能有差异）
    """
    try:
        exchange = _build_exchange(refugee)
        params = {"asset": ccy} if ccy else {}
        result = exchange.sapi_get_simple_earn_flexible_position(params)
        rows = result.get("rows", []) if isinstance(result, dict) else []
        amt = 0.0
        if ccy:
            for item in rows:
                if item.get("asset") == ccy:
                    amt = float(item.get("totalAmount", 0) or 0)
                    break
        return _success("Saving balance fetched successfully", str(amt))
    except Exception:
        # 与 OKX 路由保持兼容风格：接口不可用时返回 0
        return _success("Saving balance fetched successfully", "0")


@router.post("/api/binance/get_market_ticker", response_model=CommonResponse)
async def get_market_ticker(refugee: AccountBalancesModel, instId: str):
    try:
        exchange = _build_exchange(refugee)
        ticker = exchange.fetch_ticker(instId)
        return _success("Market ticker fetched successfully", ticker)
    except Exception as e:
        return _error("Failed to fetch market ticker", e)


@router.post("/api/binance/get_fills_history", response_model=CommonResponse)
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
        exchange = _build_exchange(refugee)
        symbol = instId if instId else None
        since = int(after) if after else None
        limit_num = int(limit) if limit else None
        params: Dict[str, Any] = {}
        if ordId:
            params["orderId"] = ordId
        if before:
            params["endTime"] = before
        if instFamily:
            params["instFamily"] = instFamily
        if uly:
            params["uly"] = uly
        trades = exchange.fetch_my_trades(
            symbol=symbol, since=since, limit=limit_num, params=params
        )
        return _success("Fills history fetched successfully", trades)
    except Exception as e:
        return _error("Failed to fetch fills history", e)


@router.post("/api/binance/get_positions", response_model=CommonResponse)
async def get_positions(
    refugee: AccountBalancesModel,
    instType: str,
    instId: str = "",
):
    """
    Binance 持仓:
    - SWAP/FUTURES 走 U 本位合约持仓接口
    """
    try:
        exchange = _build_exchange(refugee)
        if instType.upper() in {"SWAP", "FUTURES"}:
            rows = exchange.fapiPrivateV2GetPositionRisk()
            if instId:
                target = instId.replace("/", "").replace(":USDT", "")
                rows = [item for item in rows if item.get("symbol") == target]
            return _success("Positions fetched successfully", rows)

        balance = exchange.fetch_balance()
        return _success("Positions fetched successfully", balance.get("total", {}))
    except Exception as e:
        return _error("Failed to fetch positions", e)
