import ccxt
from decimal import Decimal
import asyncio

async def get_exchange(account_id: int, db):
    """获取交易所实例（通过account_id）"""
    account_info = await db.get_account_info(account_id)
    if account_info:
        exchange_id = account_info['exchange']
        exchange_class = getattr(ccxt, exchange_id)
        exchange = exchange_class({
            'apiKey': account_info['api_key'],
            'secret': account_info['api_secret'],
            'password': account_info.get('api_passphrase', None),
            'enableRateLimit': True,
        })
        return exchange
    return None

async def get_market_price(exchange: ccxt.Exchange, symbol: str) -> Decimal:
    """获取当前市场价格"""
    ticker = await exchange.fetch_ticker(symbol)
    return Decimal(str(ticker['last']))

async def get_market_precision(self, exchange: ccxt.Exchange, symbol: str, instType: str = 'SWAP') -> Tuple[Decimal, Decimal]:
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
    

async def open_position(self, account_id: int, symbol: str, side: str, pos_side: str, amount: float, price: float, order_type: str):
        """开仓、平仓下单"""
        exchange = await get_exchange(account_id)
        if not exchange:
            return None
        
        params = {
            'posSide': pos_side,
            'tdMode': 'cross'
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