import asyncio
from decimal import Decimal

from trading_bot_config import TradingBotConfig
from grid_orders2 import OKXTradingBot

class ProcessSignal:
    """交易信号处理类"""
    def __init__(self, config: TradingBotConfig):
        # if not isinstance(config, TradingBotConfig):
        #     raise TypeError("config 必须是 TradingBotConfig 类的实例")
        self.TradingBotConfig = config
            
    async def process_signal(self, signal: dict):
        """处理交易信号（完整版）"""
        account_id = signal['account_id']
        symbol = signal['symbol']
        action =  'buy' if signal['direction'] == 'long' else 'sell'  # 'buy' 或 'sell'
        size = signal['size']      # 1, 0, -1
        
        print(f"📡 接收信号: {account_id} {symbol} {action}{size}")

        try:
            # 1. 解析操作类型
            operation = self.parse_operation(action, size)
            
            # 2. 执行对应操作
            if operation['type'] == 'open':
                await self.handle_open_position(
                    account_id,
                    symbol,
                    operation['direction'],
                    operation['side'],
                    self.TradingBotConfig.get('position_percent', 0.8)
                )
            else:
                await self.handle_close_position(
                    account_id,
                    symbol,
                    operation['direction'],
                    operation['side']
                )
                
            # 3. 记录信号处理结果
            # await self.log_signal_execution(account_id, signal, operation)

        except Exception as e:
            print(f"‼️ 信号处理失败: {str(e)}")
            # await self.log_error(account_id, signal, str(e))

    # ---------- 核心子方法 ----------
    def parse_operation(self, action: str, size: int) -> dict:
        """解析信号类型"""
        if action == 'buy':
            if size == 1:   # 买入开多
                return {'type': 'open', 'side': 'buy', 'direction': 'long'}
            elif size == 0: # 买入平空
                return {'type': 'close', 'side': 'buy', 'direction': 'short'}
        else:  # sell
            if size == -1:  # 卖出开空
                return {'type': 'open', 'side': 'sell', 'direction': 'short'}
            elif size == 0: # 卖出平多
                return {'type': 'close', 'side': 'sell', 'direction': 'long'}
        raise ValueError(f"无效信号组合: action={action}, size={size}")

    async def cleanup_opposite_positions(self, account_id: int, symbol: str, direction: str):
        """平掉相反方向仓位"""
        exchange = OKXTradingBot.get_exchange(account_id)
        if not exchange:
            return

        try:
            # 从订单表中获取未平仓的反向订单数据
            unclosed_opposite_orders = await self.db.get_unclosed_opposite_orders(account_id, symbol, direction)
            print("未平仓的反向订单数据:", unclosed_opposite_orders)

            for order in unclosed_opposite_orders:
                order_id = order['id']
                order_side = order['side']
                order_size = Decimal(str(order['filled'])) if 'filled' in order else Decimal(str(order['amount']))

                if order_side != direction and order_size > 0:
                    print("orderId:", order_id)
                    close_side = 'sell' if order_side == 'long' else 'buy'
                    market_price = await self.get_market_price(exchange, symbol)

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

                    # 记录平仓订单和持仓
                    await self.record_order(
                        exchange,
                        account_id,
                        close_order['id'],
                        market_price,
                        order_size,
                        symbol,
                        1
                    )
                    await asyncio.sleep(0.2)

        except Exception as e:
            print(f"清理仓位失败: {e}")

                
    async def handle_open_position(self, account_id: int, symbol: str, 
                                direction: str, side: str, percent: float):
        """处理开仓"""
        print(f"⚡ 开仓操作: {direction} {side}")
        
        # 1. 清理反向仓位
        await self.cleanup_opposite_positions(account_id, symbol, direction)
        
        # 2. 计算开仓量
        size = await self.calculate_position_size(account_id, symbol, percent)
        if size <= 0:
            return
            
        # 3. 获取市场价格
        exchange = OKXTradingBot.get_exchange(account_id)
        price = await self.get_market_price(exchange, symbol)
        
        # 4. 下单并记录
        order = await exchange.create_order(
            symbol,
            'limit',
            side,
            float(size),
            float(price),
            {
                'leverage': self.config.leverage,
                'posSide': direction.upper()  # 兼容OKX
            }
        )
        
        if order:
            await self.record_order(
                account_id=account_id,
                order_id=order['id'],
                symbol=symbol,
                side=side,
                order_type='limit',
                quantity=size,
                price=price,
                status='open'
            )

    async def handle_close_position(self, account_id: int, symbol: str, 
                                direction: str, side: str):
        """处理平仓"""
        print(f"⚡ 平仓操作: {direction} {side}")
        
        # 1. 从数据库计算净持仓
        net_size = await self.calculate_net_position(account_id, symbol, direction)
        if net_size <= 0:
            print(f"✅ 无{direction}方向持仓可平")
            return
            
        # 2. 执行平仓
        exchange = OKXTradingBot.get_exchange(account_id)
        order = await exchange.create_order(
            symbol,
            'market',
            side,
            float(net_size),
            None,
            {'reduceOnly': True}
        )
        
        if order:
            # 3. 更新订单状态
            await self.mark_orders_as_closed(
                account_id,
                symbol,
                direction,
                order['id']
            )
            
            # 4. 记录平仓订单
            await self.record_order(
                account_id=account_id,
                order_id=order['id'],
                symbol=symbol,
                side=side,
                order_type='market',
                quantity=net_size,
                price=await self.get_market_price(exchange, symbol),
                status='closed'
            )

    async def calculate_net_position(self, account_id: int, symbol: str, direction: str) -> Decimal:
        """从订单表计算净持仓"""
        conn = self.get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT 
                        SUM(CASE 
                            WHEN side = %s AND status IN ('filled', 'closed') THEN quantity 
                            ELSE 0 
                        END) -
                        SUM(CASE 
                            WHEN side != %s AND status IN ('filled', 'closed') THEN quantity 
                            ELSE 0 
                        END) as net_size
                    FROM orders
                    WHERE account_id = %s 
                    AND symbol = %s
                    AND status IN ('filled', 'closed')
                """, (direction, direction, account_id, symbol))
                result = cursor.fetchone()
                return Decimal(str(result['net_size'])) if result['net_size'] else Decimal('0')
        finally:
            conn.close()