import asyncio
from decimal import Decimal
import logging
import uuid

from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import calculate_position_size, cancel_all_orders, get_exchange, get_market_price, get_market_precision, open_position, get_client_order_id

class SignalProcessingTask:
    """交易信号处理类"""
    def __init__(self, config: TradingBotConfig, db: Database):
        self.db = db
        self.config = config

    async def signal_processing_task(self):
        """信号处理任务"""
        while getattr(self, 'running', True): 
            try:
                conn = self.db.get_db_connection()
                with conn.cursor() as cursor:
                    cursor.execute(
                        "SELECT * FROM g_signals WHERE status='pending' LIMIT 1"
                    )
                    signal = cursor.fetchone()
                # print(signal)
                if signal:
                    await self.process_signal(signal)
                    with conn.cursor() as cursor:
                        cursor.execute(
                            "UPDATE g_signals SET status='processed' WHERE id=%s",
                            (signal['id'],)
                        )
                    conn.commit()
            
                await asyncio.sleep(3)
            except Exception as e:
                print(f"信号处理异常: {e}")
                logging.error(f"信号处理异常: {e}")
                await asyncio.sleep(5)
            finally:
                if 'conn' in locals():
                    conn.close()

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
                    self.config.position_percent
                )
            else:
                await self.handle_close_position(
                    account_id,
                    symbol,
                    operation['direction'],
                    operation['side']
                )

        except Exception as e:
            print(f"‼️ 信号处理失败: {str(e)}")
            logging.error(f"‼️ 信号处理失败: {str(e)}")

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
        """平掉相反方向的持仓并更新数据库订单为已平仓"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return

        try:
            # 获取持仓信息
            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})

            if not positions:
                print("无持仓信息")
                logging.warning("无持仓信息")
                return

            opposite_direction = 'short' if direction == 'long' else 'long'
            market_price = await get_market_price(exchange, symbol)

            for pos in positions:
                pos_side = pos.get('side') or pos.get('posSide') or ''
                pos_size = Decimal(str(pos.get('contracts') or pos.get('positionAmt') or 0))
                if pos_size == 0 or pos_side.lower() != opposite_direction:
                    continue  # 没有反向持仓，或持仓数量为0

                # 确定平仓方向（如原为long，则平仓方向为sell）
                close_side = 'sell' if pos_side == 'long' else 'buy'

                # 执行平仓订单
                client_order_id = await get_client_order_id()
                close_order = await open_position(
                    self,
                    account_id, 
                    symbol, 
                    close_side, 
                    pos_side, 
                    float(abs(pos_size)), 
                    None, 
                    'market',
                    client_order_id
                )

                if close_order:
                    # 记录平仓订单
                    await self.db.add_order({
                        'account_id': account_id,
                        'symbol': symbol,
                        'order_id': close_order['id'],
                        'clorder_id': client_order_id,
                        'price': float(market_price),
                        'executed_price': None,
                        'quantity': float(abs(pos_size)),
                        'pos_side': pos_side,
                        'order_type': 'market',
                        'side': close_side,
                        'status': 'filled',
                        'is_clopos': 1,
                        'position_group_id': str(uuid.uuid4()),
                    })

                    # 更新数据库中原始反向未平仓订单为已平仓
                    await self.db.mark_orders_as_closed(account_id, symbol, opposite_direction)
                    print(f"成功平掉{opposite_direction}方向持仓，更新订单状态为已平仓。")
                    logging.info(f"成功平掉{opposite_direction}方向持仓，更新订单状态为已平仓。")
                else:
                    print(f"平仓订单失败，方向: {opposite_direction}, 数量: {pos_size}")
                    logging.info(f"平仓订单失败，方向: {opposite_direction}, 数量: {pos_size}")

        except Exception as e:
            print(f"清理相反方向仓位失败: {e}")
            logging.error(f"清理相反方向仓位失败: {e}")


                
    async def handle_open_position(self, account_id: int, symbol: str, 
                                direction: str, side: str, percent: float):
        """处理开仓"""
        print(f"⚡ 开仓操作: {direction} {side}")
        exchange = await get_exchange(self, account_id)
        
        # 1. 平掉反向仓位
        await self.cleanup_opposite_positions(account_id, symbol, direction)
        
        # 2. 计算开仓量
        price = await get_market_price(exchange, symbol)
        size = await calculate_position_size(self, exchange, symbol, percent, price)
        if size <= 0:
            return
            
        # 3. 获取市场价格
        price = await get_market_price(exchange, symbol)
        client_order_id = await get_client_order_id()
        # 4. 下单并记录
        order = await open_position(
            self,
            account_id, 
            symbol, 
            side, 
            direction, 
            float(size), 
            float(price), 
            'limit',
            client_order_id
        )
        
        if order:
            await self.db.add_order({
                'account_id': account_id,
                'symbol': symbol,
                'order_id': order['id'],
                'clorder_id': client_order_id,
                'price': float(price),
                'executed_price': None,
                'quantity': float(size),
                'pos_side': direction,
                'order_type': 'limit',
                'side': side, 
                'status': 'live',
                'position_group_id': str(uuid.uuid4()),
            })

    async def handle_close_position(self, account_id: int, symbol: str, direction: str, side: str):
        """处理平仓"""
        print(f"⚡ 平仓操作: {direction} {side}")
        logging.info(f"⚡ 平仓操作: {direction} {side}")
        
        exchange = await get_exchange(self, account_id)
        if not exchange:
            print(f"❌ 获取 exchange 失败")
            logging.error(f"❌ 获取 exchange 失败")
            return

        await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单

        # 1. 从数据库计算净持仓
        # 获取持仓信息
        positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
        net_size = Decimal('0')
        for pos in positions:
            pos_side = pos['side']  # long or short
            if pos_side == direction:
                net_size = Decimal(str(pos['contracts']))
                break
        
        if net_size <= 0:
            print(f"✅ 无 {direction} 方向持仓可平")
            logging.info(f"✅ 无 {direction} 方向持仓可平")
            return
        
        # 2. 执行平仓
        client_order_id = await get_client_order_id()
        order = await open_position(
            self,
            account_id, 
            symbol, 
            side, 
            direction, 
            float(net_size), 
            None, 
            'market',
            client_order_id
        )
        
        if order:
            current_price = await get_market_price(exchange, symbol)
            # 4. 记录平仓订单
            await self.db.add_order({
                'account_id': account_id,
                'symbol': symbol,
                'order_id': order['id'],
                'clorder_id': client_order_id,
                'price': float(current_price),
                'executed_price': None,
                'quantity': float(net_size),
                'pos_side': pos_side,
                'order_type': 'market',
                'side': side,
                'status': 'filled',
                'is_clopos': 1,
                'position_group_id': str(uuid.uuid4()),
            })
            # 5. 更新数据库中原始订单为已平仓
            await self.db.mark_orders_as_closed(account_id, symbol, direction)