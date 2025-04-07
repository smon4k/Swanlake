import asyncio
from decimal import Decimal

import ccxt

from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import calculate_position_size, cleanup_opposite_positions, get_exchange, get_market_price, get_market_precision, open_position

class SignalProcessingTask:
    """交易信号处理类"""
    def __init__(self, config: TradingBotConfig, db: Database):
        self.db = db
        self.config = config

    async def record_order(self, exchange: ccxt.Exchange, account_id: int, order_id: str, price: Decimal, quantity: Decimal, symbol: str, is_clopos: int = 0):
        """记录订单到数据库"""
        order_info = exchange.fetch_order(order_id, symbol, {'instType': 'SWAP'})
        if not order_info:
            print(f"无法获取订单信息，订单ID: {order_id}")
            return
        await self.db.record_order(account_id, order_id, float(price), float(quantity), symbol, order_info, is_clopos)
        
    async def signal_processing_task(self):
        """信号处理任务"""
        # while self.running:
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT * FROM signals WHERE status='pending' LIMIT 1"
                )
                signal = cursor.fetchone()
            # print(signal)
            if signal:
                await self.process_signal(signal)
                # with conn.cursor() as cursor:
                #     cursor.execute(
                #         "UPDATE signals SET status='processed' WHERE id=%s",
                #         (signal['id'],)
                #     )
                # conn.commit()
        
            await asyncio.sleep(1)
        except Exception as e:
            print(f"信号处理异常: {e}")
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
                
            # 3. 记录信号处理结果
            # await self.log_signal_execution(account_id, signal, operation)

        except Exception as e:
            print(f"‼️ 信号处理失败: {str(e)}")
            # await self.log_error(account_id, signal, str(e))

    # ---------- 核心子方法 ----------
    def parse_operation(self, action: str, size: int) -> dict:
        """解析信号类型"""
        if action == 'buy':
            if size == 1:   # 买入开多 建立或增加多头仓位（Long）
                return {'type': 'open', 'side': 'buy', 'direction': 'long'}
            elif size == 0: # 买入平空 平掉空头仓位（Short）
                return {'type': 'close', 'side': 'buy', 'direction': 'short'}
        else:  # sell
            if size == -1:  # 卖出开空 建立或增加空头仓位（Short）
                return {'type': 'open', 'side': 'sell', 'direction': 'short'}
            elif size == 0: # 卖出平多 平掉多头仓位（Long）
                return {'type': 'close', 'side': 'sell', 'direction': 'long'}
        raise ValueError(f"无效信号组合: action={action}, size={size}")
                
    async def handle_open_position(self, account_id: int, symbol: str, direction: str, side: str, percent: float):
        """处理开仓"""
        print(f"⚡ 开仓操作: {direction} {side}")
        
        exchange = await get_exchange(self, account_id)
        if not exchange:
            print(f"⚠️ 账户 {account_id} 未连接交易所")
            return

        # 1. 清理反向仓位
        # await cleanup_opposite_positions(self, exchange, account_id, symbol, direction)
        
        # 2. 计算开仓量
        open_size = await calculate_position_size(self, exchange, symbol, percent)
        if open_size <= 0:
            return
        
        params = {
            'posSide': direction,
            'tdMode': 'cross'
        }
        # 3. 获取市场价格
        price = await get_market_price(exchange, symbol)

        # 4. 下单并记录
        order = exchange.create_order(
            symbol,
            'limit',
            side,
            float(open_size),
            float(price),
            params
        )
        # print("order", order)
        if order:
            await self.db.add_order({
                'account_id': account_id,
                'symbol': symbol,
                'order_id': order['id'],
                'price': float(price),
                'executed_price': None,
                'quantity': float(open_size),
                'pos_side': direction,
                'order_type': 'limit',
                'side': side,
                'status': 'live',
            })
            # await self.record_order(
            #     exchange,
            #     account_id,
            #     order['id'],
            #     price,
            #     size,
            #     symbol
            # )

    async def handle_close_position(self, account_id: int, symbol: str, direction: str, side: str):
        """处理平仓"""
        print(f"⚡ 平仓操作: {direction} {side}")
        
        # 1. 从数据库获取平仓数据
        order_completed = await self.db.get_completed_order(account_id, symbol, direction)
        close_size = order_completed['quantity']
        if order_completed['quantity'] <= 0:
            print(f"✅ 无{direction}方向持仓可平")
            return
        
        # 2. 执行平仓
        close_order = await open_position(
            self,
            account_id,
            symbol,
            side,
            direction,
            float(close_size),
            None,  # 市价单
            'market'
        )
        if not close_order:
            print("平仓订单创建失败")
            return False
        
        await self.db.update_order_by_id(account_id, order_completed['id'], {'is_clopos': 1})

         # 记录平仓订单
        exchange = await get_exchange(self, account_id)
        market_price = await get_market_price(exchange, symbol)
        await self.db.add_order({
            'account_id': account_id,
            'symbol': symbol,
            'order_id': close_order['id'],
            'price': float(market_price),
            'executed_price': None,
            'quantity': float(close_size),
            'pos_side': direction,
            'order_type': 'market',
            'side': side,
            'status': 'filled',
            'is_clopos': 1,
        })