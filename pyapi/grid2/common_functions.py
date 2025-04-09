from typing import Tuple
import ccxt
from decimal import Decimal
from database import Database
import asyncio
from datetime import datetime, timezone
import uuid

async def get_exchange(self, account_id: int) -> ccxt.Exchange:
    """获取交易所实例（通过account_id）"""
    account_info = await self.db.get_account_info(account_id)
    if account_info:
        exchange_id = account_info['exchange']
        exchange_class = getattr(ccxt, exchange_id)
        exchange = exchange_class({
            'apiKey': account_info['api_key'],
            'secret': account_info['api_secret'],
            'password': account_info.get('api_passphrase', None),
            "options": {"defaultType": "swap"},
            # 'enableRateLimit': True,
        })
        exchange.set_sandbox_mode(True)  # 开启模拟交易
        return exchange
    return None

async def get_market_price(exchange: ccxt.Exchange, symbol: str) -> Decimal:
    """获取当前市场价格"""
    ticker = exchange.fetch_ticker(symbol)
    return Decimal(str(ticker['last']))

async def get_market_precision(exchange: ccxt.Exchange, symbol: str, instType: str = 'SWAP') -> Tuple[Decimal, Decimal]:
    """获取市场的价格和数量精度"""
    try:
        markets = exchange.fetch_markets_by_type(instType, {'instId': f"{symbol}"})
        price_precision = Decimal(str(markets[0]['precision']['price']))
        amount_precision = Decimal(str(markets[0]['precision']['amount']))
        return {
            'price': price_precision,
            'amount': amount_precision,
        }
    except Exception as e:
        print(f"获取市场精度失败: {e}")
        return Decimal('0.0001'), Decimal('0.0001')  # 设置默认精度值

async def get_client_order_id():
    prefix = 'Zx'
    date_str = datetime.now().strftime('%Y%m%d')
    unique_str = str(uuid.uuid4()).replace('-', '')[6:19]  # 模仿 uniqid() 第7到19位
    ascii_str = ''.join(str(ord(c)) for c in unique_str)[:8]
    client_order_id = prefix + date_str + ascii_str
    return client_order_id


async def open_position(self, account_id: int, symbol: str, side: str, pos_side: str, amount: float, price: float, order_type: str, client_order_id: str = None, is_reduce_only: bool = False):
        """开仓、平仓下单"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return None
        
        params = {
            'posSide': pos_side,
            'tdMode': 'cross',
            'clOrdId': client_order_id,
            'reduceOnly': is_reduce_only,
        }
        try:
            # print("create_order", symbol, direction, price, amount)
            order = exchange.create_order(
                symbol=symbol,
                type=order_type,
                side=side,
                amount=float(amount),
                price=price,
                params=params
            )
            # print("order", order)
            if order['info'].get('sCode') == '0':
                return order
            else:
                print(f"开仓失败: {order['info'].get('sMsg', '未知错误')}")
                return None
        except Exception as e:
            print(f"开仓失败: {e}")
            raise

async def calculate_position_size(self, exchange: ccxt.Exchange, symbol: str, position_percent: Decimal, price: float) -> Decimal:
    """计算仓位大小"""
    try:
        trading_pair = symbol.replace("-", ",")
        balance = exchange.fetch_balance({"ccy": trading_pair})
        total_equity = Decimal(str(balance["USDT"]['total']))
        # print(f"账户余额: {total_equity}")
        # price = await get_market_price(exchange, symbol)
        market_precision = await get_market_precision(exchange, symbol, 'SWAP')
        # print("market_precision", market_precision)
        position_size = (total_equity * position_percent) / (price * Decimal(market_precision['amount']))
        position_size = position_size.quantize(Decimal(market_precision['price']), rounding='ROUND_DOWN')
        return min(position_size, self.config.max_position)
    except Exception as e:
        print(f"计算仓位失败: {e}")
        return Decimal('0')
    

async def cleanup_opposite_positions(self, exchange: ccxt.Exchange, account_id: int, symbol: str, direction: str):
    """平掉相反方向仓位"""
    try:
        # 从订单表中获取未平仓的反向订单数据
        unclosed_opposite_orders = await self.db.get_unclosed_opposite_orders(account_id, symbol, direction)
        print("未平仓的反向订单数据:", unclosed_opposite_orders)
        if not unclosed_opposite_orders:
            print("未找到未平仓的反向订单")
            return
        for order in unclosed_opposite_orders:
            order_id = order['id']
            order_side = order['side']
            order_size = Decimal(str(order['filled'])) if 'filled' in order else Decimal(str(order['amount']))

            if order_side != direction and order_size > 0:
                print("orderId:", order_id)
                close_side = 'sell' if order_side == 'long' else 'buy'
                market_price = await get_market_price(exchange, symbol)
                client_order_id = await get_client_order_id()
                # 执行平仓操作
                close_order = await self.open_position(
                    account_id,
                    symbol,
                    close_side,
                    order_side,
                    float(order_size),
                    market_price,
                    'market',
                    client_order_id,
                    True,  # 设置为平仓
                )

                if not close_order:
                    await asyncio.sleep(0.2)
                    continue
                
                await self.db.update_order_by_id(account_id, order_id, {'is_clopos': 1})

                # 记录平仓订单和持仓
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': close_order['id'],
                    'price': float(market_price),
                    'executed_price': None,
                    'quantity': float(order_size),
                    'pos_side': order_side,
                    'order_type': 'market',
                    'side': close_side,
                    'status': 'filled',
                    'is_clopos': 1,
                })
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
        print(f"清理仓位失败: {e}")  


async def milliseconds_to_local_datetime(milliseconds: int) -> str:
    """
    将毫秒时间戳转换为 UTC 时间的字符串格式
    :param milliseconds: 毫秒时间戳
    :return: 格式化的时间字符串 (YYYY-MM-DD HH:MM:SS)
    """
    # 将毫秒时间戳转换为秒
    seconds = int(milliseconds) / 1000.0
    # 转换为 UTC 时间
    utc_time = datetime.fromtimestamp(seconds, tz=timezone.utc)
    # 格式化为字符串
    formatted_time = utc_time.strftime('%Y-%m-%d %H:%M:%S')
    return formatted_time

async def get_latest_filled_price_from_position_history(exchange, symbol: str, pos_side: str = None) -> Decimal:
    """
    获取最近一条历史持仓记录的成交价 avgPx（适配 OKX 双向持仓）

    :param exchange: ccxt.okx 实例
    :param symbol: 交易对（如 'BTC/USDT:USDT'）
    :param pos_side: 'long' 或 'short'，可选
    :return: Decimal 类型的成交价，没有记录则返回 Decimal('0')
    """
    try:
        # 只请求最近一条记录（limit = 1）
        positions = exchange.fetch_position_history(symbol, None, 1)

        if not positions:
            print("⚠️ 没有历史持仓记录")
            return Decimal('0')

        pos = positions[0]

        # 检查方向（可选）
        if pos_side and pos['info'].get('direction') != pos_side.upper():
            # print(f"⚠️ 最近的记录方向是 {pos['info'].get('direction')}，不匹配 {pos_side}")
            return Decimal('0')

        avg_px = pos['info'].get('closeAvgPx', '0')
        # print(f"✅ 最近成交价: {avg_px}（方向: {pos['info'].get('direction')}）")

        return Decimal(avg_px)

    except Exception as e:
        print(f"❌ 获取最新持仓历史失败: {e}")
        return Decimal('0')
    

async def cancel_all_orders(self, account_id: int, symbol: str):
    """取消所有未成交的订单"""
    exchange = await get_exchange(self, account_id)
    if not exchange:
        return None
    
    try:
        open_orders = exchange.fetch_open_orders(symbol, None, None, {'instType': 'SWAP'}) # 获取未成交的订单
        # print(f"未成交订单: {open_orders}")
        for order in open_orders:
            try:
                cancel_order =  exchange.cancel_order(order['id'], symbol) # 进行撤单
                print(f"取消未成交的订单: {order['id']}")
                if cancel_order['info']['sCode'] == '0':
                    existing_order = await self.db.get_order_by_id(account_id, order['id'])
                    if existing_order:
                        # 更新订单信息
                        await self.db.update_order_by_id(account_id, order['id'], {
                            'status': 'canceled'
                        })
                    # print(f"取消订单成功")
            except Exception as e:
                print(f"取消订单失败: {e}")
    except Exception as e:
        print(f"获取未成交订单失败: {e}")


