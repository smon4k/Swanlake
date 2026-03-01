import json
import os
import random
import time
from typing import Dict, Tuple
import ccxt.async_support as ccxt
from decimal import Decimal
import asyncio
from datetime import datetime, timezone
import uuid
import logging
from typing import Optional


async def get_exchange(self, account_id: int) -> Optional[ccxt.Exchange]:
    """获取交易所实例（通过account_id）"""
    account_info = await self.db.get_account_info(account_id)
    if not account_info:
        logging.debug(f"📭 账户 {account_id} 未配置或已禁用，跳过")
        return None

    exchange_id = account_info["exchange"]
    logging.debug(f"🔗 创建交易所实例: 账户={account_id}, 交易所={exchange_id}")
    exchange_class = getattr(ccxt, exchange_id)

    # 统一转成字符串，避免 None / int 之类的问题
    api_key = str(account_info.get("api_key") or "")
    api_secret = str(account_info.get("api_secret") or "")
    api_passphrase = str(account_info.get("api_passphrase") or "")

    params = {
        "apiKey": api_key,
        "secret": api_secret,
        "password": api_passphrase,
        "options": {"defaultType": "swap"},
        "enableRateLimit": True,
        "timeout": 30000,
        # 'verbose': True,  # 调试日志
    }

    # 模拟盘设置
    is_simulation = os.getenv("IS_SIMULATION", "1")
    exchange = exchange_class(params)
    if is_simulation == "1":  # 1 表示模拟环境
        exchange.set_sandbox_mode(True)

    # 本地环境才加代理
    # if os.getenv("IS_LOCAL", "0") == "1":
    #     # print("使用本地代理 http://127.0.0.1:7890")
    #     proxy_url = "http://127.0.0.1:7890"
    #     # 确保是 str 类型
    #     exchange.aiohttp_proxy = str(proxy_url)
    #     exchange.aiohttp_proxy_auth = None

    return exchange


async def get_market_price(
    exchange: ccxt.Exchange,
    symbol: str,
    api_limiter=None,
    close_exchange: bool = False,
    retries: int = 3,
) -> Decimal:
    """获取当前市场价格（带重试机制，防止API限流）

    Args:
        exchange: 交易所实例
        symbol: 交易对
        api_limiter: API限流器
        close_exchange: 是否在调用后关闭exchange（默认False，由调用方管理）
        retries: 重试次数（默认3次）

    Returns:
        Decimal: 市场价格（失败返回0，调用方需检查）
    """
    try:
        for attempt in range(retries):
            try:
                # ✅ 调用全局API限流器
                if api_limiter:
                    await api_limiter.check_and_wait()

                ticker = await exchange.fetch_ticker(symbol)
                price = Decimal(str(ticker["last"]))
                logging.debug(f"💰 获取市场价格: {symbol} = {price}")
                return price

            except Exception as e:
                error_str = str(e)

                # RateLimitExceeded - 指数退避
                if "Too Many Requests" in error_str or "50011" in error_str:
                    if attempt < retries - 1:
                        delay = (attempt + 1) * 2.0 + random.uniform(0.5, 1.5)
                        logging.warning(
                            f"⏳ 获取市场价格限流，等待{delay:.1f}s后重试 ({attempt+1}/{retries}): {symbol}"
                        )
                        await asyncio.sleep(delay)
                        continue

                # 网络错误 - 快速重试
                elif "Network" in error_str or "Timeout" in error_str:
                    if attempt < retries - 1:
                        await asyncio.sleep(0.5)
                        continue

                # 最后一次重试失败
                if attempt == retries - 1:
                    logging.error(
                        f"❌ 获取市场价格多次失败（已重试{retries}次）: 币种={symbol}, 错误={e}"
                    )
                    return Decimal("0")  # ✅ 返回0，让调用方决定如何处理
                else:
                    # 其他错误 - 通用重试
                    logging.warning(
                        f"⚠️ 获取市场价格失败，重试中 ({attempt+1}/{retries}): {symbol}, {e}"
                    )
                    await asyncio.sleep(1.0)
                    continue

        return Decimal("0")
    finally:
        # ✅ 只在明确要求时才关闭，避免频繁创建/销毁连接
        if close_exchange:
            await exchange.close()


async def get_market_precision(
    self,
    exchange: ccxt.Exchange,
    symbol: str,
    instType: str = "SWAP",
    close_exchange: bool = True,
) -> dict:
    """获取市场的价格和数量精度（带缓存）"""
    # ✅ 先检查缓存
    cache_key = f"{symbol}:{instType}"
    if cache_key in self.market_precision_cache:
        logging.debug(f"✅ 使用缓存市场精度: {cache_key}")
        return self.market_precision_cache[cache_key]

    try:
        # ✅ 调用全局API限流器
        if hasattr(self, "api_limiter") and self.api_limiter:
            await self.api_limiter.check_and_wait()

        markets = await exchange.fetch_markets_by_type(
            instType, {"instId": f"{symbol}"}
        )

        if not markets or len(markets) == 0:
            logging.error(f"❌ 未找到市场信息: {symbol}")
            return {}

        contract_size = Decimal(str(markets[0]["contractSize"]))  # 默认是1，适用于BTC
        price_precision = Decimal(str(markets[0]["precision"]["price"]))
        amount_precision = Decimal(str(markets[0]["precision"]["amount"]))  # 数量精度
        min_amount = Decimal(str(markets[0]["limits"]["amount"]["min"]))  # 最小下单量

        logging.info(
            f"📏 获取市场精度: {symbol}, 合约大小={contract_size}, "
            f"价格精度={price_precision}, 数量精度={amount_precision}, 最小数量={min_amount}"
        )

        result = {
            "min_amount": min_amount,
            "contract_size": contract_size,
            "price": price_precision,
            "amount": amount_precision,
        }

        # ✅ 保存到缓存
        self.market_precision_cache[cache_key] = result

        return result
    except Exception as e:
        logging.error(f"❌ 获取市场精度失败: 币种={symbol}, 错误={e}", exc_info=True)
        logging.warning(f"⚠️ 使用默认市场精度: {symbol}")
        return {
            "min_amount": Decimal("0.001"),
            "contract_size": Decimal("1"),
            "price": Decimal("0.0001"),
            "amount": Decimal("0.0001"),
        }
    finally:
        if close_exchange:
            await exchange.close()  # ✅ 用完就关


async def get_client_order_id(prefix: str = "Zx"):
    """
    生成全局唯一的订单客户端ID
    格式: Zx + UUID前16位
    确保：
    1. UUID保证全局唯一性
    2. 不会因为时间/并发产生碰撞
    3. 长度固定、易于追踪
    """
    # 使用UUID的hex表示，去掉"-"后取前16位
    # UUID hex = 32位16进制字符，足以保证唯一性
    unique_id = uuid.uuid4().hex[:16]
    client_order_id = prefix + unique_id
    return client_order_id


async def open_position(
    self,
    account_id: int,
    symbol: str,
    side: str,
    pos_side: str,
    amount: float,
    price: float,
    order_type: str,
    client_order_id: str = None,
    is_reduce_only: bool = False,
    exchange: ccxt.Exchange = None,
    close_exchange: bool = True,
):
    """开仓、平仓下单（带重试机制 + 超时保护 - 方案3改进）"""
    max_retries = 3
    retry_delay = 0.5  # 初始延迟0.5秒
    order_timeout = 10.0  # ✅ 单次下单超时10秒
    current_client_order_id = client_order_id
    managed_exchange = exchange is None
    if managed_exchange:
        exchange = await get_exchange(self, account_id)
        if not exchange:
            logging.error(f"❌ 开仓失败：无法获取交易所实例 - 账户={account_id}")
            return None

    async def confirm_order_by_client_id(exchange, target_client_order_id: str):
        """超时或重复ID时，按 clOrdId 查询订单是否已被交易所受理。"""
        if not target_client_order_id:
            return None

        params = {"instType": "SWAP"}

        async def _find_in_orders(orders):
            for item in orders or []:
                info = item.get("info", {})
                if (
                    info.get("clOrdId") == target_client_order_id
                    or item.get("clientOrderId") == target_client_order_id
                ):
                    return item
            return None

        try:
            if hasattr(self, "api_limiter") and self.api_limiter:
                await self.api_limiter.check_and_wait()
            open_orders = await exchange.fetch_open_orders(symbol, None, None, params)
            found = await _find_in_orders(open_orders)
            if found:
                return found
        except Exception as e:
            logging.debug(
                f"⚠️ 账户 {account_id} 按 clOrdId 查询未成交订单失败: clOrdId={target_client_order_id}, 错误={e}"
            )

        try:
            if hasattr(exchange, "fetch_closed_orders"):
                if hasattr(self, "api_limiter") and self.api_limiter:
                    await self.api_limiter.check_and_wait()
                closed_orders = await exchange.fetch_closed_orders(
                    symbol, None, 50, params
                )
                found = await _find_in_orders(closed_orders)
                if found:
                    return found
        except Exception as e:
            logging.debug(
                f"⚠️ 账户 {account_id} 按 clOrdId 查询历史订单失败: clOrdId={target_client_order_id}, 错误={e}"
            )

        return None

    try:
        for attempt in range(max_retries):
            params = {
                "posSide": pos_side,
                "tdMode": "cross",
                "clOrdId": current_client_order_id,
                "reduceOnly": is_reduce_only,
            }

            if attempt == 0:  # 只在第一次尝试时记录日志
                logging.info(
                    f"📝 准备下单: 账户={account_id}, 币种={symbol}, 方向={side}, "
                    f"持仓方向={pos_side}, 数量={amount}, 价格={price}, "
                    f"订单类型={order_type}, 仅减仓={is_reduce_only}"
                )

            try:
                # ✅ 调用全局API限流器
                if hasattr(self, "api_limiter") and self.api_limiter:
                    await self.api_limiter.check_and_wait()

                # ✅ 【改进】为单次下单添加超时保护
                try:
                    order = await asyncio.wait_for(
                        exchange.create_order(
                            symbol=symbol,
                            type=order_type,
                            side=side,
                            amount=float(amount),
                            price=price,
                            params=params,
                        ),
                        timeout=order_timeout,
                    )
                except asyncio.TimeoutError:
                    logging.warning(
                        f"⏱️ 账户 {account_id} 下单超时({order_timeout}秒) ({attempt+1}/{max_retries}): "
                        f"币种={symbol}, 方向={side}"
                    )
                    # 超时后先按 clOrdId 查单，避免已受理订单被误判失败并重复提交
                    confirmed_order = await confirm_order_by_client_id(
                        exchange, current_client_order_id
                    )
                    if confirmed_order:
                        logging.warning(
                            f"✅ 账户 {account_id} 超时后确认订单已存在: "
                            f"clOrdId={current_client_order_id}, orderId={confirmed_order.get('id', 'N/A')}"
                        )
                        return confirmed_order
                    if attempt < max_retries - 1:
                        # 下次重试切换 clOrdId，避免重复ID冲突
                        current_client_order_id = await get_client_order_id()
                        await asyncio.sleep(retry_delay)
                        continue
                    else:
                        logging.error(f"❌ 账户 {account_id} 下单超时已达最大重试次数")
                        return None

                if order["info"].get("sCode") == "0":
                    order_id = order.get("id", "N/A")
                    logging.info(
                        f"✅ 下单成功: 账户={account_id}, 订单ID={order_id}, "
                        f"币种={symbol}, 方向={side}, 数量={amount}, 价格={price}"
                    )
                    return order
                else:
                    error_msg = order["info"].get("sMsg", "未知错误")
                    error_code = order["info"].get("sCode", "N/A")

                    # 重复 clOrdId 时先查单确认，确认存在则按成功处理
                    if error_code == "51016":
                        confirmed_order = await confirm_order_by_client_id(
                            exchange, current_client_order_id
                        )
                        if confirmed_order:
                            logging.warning(
                                f"✅ 账户 {account_id} 遇到51016但已确认订单存在: "
                                f"clOrdId={current_client_order_id}, orderId={confirmed_order.get('id', 'N/A')}"
                            )
                            return confirmed_order
                        if attempt < max_retries - 1:
                            current_client_order_id = await get_client_order_id()
                            logging.warning(
                                f"⚠️ 账户 {account_id} 下单 clOrdId 冲突，切换新ID重试: "
                                f"old={params.get('clOrdId')}, new={current_client_order_id}"
                            )
                            await asyncio.sleep(retry_delay)
                            continue

                    # ✅ 【改进】只在频率限制时重试，其他错误直接返回
                    if error_code == "50011" and attempt < max_retries - 1:
                        wait_time = retry_delay * (2**attempt)  # 指数退避：0.5s, 1s, 2s
                        logging.warning(
                            f"⚠️ 账户 {account_id} 下单触发频率限制，{wait_time:.1f}秒后重试 (尝试 {attempt+1}/{max_retries})"
                        )
                        await asyncio.sleep(wait_time)
                        continue  # 继续重试

                    logging.error(
                        f"❌ 下单失败: 账户={account_id}, 币种={symbol}, "
                        f"错误码={error_code}, 错误信息={error_msg}"
                    )
                    return None

            except asyncio.TimeoutError:
                # ✅ 超时错误单独处理
                logging.warning(
                    f"⏱️ 账户 {account_id} 下单超时({order_timeout}秒) ({attempt+1}/{max_retries}): "
                    f"币种={symbol}, 方向={side}"
                )
                confirmed_order = await confirm_order_by_client_id(
                    exchange, current_client_order_id
                )
                if confirmed_order:
                    logging.warning(
                        f"✅ 账户 {account_id} 超时后确认订单已存在: "
                        f"clOrdId={current_client_order_id}, orderId={confirmed_order.get('id', 'N/A')}"
                    )
                    return confirmed_order
                if attempt < max_retries - 1:
                    current_client_order_id = await get_client_order_id()
                    await asyncio.sleep(retry_delay)
                    continue
                else:
                    logging.error(f"❌ 账户 {account_id} 下单超时已达最大重试次数")
                    return None

            except Exception as e:
                error_msg = str(e)
                if "51016" in error_msg:
                    confirmed_order = await confirm_order_by_client_id(
                        exchange, current_client_order_id
                    )
                    if confirmed_order:
                        logging.warning(
                            f"✅ 账户 {account_id} 异常51016但确认订单存在: "
                            f"clOrdId={current_client_order_id}, orderId={confirmed_order.get('id', 'N/A')}"
                        )
                        return confirmed_order
                    if attempt < max_retries - 1:
                        current_client_order_id = await get_client_order_id()
                        logging.warning(
                            f"⚠️ 账户 {account_id} 异常51016，切换新 clOrdId 重试: "
                            f"new={current_client_order_id}"
                        )
                        await asyncio.sleep(retry_delay)
                        continue

                # ✅ 【改进】检查是否是频率限制错误
                if (
                    "50011" in error_msg or "Too Many Requests" in error_msg
                ) and attempt < max_retries - 1:
                    wait_time = retry_delay * (2**attempt)  # 指数退避
                    logging.warning(
                        f"⚠️ 账户 {account_id} 下单触发频率限制，{wait_time:.1f}秒后重试 (尝试 {attempt+1}/{max_retries})"
                    )
                    await asyncio.sleep(wait_time)
                    continue  # 继续重试

                logging.error(
                    f"❌ 下单异常: 账户={account_id}, 币种={symbol}, 方向={side}, 错误={e}",
                    exc_info=True,
                )
                return None
    finally:
        if close_exchange and managed_exchange and exchange:
            await exchange.close()  # ✅ 开仓流程整体结束后再关闭，减少重复创建/销毁

    # 所有重试都失败
    logging.error(f"❌ 账户 {account_id} 下单失败：已达到最大重试次数 {max_retries}")
    return None


# 获取账户余额
async def get_account_balance(
    exchange: ccxt.Exchange,
    symbol: str,
    marketType: str = "trading",
    api_limiter=None,
    retries: int = 3,
    close_exchange: bool = True,
) -> Decimal:
    """获取账户余额（带重试机制，防止API限流）

    Args:
        exchange: 交易所实例
        symbol: 交易对
        marketType: 市场类型
        api_limiter: API限流器
        retries: 重试次数（默认3次）
        close_exchange: 是否关闭exchange（默认True，保持向后兼容）

    Returns:
        Decimal: 账户余额（失败返回0，会导致开仓失败，这是安全的）
    """
    try:
        for attempt in range(retries):
            try:
                # ✅ 调用全局API限流器
                if api_limiter:
                    await api_limiter.check_and_wait()

                params = {}
                if symbol:
                    trading_pair = symbol.replace("-", ",")
                    params = {"ccy": trading_pair, "type": marketType}
                balance = await exchange.fetch_balance(params)
                total_equity = Decimal(str(balance["USDT"]["total"]))
                return total_equity

            except Exception as e:
                error_str = str(e)

                # RateLimitExceeded - 指数退避
                if "Too Many Requests" in error_str or "50011" in error_str:
                    if attempt < retries - 1:
                        delay = (attempt + 1) * 2.0 + random.uniform(0.5, 1.5)
                        logging.warning(
                            f"⏳ 获取账户余额限流，等待{delay:.1f}s后重试 ({attempt+1}/{retries}): {symbol}"
                        )
                        await asyncio.sleep(delay)
                        continue

                # 网络错误 - 快速重试
                elif "Network" in error_str or "Timeout" in error_str:
                    if attempt < retries - 1:
                        await asyncio.sleep(0.5)
                        continue

                # 最后一次重试失败
                if attempt == retries - 1:
                    logging.error(
                        f"❌ 获取账户余额多次失败（已重试{retries}次）: {symbol}, {e}"
                    )
                    return Decimal("0")  # ✅ 返回0会导致开仓失败，这是安全的
                else:
                    # 其他错误 - 通用重试
                    logging.warning(
                        f"⚠️ 获取账户余额失败，重试中 ({attempt+1}/{retries}): {symbol}, {e}"
                    )
                    await asyncio.sleep(1.0)
                    continue

        return Decimal("0")
    finally:
        # ✅ 保持向后兼容：默认关闭exchange
        if close_exchange:
            await exchange.close()


async def cleanup_opposite_positions(
    self, exchange: ccxt.Exchange, account_id: int, symbol: str, direction: str
):
    """平掉相反方向仓位"""
    try:
        # 从订单表中获取未平仓的反向订单数据
        unclosed_opposite_orders = await self.db.get_unclosed_opposite_orders(
            account_id, symbol, direction
        )
        # print("未平仓的反向订单数据:", unclosed_opposite_orders)
        logging.info(f"未平仓的反向订单数据: {unclosed_opposite_orders}")
        if not unclosed_opposite_orders:
            # print("未找到未平仓的反向订单")
            logging.info(f"未找到未平仓的反向订单")
            return
        for order in unclosed_opposite_orders:
            order_id = order["id"]
            order_side = order["side"]
            order_size = (
                Decimal(str(order["filled"]))
                if "filled" in order
                else Decimal(str(order["amount"]))
            )

            if order_side != direction and order_size > 0:
                # print("orderId:", order_id)
                close_side = "sell" if order_side == "long" else "buy"
                market_price = await get_market_price(
                    exchange,
                    symbol,
                    self.api_limiter if hasattr(self, "api_limiter") else None,
                    close_exchange=False,
                )
                client_order_id = await get_client_order_id()
                # 执行平仓操作
                close_order = await self.open_position(
                    account_id,
                    symbol,
                    close_side,
                    order_side,
                    float(order_size),
                    market_price,
                    "market",
                    client_order_id,
                    True,  # 设置为平仓
                )

                if not close_order:
                    await asyncio.sleep(0.2)
                    continue

                await self.db.update_order_by_id(account_id, order_id, {"is_clopos": 1})

                # 记录平仓订单和持仓
                await self.db.add_order(
                    {
                        "account_id": account_id,
                        "symbol": symbol,
                        "order_id": close_order["id"],
                        "price": float(market_price),
                        "executed_price": None,
                        "quantity": float(order_size),
                        "pos_side": order_side,
                        "order_type": "market",
                        "side": close_side,
                        "status": "filled",
                        "is_clopos": 1,
                    }
                )
                # await self.record_order(
                #     exchange,
                #     account_id,
                #     close_order['id'],
                #     market_price,
                #     order_size,
                #     symbol,
                # )
                # 进行更新订单状态
                await asyncio.sleep(0.2)

    except Exception as e:
        # print(f"清理仓位失败: {e}")
        logging.error(f"清理仓位失败: {e}")
    finally:
        await exchange.close()  # ✅ 用完就关


# 在 common_functions.py 中修改这个函数

async def milliseconds_to_local_datetime(milliseconds: int) -> str:
    """
    将毫秒时间戳转换为 UTC 时间的字符串格式
    :param milliseconds: 毫秒时间戳
    :return: 格式化的时间字符串 (YYYY-MM-DD HH:MM:SS)
    """
    # ✅ 添加安全检查：处理 0 或无效时间戳
    if not milliseconds or milliseconds <= 0:
        # 返回当前时间作为默认值
        return datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    
    try:
        # 将毫秒时间戳转换为秒
        seconds = int(milliseconds) / 1000.0
        
        # ✅ 添加时间戳有效性检查（防止年份过小或过大）
        if seconds < 0 or seconds > 253402300799:  # 检查是否在合理范围内
            return datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        # 转换为 UTC 时间
        utc_time = datetime.fromtimestamp(seconds, tz=timezone.utc)
        # 格式化为字符串
        formatted_time = utc_time.strftime("%Y-%m-%d %H:%M:%S")
        return formatted_time
    except (ValueError, OSError, OverflowError) as e:
        # ✅ 捕获可能的异常，返回当前时间
        logging.warning(f"⚠️ 时间戳转换失败: {milliseconds}, 错误: {e}")
        return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


async def get_latest_filled_price_from_position_history(
    exchange, symbol: str, pos_side: str = None
) -> Decimal:
    """
    获取最近一条历史持仓记录的成交价 avgPx（适配 OKX 双向持仓）

    :param exchange: ccxt.okx 实例
    :param symbol: 交易对（如 'BTC/USDT:USDT'）
    :param pos_side: 'long' 或 'short'，可选
    :return: Decimal 类型的成交价，没有记录则返回 Decimal('0')
    """
    try:
        # 只请求最近一条记录（limit = 1）
        positions = await exchange.fetch_position_history(symbol, None, 1)

        if not positions:
            # print("⚠️ 没有历史持仓记录")
            logging.error(f"没有历史持仓记录")
            return Decimal("0")

        pos = positions[0]

        # 检查方向（可选）
        if pos_side and pos["info"].get("direction") != pos_side.upper():
            # print(f"⚠️ 最近的记录方向是 {pos['info'].get('direction')}，不匹配 {pos_side}")
            return Decimal("0")

        avg_px = pos["info"].get("closeAvgPx", "0")
        # print(f"✅ 最近成交价: {avg_px}（方向: {pos['info'].get('direction')}）")

        return Decimal(avg_px)

    except Exception as e:
        # print(f"❌ 获取最新持仓历史失败: {e}")
        logging.error(f"获取最新持仓历史失败: {e}")
        return Decimal("0")
    finally:
        await exchange.close()


async def fetch_positions_with_retry(
    exchange,
    account_id: int,
    symbol: str = "",
    params: dict = None,
    retries: int = 3,
    api_limiter=None,
    timeout: float = 10.0,
):
    """
    带重试机制的持仓查询（防止API限流和网络抖动）

    :param exchange: ccxt 交易所实例
    :param account_id: 账户 ID
    :param symbol: 交易对（空字符串表示所有持仓）
    :param params: 查询参数（如 {"instType": "SWAP"}）
    :param retries: 重试次数
    :param api_limiter: 全局API限流器
    :param timeout: 单次请求超时时间（秒）
    :return: 持仓列表或 None
    """
    if params is None:
        params = {"instType": "SWAP"}

    logging.debug(
        f"🔍 查询持仓: 账户={account_id}, 币种={symbol or '全部'}, 超时={timeout}秒"
    )

    for attempt in range(retries):
        try:
            # ✅ 调用全局API限流器
            if api_limiter:
                await api_limiter.check_and_wait()

            # 添加超时控制
            positions = await asyncio.wait_for(
                exchange.fetch_positions(symbol, params), timeout=timeout
            )

            logging.debug(f"✅ 账户 {account_id} 获取持仓成功: {len(positions)} 个持仓")
            return positions

        except asyncio.TimeoutError:
            if attempt < retries - 1:
                delay = (attempt + 1) * 1.0  # 超时后延迟更久
                logging.warning(
                    f"⏱️ 账户 {account_id} 获取持仓超时，等待 {delay:.1f}s 后重试 ({attempt+1}/{retries})"
                )
                await asyncio.sleep(delay)
                continue
            else:
                logging.error(f"❌ 账户 {account_id} 获取持仓多次超时")
                return None

        except Exception as e:
            error_str = str(e)

            # 针对不同错误类型采取不同策略
            if "Too Many Requests" in error_str or "50011" in error_str:
                # API 限流错误 - 使用指数退避
                if attempt < retries - 1:
                    delay = (attempt + 1) * 2.0 + random.uniform(0.5, 1.5)
                    logging.warning(
                        f"⏳ 账户 {account_id} API 限流，等待 {delay:.2f}s 后重试 ({attempt+1}/{retries})"
                    )
                    await asyncio.sleep(delay)
                    continue

            elif "Network" in error_str or "Timeout" in error_str:
                # 网络错误 - 快速重试
                if attempt < retries - 1:
                    delay = 0.5
                    logging.warning(
                        f"🌐 账户 {account_id} 网络错误，等待 {delay:.1f}s 后重试 ({attempt+1}/{retries}): {e}"
                    )
                    await asyncio.sleep(delay)
                    continue

            elif "Invalid" in error_str or "Permission" in error_str:
                # 配置错误 - 不重试
                logging.error(f"❌ 账户 {account_id} 配置错误（不重试）: {e}")
                return None

            # 其他错误 - 重试一次
            if attempt < retries - 1:
                delay = 1.0
                logging.warning(
                    f"⚠️ 账户 {account_id} 获取持仓失败，重试中 ({attempt+1}/{retries}): {e}"
                )
                await asyncio.sleep(delay)
                continue
            else:
                logging.error(
                    f"❌ 账户 {account_id} 获取持仓多次重试仍失败: {e}", exc_info=True
                )
                return None

    return None


async def fetch_order_with_retry(
    exchange,
    account_id: int,
    order_id: str,
    symbol: str,
    params: dict = None,
    retries: int = 3,
    api_limiter=None,
):
    """
    带重试机制的单个订单查询（防止API限流）

    :param exchange: ccxt 交易所实例
    :param account_id: 账户 ID
    :param order_id: 订单 ID
    :param symbol: 交易对
    :param params: 查询参数（如 {"instType": "SWAP"}）
    :param retries: 重试次数
    :param api_limiter: 全局API限流器
    :return: 订单信息或 None
    """
    if params is None:
        params = {}

    logging.debug(f"🔍 查询订单: 账户={account_id}, 订单ID={order_id}, 币种={symbol}")

    for attempt in range(retries):
        try:
            # ✅ 调用全局API限流器
            if api_limiter:
                await api_limiter.check_and_wait()

            order_info = await exchange.fetch_order(order_id, symbol, params)

            # 记录订单详细信息
            state = order_info.get("info", {}).get("state", "unknown")
            filled = order_info.get("filled", 0)
            amount = order_info.get("amount", 0)
            logging.debug(
                f"✅ 查询订单成功: 账户={account_id}, 订单={order_id}, "
                f"状态={state}, 已成交={filled}/{amount}"
            )
            return order_info
        except Exception as e:
            error_str = str(e)

            # 订单已不存在（常见于本地记录滞后/交易所已清理），按可恢复场景处理
            if "51603" in error_str or "Order does not exist" in error_str:
                logging.info(
                    f"ℹ️ 账户 {account_id} 订单不存在，按已失效处理: "
                    f"订单={order_id}, 币种={symbol}"
                )
                return None

            if "Too Many Requests" in error_str and attempt < retries - 1:
                # 使用指数退避 + 随机抖动来缓解限流
                delay = (attempt + 1) * 0.5 + random.uniform(0.1, 0.3)
                logging.warning(
                    f"⏳ 账户 {account_id} 查询订单请求过多，等待 {delay:.2f}s 后重试 ({attempt+1}/{retries})... order_id={order_id}"
                )
                await asyncio.sleep(delay)
                continue
            else:
                logging.error(
                    f"❌ 账户 {account_id} 查询订单失败: 订单={order_id}, 币种={symbol}, 错误={e}"
                )
                return None

    logging.error(f"❌ 账户 {account_id} 查询订单 {order_id} 多次重试仍失败")
    return None


async def cancel_all_orders(
    self,
    exchange,
    account_id: int,
    symbol: str,
    cancel_conditional: bool = False,
    close_exchange: bool = True,
):
    """
    取消所有未成交订单（普通 + 条件单）

    :param exchange: ccxt 交易所实例
    :param account_id: 账户 ID
    :param symbol: 交易对，如 'BTC/USDT:USDT'
    :param side: 'buy', 'sell', 'all'，指定取消的订单方向
    :param cancel_conditional: bool, 是否取消条件单（策略单）
    """

    async def fetch_orders(params: dict, retries=3):
        """带限流与重试机制的 fetch_open_orders"""
        for attempt in range(retries):
            try:
                # ✅ 调用全局API限流器
                if hasattr(self, "api_limiter") and self.api_limiter:
                    await self.api_limiter.check_and_wait()

                return await exchange.fetch_open_orders(symbol, None, None, params)
            except Exception as e:
                if "Too Many Requests" in str(e):
                    delay = (attempt + 1) * 0.6 + random.uniform(0.1, 0.5)
                    logging.warning(
                        f"⏳ 用户 {account_id} 请求过多，等待 {delay:.2f}s 后重试 ({attempt+1}/{retries})..."
                    )
                    await asyncio.sleep(delay)
                    continue
                else:
                    logging.error(
                        f"用户 {account_id} 获取未成交订单失败 params={params}: {e}"
                    )
                    return []
        logging.error(f"用户 {account_id} 获取未成交订单多次重试仍失败 params={params}")
        return []

    async def cancel_orders(order_list: list, params: dict):
        """批量撤销订单"""
        canceled_count = 0
        for order in order_list:
            try:
                # ✅ 调用全局API限流器
                if hasattr(self, "api_limiter") and self.api_limiter:
                    await self.api_limiter.check_and_wait()

                order_id = order["id"]
                logging.debug(f"🗑️ 取消订单: 账户={account_id}, 订单={order_id[:15]}...")

                cancel_order = await exchange.cancel_order(order_id, symbol, params)

                if cancel_order.get("info", {}).get("sCode") == "0":
                    canceled_count += 1
                    logging.info(
                        f"✅ 订单取消成功: 账户={account_id}, 订单={order_id[:15]}..."
                    )

                    existing_order = await self.db.get_order_by_id(account_id, order_id)
                    if existing_order:
                        await self.db.update_order_by_id(
                            account_id, order_id, {"status": "canceled"}
                        )
                        logging.debug(f"✅ 数据库订单状态已更新为 canceled")
                else:
                    error_msg = cancel_order.get("info", {}).get("sMsg", "未知错误")
                    logging.warning(
                        f"⚠️ 订单取消失败: 账户={account_id}, 订单={order_id[:15]}..., "
                        f"错误={error_msg}"
                    )
            except Exception as e:
                logging.error(
                    f"❌ 取消订单异常: 账户={account_id}, 订单={order.get('id', 'N/A')[:15]}..., "
                    f"错误={e}"
                )

        if canceled_count > 0:
            logging.info(f"✅ 成功取消 {canceled_count}/{len(order_list)} 个订单")

    try:
        logging.info(
            f"🗑️ 开始取消订单: 账户={account_id}, 币种={symbol}, 包含条件单={cancel_conditional}"
        )

        # 1️⃣ 取消普通订单（永续合约）
        normal_params = {"instType": "SWAP"}
        normal_orders = await fetch_orders(normal_params)
        if normal_orders:
            logging.info(
                f"📝 账户 {account_id} 找到 {len(normal_orders)} 个普通订单待取消: "
                f"{[o['id'] for o in normal_orders[:5]]}"
            )
            await cancel_orders(normal_orders, normal_params)
        else:
            logging.debug(f"账户 {account_id} 无普通订单需要取消")

        # 2️⃣ 取消条件单（策略单）—— 由 cancel_conditional 控制
        if cancel_conditional:
            conditional_params = {
                "instType": "SWAP",
                "ordType": "conditional",
                "trigger": True,
            }
            conditional_orders = await fetch_orders(conditional_params)
            if conditional_orders:
                logging.info(
                    f"📝 账户 {account_id} 找到 {len(conditional_orders)} 个条件单待取消"
                )
            else:
                logging.debug(f"账户 {account_id} 无条件单需要取消")
            if conditional_orders:
                await cancel_orders(conditional_orders, conditional_params)
            else:
                logging.debug(f"账户 {account_id} 无条件单需要取消")
        else:
            logging.debug(f"账户 {account_id} 跳过取消条件单")

        logging.info(f"✅ 取消订单完成: 账户={account_id}, 币种={symbol}")

    except Exception as e:
        logging.error(
            f"❌ 取消订单失败: 账户={account_id}, 币种={symbol}, 错误={e}",
            exc_info=True,
        )
    finally:
        if close_exchange:
            await exchange.close()


async def get_max_position_value(self, account_id: int, symbol: str) -> Decimal:
    """根据交易对匹配对应的最大仓位值"""
    normalized_symbol = symbol.upper().replace("-SWAP", "")
    max_position_list = self.db.account_config_cache.get(account_id, {}).get(
        "max_position_list", []
    )
    max_position_list_arr = json.loads(max_position_list)
    for item in max_position_list_arr:
        if item.get("symbol") == normalized_symbol:
            return Decimal(item.get("value"))
    return Decimal("0")  # 如果没有匹配到，返回0


async def get_grid_percent_list(self, account_id: int, direction: str) -> Decimal:
    """根据交易对匹配对应的最大仓位值"""
    grid_percent_list = self.db.account_config_cache.get(account_id, {}).get(
        "grid_percent_list", []
    )
    grid_percent_list_arr = json.loads(grid_percent_list)
    for item in grid_percent_list_arr:
        if item.get("direction") == direction:
            return item
    return []  # 如果没有匹配到，返回[]


async def fetch_positions_history(
    self,
    account_id: int,
    inst_type: str = "SWAP",
    inst_id: str = None,
    limit: str = "100",
    after: str = None,
    before: str = None,
):
    """
    分页获取历史持仓记录（已平仓仓位）
    :param account_id: 账户ID
    :param inst_type: 交易类型，如 SWAP
    :param inst_id: 指定交易对（如 BTC-USDT-SWAP），可选
    :param limit: 每页获取数量（最大100）
    :return: 历史持仓记录列表
    """
    exchange = await get_exchange(self, account_id)
    if not exchange:
        return []

    history = []
    while True:
        try:
            params = {
                "instType": inst_type,
                "after": after,
                "before": before,
            }

            # 调用交易所接口获取历史持仓
            result = await exchange.fetch_positions_history(
                [inst_id], None, limit, params
            )

            # 如果返回结果为空，说明已经获取完所有数据
            if not result:
                break

            return result

        except Exception as e:
            # print(f"获取历史持仓失败: {e}")
            logging.error(f"获取历史持仓失败: {e}")
            break
        finally:
            await exchange.close()


async def fetch_current_positions(
    self,
    account_id: int,
    symbol: str,
    inst_type: str = "SWAP",
    exchange: ccxt.Exchange = None,
    close_exchange: bool = True,
) -> list:
    """获取当前持仓信息，返回持仓数据列表"""
    created_exchange = False
    current_exchange = exchange
    try:
        # ✅ 调用全局API限流器
        if hasattr(self, "api_limiter") and self.api_limiter:
            await self.api_limiter.check_and_wait()

        if current_exchange is None:
            current_exchange = await get_exchange(self, account_id)
            created_exchange = True
        if not current_exchange:
            logging.error(f"❌ 获取持仓失败：无法获取交易所对象 - 账户={account_id}")
            raise Exception("无法获取交易所对象")

        logging.debug(f"🔍 查询当前持仓: 账户={account_id}, 币种={symbol}")
        positions = await current_exchange.fetch_positions_for_symbol(
            symbol, {"instType": inst_type}
        )

        # 统计持仓信息
        position_summary = []
        for pos in positions:
            contracts = pos.get("contracts", 0)
            if contracts != 0:
                side = pos.get("side", "unknown")
                entry_price = pos.get("entryPrice", 0)
                position_summary.append(f"{side}:{contracts}@{entry_price}")

        if position_summary:
            logging.debug(
                f"📊 查询到持仓: 账户={account_id}, 币种={symbol}, "
                f"详情=[{', '.join(position_summary)}]"
            )
        else:
            logging.debug(f"📭 无持仓: 账户={account_id}, 币种={symbol}")

        return positions

    except Exception as e:
        logging.error(
            f"❌ 获取当前持仓失败: 账户={account_id}, 币种={symbol}, 错误={e}",
            exc_info=True,
        )
        return []
    finally:
        if close_exchange and created_exchange and current_exchange:
            await current_exchange.close()


# 获取账户总持仓数
async def get_total_positions(
    self,
    account_id: int,
    symbol: str,
    inst_type: str = "SWAP",
    cached_positions: list = None,
    exchange: ccxt.Exchange = None,
) -> Decimal:
    """获取账户总持仓数

    Args:
        account_id: 账户ID
        symbol: 交易对
        inst_type: 交易类型
        cached_positions: 缓存的持仓数据，如果提供则不再查询

    Returns:
        总持仓数量
    """
    try:
        # ✅ 优先使用缓存的持仓数据
        if cached_positions is not None:
            position_details = []
            total_positions = Decimal("0")
            for position in cached_positions:
                pos = abs(Decimal(position["info"]["pos"]))
                side = position.get("side", "unknown")
                total_positions += pos
                if pos > 0:
                    position_details.append(f"{side}:{pos}")

            logging.info(
                f"📊 使用缓存计算总持仓: 账户={account_id}, 币种={symbol}, "
                f"总持仓={total_positions}, 详情=[{', '.join(position_details)}]"
            )
            return total_positions

        # 如果没有缓存，才查询
        logging.info(f"🔍 查询持仓: 账户={account_id}, 币种={symbol}")
        positions = await fetch_current_positions(
            self,
            account_id,
            symbol,
            inst_type,
            exchange=exchange,
            close_exchange=exchange is None,
        )

        position_details = []
        total_positions = Decimal("0")
        for position in positions:
            pos = abs(Decimal(position["info"]["pos"]))
            side = position.get("side", "unknown")
            total_positions += pos
            if pos > 0:
                position_details.append(f"{side}:{pos}")

        logging.info(
            f"📊 查询到总持仓: 账户={account_id}, 币种={symbol}, "
            f"总持仓={total_positions}, 详情=[{', '.join(position_details)}]"
        )
        return total_positions

    except Exception as e:
        logging.error(
            f"❌ 获取账户总持仓数失败: 账户={account_id}, 币种={symbol}, 错误={e}",
            exc_info=True,
        )
        return Decimal("0")


# 更新订单状态以及进行配对订单、计算利润
async def update_order_status(
    self,
    order: dict,
    account_id: int,
    executed_price: float = None,
    fill_date_time: str = None,
):
    """更新订单状态以及进行配对订单、计算利润"""
    # exchange = await get_exchange(self, account_id)
    # if not exchange:
    #     return
    # print("开始匹配订单")
    logging.info(f"开始匹配订单")
    side = "sell" if order["side"] == "buy" else "buy"
    get_order_by_price_diff = await self.db.get_order_by_price_diff_v2(
        account_id, order["info"]["instId"], executed_price, side
    )
    # print("get_order_by_price_diff", get_order_by_price_diff)
    profit = 0
    group_id = ""
    # new_price = await get_market_price(exchange, order['info']['instId'])
    # print(f"最新价格: {new_price}")
    if get_order_by_price_diff:
        if order["side"] == "sell" and (
            Decimal(executed_price)
            >= Decimal(get_order_by_price_diff["executed_price"])
        ):
            # if order['side'] == 'buy':
            # 计算利润
            group_id = str(uuid.uuid4())
            profit = (
                Decimal(executed_price)
                - Decimal(get_order_by_price_diff["executed_price"])
            ) * Decimal(min(order["amount"], get_order_by_price_diff["quantity"]))
            print(f"配对订单成交，利润 buy: {profit}")
            logging.info(f"配对订单成交，利润 buy: {profit}")
        if order["side"] == "buy" and (
            Decimal(executed_price)
            <= Decimal(get_order_by_price_diff["executed_price"])
        ):
            # if order['side'] == 'sell':
            # 计算利润
            group_id = str(uuid.uuid4())
            profit = (
                Decimal(get_order_by_price_diff["executed_price"])
                - Decimal(executed_price)
            ) * Decimal(min(order["amount"], get_order_by_price_diff["quantity"]))
            # print(f"配对订单成交，利润 sell: {profit}")
            logging.info(f"配对订单成交，利润 sell: {profit}")
        if profit != 0:
            await self.db.update_order_by_id(
                account_id,
                get_order_by_price_diff["order_id"],
                {"profit": profit, "position_group_id": group_id},
            )
        await self.db.update_order_by_id(
            account_id,
            order["id"],
            {
                "executed_price": executed_price,
                "status": order["info"]["state"],
                "fill_time": fill_date_time,
                "profit": profit,
                "position_group_id": group_id,
            },
        )
    else:
        await self.db.update_order_by_id(
            account_id,
            order["id"],
            {
                "executed_price": executed_price,
                "status": order["info"]["state"],
                "fill_time": fill_date_time,
            },
        )
