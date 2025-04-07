
import asyncio
from decimal import Decimal
from common_functions import get_exchange, get_market_price, open_position, calculate_position_size, cleanup_opposite_positions
from database import Database
from trading_bot_config import TradingBotConfig

class PriceMonitoringTask:
    def __init__(self, config: TradingBotConfig, db: Database):
        self.config = config
        self.db = db
    
    async def price_monitoring_task(self):
        """价格监控任务"""
        # while self.running:
        try:
            # positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            for account_id in self.db.account_cache:
                await self.check_positions(account_id) # 检查持仓
            await asyncio.sleep(self.config.check_interval)
        except Exception as e:
            print(f"价格监控异常: {e}")
            await asyncio.sleep(5)
    
    async def check_positions(self, account_id: int):
        """检查持仓"""
        try:
            # print("check_positions")
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            # 获取订单未撤销的订单
            open_orders = await self.db.get_active_orders(account_id) # 获取未撤销的和未平仓的订单
            # print("open_orders", open_orders)
            # return
            if not open_orders:
                print("没有未撤销以及平仓的订单")
                return
            latest_order = None
            latest_fill_time = 0
            for order in open_orders:
                # 检查订单是否存在
                order_info = exchange.fetch_order(order['order_id'], order['symbol'], {'instType': 'SWAP'})
                # print("order_info", order_info)
                fill_time = order_info['info'].get('fillTime')
                if fill_time:
                    fill_time = int(fill_time)
                    if fill_time > latest_fill_time:
                        latest_fill_time = fill_time
                        latest_order = order_info
                if latest_order['info']['state'] == 'filled':
                    await self.db.update_order_by_id(account_id, order_info['id'], {'executed_price': order_info['info']['fillPx'], 'status': 'filled'})

            if latest_order or latest_order['info']:
                print(f"订单存在: {latest_order}")
                if latest_order['info']['state'] == 'filled':
                    # 更新订单信息
                    await self.manage_grid_orders(latest_order, account_id) #检查网格

        except Exception as e:
            print(f"检查持仓失败: {e}")

    async def manage_grid_orders(self, order: dict, account_id: int):
        """基于订单成交价进行撤单和网格管理，使用calculate_position_size计算挂单数量"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                print("未找到交易所实例")
                return
                
            symbol = order['info']['instId']

            # 2. 平掉相反方向仓位
            # await cleanup_opposite_positions(self, exchange, account_id, symbol, order['side'])

            await self.cancel_all_orders(account_id, symbol) # 取消所有未成交的订单
            
            # order_id = order['info']['ordId']
            filled_price = Decimal(str(order['info']['fillPx']))  # 订单成交价
            print(f"订单成交价: {filled_price}")

            
            # 3. 计算新挂单价格（基于订单成交价±0.2%）
            sell_price = filled_price * (Decimal('1') + Decimal(str(self.config.grid_step)))
            buy_price = filled_price * (Decimal('1') - Decimal(str(self.config.grid_step)))
            # print(f"计算挂单价: 卖{sell_price} 买{buy_price}")
            # return
            # 4. 使用calculate_position_size计算挂单数量
            sell_size = await calculate_position_size(self, exchange, symbol, self.config.grid_sell_percent)  # 例如0.05表示5%
            buy_size = await calculate_position_size(self, exchange, symbol,self.config.grid_buy_percent)   # 例如0.04表示4%
            print(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            # return
            # 5. 创建新挂单（确保数量有效）
            if sell_size and float(sell_size) > 0:
                sell_order = await open_position(
                    self,
                    account_id, 
                    symbol, 
                    'sell', 
                    'short', 
                    float(sell_size), 
                    float(sell_price), 
                    'limit'
                )
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': sell_order['id'],
                    'price': float(sell_price),
                    'executed_price': None,
                    'quantity': float(sell_size),
                    'pos_side': 'short',
                    'order_type': 'limit',
                    'side': 'sell',
                    'status': 'live',
                })
                print(f"已挂卖单: 价格{sell_price} 数量{sell_size}")

            if buy_size and float(buy_size) > 0:
                buy_order = await open_position(
                    self,
                    account_id, 
                    symbol, 
                    'buy', 
                    'long', 
                    float(buy_size), 
                    float(buy_price), 
                    'limit'
                )
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': buy_order['id'],
                    'price': float(buy_price),
                    'executed_price': None,
                    'quantity': float(buy_size),
                    'pos_side': 'long',
                    'order_type': 'limit',
                    'side': 'buy',
                    'status': 'live',
                })
                print(f"已挂买单: 价格{buy_price} 数量{buy_size}")

        except Exception as e:
            print(f"网格订单管理失败: {str(e)}")
            import traceback
            traceback.print_exc()

    async def cancel_all_orders(self, account_id: int, symbol: str):
        """取消所有未成交的订单"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return None
        
        try:
            open_orders = exchange.fetch_open_orders(symbol, None, None, {'instType': 'SWAP'}) # 获取未成交的订单
            for order in open_orders:
                try:
                    cancel_order =  exchange.cancel_order(order['id'], symbol) # 进行撤单
                    if cancel_order['info']['sCode'] == 0:
                        existing_order = await self.db.get_order_by_id(account_id, order['id'])
                        if existing_order:
                            # 更新订单信息
                            await self.db.update_order_by_id(account_id, order['id'], {
                                'status': 'canceled',
                                'is_clopos': 1,
                            })
                        print(f"取消订单成功: {cancel_order}")
                except Exception as e:
                    print(f"取消订单失败: {e}")
        except Exception as e:
            print(f"获取未成交订单失败: {e}")
    
        
    
   