from typing import Tuple
import ccxt
from decimal import Decimal
from database import Database
import asyncio

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
    

async def open_position(self, account_id: int, symbol: str, side: str, pos_side: str, amount: float, price: float, order_type: str):
        """开仓、平仓下单"""
        exchange = await get_exchange(self, account_id)
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

async def calculate_position_size(self, exchange: ccxt.Exchange, symbol: str, position_percent: Decimal) -> Decimal:
    """计算仓位大小"""
    try:
        trading_pair = symbol.replace("-", ",")
        balance = exchange.fetch_balance({"ccy": trading_pair})
        total_equity = Decimal(str(balance["USDT"]['total']))
        print(f"账户余额: {total_equity}")
        price = await get_market_price(exchange, symbol)
        market_precision = await get_market_precision(exchange, symbol, 'SWAP')
        print("market_precision", market_precision)
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
        # print("未平仓的反向订单数据:", unclosed_opposite_orders)
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

                # 执行平仓操作
                close_order = await self.open_position(
                    account_id,
                    symbol,
                    close_side,
                    order_side,
                    float(order_size),
                    market_price,
                    'market'
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


async def close_filled_order(self, exchange: ccxt.Exchange, account_id: int, order: dict, opposite_direction: False):
    """根据已成交订单执行平仓（精确匹配持仓）"""
    try:
        symbol = order['symbol']
        order_id = order['id']
        
        # # 获取订单详细信息（确保是最新状态）
        # order_info = exchange.fetch_order(order_id, symbol)
        # if not order_info:
        #     print(f"无法获取订单信息: {order_id}")
        #     return False
            
        # 检查订单是否已成交
        if order['status'] != 'closed' and order['filled'] <= 0:
            print(f"订单未成交: {order_id} (状态: {order['status']})")
            return False
            
        # 获取当前所有持仓
        positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
        # print("当前持仓数据:", positions)
        
        # 匹配对应方向的持仓
        target_pos = None
        order_side = order['side']  # 原订单方向
        pos_side = order.get('info', {}).get('posSide')  # 从订单信息获取持仓方向

        if opposite_direction:
            pos_side = 'long' if pos_side == 'short' else 'short'
        
        for pos in positions:
            # 根据不同交易所的字段匹配
            if (pos['side'] == pos_side or 
                (pos['side'] == 'long' and order_side == 'buy') or
                (pos['side'] == 'short' and order_side == 'sell')):
                target_pos = pos
                break
                
        if not target_pos or Decimal(str(target_pos['contracts'])) <= 0:
            print(f"找不到匹配的持仓: 订单方向={order_side}, 持仓方向={pos_side}")
            return False
            
        # 确定平仓参数
        close_side = 'sell' if order_side == 'buy' else 'buy'
        close_size = min(
            Decimal(str(order['filled'])),  # 订单成交量
            Decimal(str(target_pos['contracts']))  # 当前持仓量
        )
        
        # 执行平仓
        print(f"执行平仓: {symbol} {close_side} {close_size}")
        close_order = await open_position(
            account_id,
            symbol,
            close_side,
            target_pos['side'],  # 实际持仓方向
            float(close_size),
            None,  # 市价单
            'market'
        )
        
        if not close_order:
            print("平仓订单创建失败")
            return False

        await self.db.update_order_by_id(account_id, order_id, {'clopos_status': 1})

        # 记录平仓订单
        market_price = await get_market_price(exchange, symbol)
        await self.db.add_order({
            'account_id': account_id,
            'symbol': symbol,
            'order_id': close_order['id'],
            'price': float(market_price),
            'executed_price': None,
            'quantity': float(order_size),
            'pos_side': target_pos['side'],
            'order_type': 'market',
            'side': target_pos['side'],
            'status': 'filled',
            'is_clopos': 1,
        })
        # await self.record_order(
        #     exchange,
        #     account_id,
        #     close_order['id'],
        #     market_price,
        #     close_size,
        #     symbol,
        #     1
        # )
        
        print(f"平仓成功: 订单ID {close_order['id']}")
        return True
        
    except Exception as e:
        print(f"平仓过程中出错: {e}")
        import traceback
        traceback.print_exc()
        return False