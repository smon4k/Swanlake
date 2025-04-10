import asyncio
from decimal import Decimal
import logging
import uuid

import ccxt

from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import cancel_all_orders, get_account_balance, get_exchange, get_market_price, get_market_precision, open_position, get_client_order_id

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
        pos_side = signal['direction'] # 'long' 或 'short'
        side =  'buy' if pos_side == 'long' else 'sell'  # 'buy' 或 'sell'
        size = signal['size']      # 1, 0, -1
        price = signal['price']    # 0.00001
        
        print(f"📡 接收信号: {account_id} {symbol} {side} {size}")
        logging.info(f"📡 接收信号: {account_id} {symbol} {side} {size}")

        try:
            # 1. 解析操作类型
            # operation = self.parse_operation(side, size)
            
            # 1. 解析操作类型 执行对应操作
            # buy 1: 买入开多 buy long  结束：sell 0
            # buy 0: 买入平空 结束做多 buy short
            # sell -1: 卖出开空 sell short 结束： buy 0
            # sell 0: 卖出平多 结束做空 sell long
            if (side == 'buy' and size == 1) or (side == 'sell' and size == -1): # 开仓
                # 1.1 开仓前先平掉反向仓位
                await self.cleanup_opposite_positions(account_id, symbol, pos_side)

                # 1.2 开仓
                await self.handle_open_position(
                    account_id,
                    symbol,
                    pos_side,
                    side,
                    self.config.position_percent,
                    price
                )
            elif (side == 'buy' and size == 0) or (side == 'sell' and size == 0): # 平仓
                # 1.3 平仓
                # await self.handle_close_position(
                #     account_id,
                #     symbol,
                #     pos_side,
                #     side
                # )

                # 1.3 取消所有未成交的订单
                await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单

                # 1.4 平掉反向仓位
                await self.cleanup_opposite_positions(account_id, symbol, pos_side)
            else:
                print(f"❌ 无效信号: {side}{size}")
                logging.error(f"❌ 无效信号: {side}{size}")

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
        """平掉一个方向的仓位（双向持仓），并更新数据库订单为已平仓"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return

        try:
            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            if not positions:
                print("无持仓信息")
                logging.warning("无持仓信息")
                return

            opposite_direction = 'short' if direction == 'long' else 'long'
            total_size = Decimal('0')

            for pos in positions:
                pos_side = pos.get('side') or pos.get('posSide') or ''
                pos_size = Decimal(str(pos.get('contracts') or pos.get('positionAmt') or 0))
                if pos_size == 0 or pos_side.lower() != opposite_direction:
                    continue
                total_size += abs(pos_size)

            if total_size == 0:
                print(f"无反向持仓需要平仓：{opposite_direction}")
                logging.info(f"无反向持仓需要平仓：{opposite_direction}")
                return

            close_side = 'sell' if opposite_direction == 'long' else 'buy'
            market_price = await get_market_price(exchange, symbol)
            client_order_id = await get_client_order_id()

            close_order = await open_position(
                self,
                account_id,
                symbol,
                close_side,
                opposite_direction,
                float(total_size),
                None,
                'market',
                client_order_id,
                True  # reduceOnly=True
            )

            if close_order:
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': close_order['id'],
                    'clorder_id': client_order_id,
                    'price': float(market_price),
                    'executed_price': None,
                    'quantity': float(total_size),
                    'pos_side': opposite_direction,
                    'order_type': 'market',
                    'side': close_side,
                    'status': 'filled',
                    'is_clopos': 1,
                    'position_group_id': str(uuid.uuid4()),
                })

                await self.db.mark_orders_as_closed(account_id, symbol, opposite_direction)
                logging.info(f"成功平掉{opposite_direction}方向总持仓：{total_size}")

            else:
                logging.info(f"平仓失败，方向: {opposite_direction}，数量: {total_size}")

        except Exception as e:
            logging.error(f"清理反向持仓出错: {e}")



                
    async def handle_open_position(self, account_id: int, symbol: str, pos_side: str, side: str, percent: float, price: Decimal):
        """处理开仓"""
        print(f"⚡ 开仓操作: {pos_side} {side}")
        logging.info(f"⚡ 开仓操作: {pos_side} {side}")
        exchange = await get_exchange(self, account_id)
        
        # 1. 平掉反向仓位
        # await self.cleanup_opposite_positions(account_id, symbol, pos_side)
        
        # 2. 计算开仓量
        # price = await get_market_price(exchange, symbol)
        if(pos_side == 'short'): # 做空
            price = price - self.config.commission_price_difference # 信号价 - 50U
        elif(pos_side =='long'): # 做多
            price = price + self.config.commission_price_difference # 信号价 + 50U

        balance = await get_account_balance(exchange, symbol)
        print(f"账户余额: {balance}")
        if balance is None:
            print(f"账户余额获取失败")
            logging.error(f"账户余额获取失败")
            return
        max_balance = self.config.max_position * self.config.position_percent #  最大仓位数 * 开仓比例
        if balance >= max_balance: # 超过最大仓位限制
            balance = self.config.max_position
        print(f"成交余额: {balance}")
        size = await self.calculate_position_size(exchange, balance, symbol, percent, price)
        if size <= 0:
            print(f"开仓量为0，不执行开仓")
            logging.info(f"开仓量为0，不执行开仓")
            return
        if size >= self.config.max_position:
            print(f"开仓量超过最大仓位限制，不执行开仓")
            logging.info(f"开仓量超过最大仓位限制，不执行开仓")
            return
            
        # 3. 获取市场价格
        client_order_id = await get_client_order_id()
        # 4. 下单并记录
        order = await open_position(
            self,
            account_id, 
            symbol, 
            side, 
            pos_side, 
            float(size), 
            float(price), 
            'limit',
            client_order_id
        )
        # print("order", order)
        if order:
            await self.db.add_order({
                'account_id': account_id,
                'symbol': symbol,
                'order_id': order['id'],
                'clorder_id': client_order_id,
                'price': float(price),
                'executed_price': None,
                'quantity': float(size),
                'pos_side': pos_side,
                'order_type': 'limit',
                'side': side, 
                'status': 'live',
                'position_group_id': str(uuid.uuid4()),
            })

    async def calculate_position_size(self, exchange: ccxt.Exchange, balance: Decimal, symbol: str, position_percent: Decimal, price: float) -> Decimal:
        """计算仓位大小"""
        try:
            # balance = get_account_balance(exchange, symbol)
            # total_equity = Decimal(str(balance["USDT"]['total']))
            # print(f"账户余额: {total_equity}")
            # price = await get_market_price(exchange, symbol)
            market_precision = await get_market_precision(exchange, symbol, 'SWAP')
            # print("market_precision", market_precision)
            position_size = (balance * position_percent) / (price * Decimal(market_precision['amount']))
            position_size = position_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')
            return min(position_size, self.config.total_position)
        except Exception as e:
            print(f"计算仓位失败: {e}")
            return Decimal('0')