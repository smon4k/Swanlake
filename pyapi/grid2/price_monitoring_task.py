
import asyncio
from decimal import Decimal
import uuid
from common_functions import get_client_order_id, get_exchange, get_latest_filled_price_from_position_history, get_market_price, open_position, calculate_position_size, milliseconds_to_local_datetime
from database import Database
from trading_bot_config import TradingBotConfig

class PriceMonitoringTask:
    def __init__(self, config: TradingBotConfig, db: Database):
        self.config = config
        self.db = db
    
    async def price_monitoring_task(self):
        """价格监控任务"""
        while getattr(self, 'running', True): 
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
            # 获取订单未成交的订单
            open_orders = await self.db.get_active_orders(account_id) # 获取未撤销的和未平仓的订单
            # print("open_orders", open_orders)
            # return
            if not open_orders:
                print("没有获取到持仓订单")
                return
            latest_order = None
            latest_fill_time = 0

            # 检查止盈止损并平仓
            signal = await self.db.get_latest_signal(account_id)  # 获取最新信号
            await self.check_and_close_position(exchange, account_id, signal['symbol'], signal['price'])
        
            for order in open_orders:
                # 检查订单是否存在
                order_info = exchange.fetch_order(order['order_id'], order['symbol'], {'instType': 'SWAP'})
                # print("order_info", order_info)
                fill_date_time = None
                fill_time = order_info['info'].get('fillTime')
                executed_price = None
                # print(order_info['info']['state'])
                if order_info['info']['state'] == 'filled':
                    # print(f"成交价格: {executed_price}")
                    fill_date_time = await milliseconds_to_local_datetime(fill_time)
                    fill_time = float(fill_time)
                    if fill_time > latest_fill_time:
                        latest_fill_time = int(fill_time)
                        latest_order = order_info
                    executed_price = order_info['info'].get('fillPx') # 成交价格

                await self.db.update_order_by_id(account_id, order_info['id'], {'executed_price': executed_price, 'status': order_info['info']['state'], 'fill_time': fill_date_time})
                
            if latest_order:
                print(f"订单已成交，成交方向: {latest_order['side']}, 成交时间: {latest_order['info']['fillTime']}, 成交价格: {latest_order['info']['fillPx']}")
                # print(f"订单存在: {latest_order}")
                if latest_order['info']['state'] == 'filled':
                    # 检查止盈止损
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

            await self.cancel_all_orders(account_id, symbol) # 取消所有未成交的订单

            # 2. 平掉相反方向仓位
            # await cleanup_opposite_positions(self, exchange, account_id, symbol, order['side'])

            
            # order_id = order['info']['ordId']
            filled_price = await get_latest_filled_price_from_position_history(exchange, symbol)
            # filled_price = Decimal(str(order['info']['fillPx']))  # 订单成交价
            print(f"最新订单成交价: {filled_price}")
            
            # 3. 计算新挂单价格（基于订单成交价±0.2%）
            sell_price = filled_price * (Decimal('1') + Decimal(str(self.config.grid_step)))
            buy_price = filled_price * (Decimal('1') - Decimal(str(self.config.grid_step)))
            # print(f"计算挂单价: 卖{sell_price} 买{buy_price}")
            # return
            # 4. 使用calculate_position_size计算挂单数量
            sell_size = await calculate_position_size(self, exchange, symbol, self.config.grid_sell_percent, sell_price)  # 例如0.05表示5%
            buy_size = await calculate_position_size(self, exchange, symbol,self.config.grid_buy_percent, buy_price)   # 例如0.04表示4%
            print(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            # return
            # 5. 创建新挂单（确保数量有效）
            group_id = str(uuid.uuid4())
            if sell_size and float(sell_size) > 0:
                client_order_id = await get_client_order_id()
                sell_order = await open_position(
                    self,
                    account_id, 
                    symbol, 
                    'sell', 
                    'short', 
                    float(sell_size), 
                    float(sell_price), 
                    'limit',
                    client_order_id
                )
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': sell_order['id'],
                    'clorder_id': client_order_id,
                    'price': float(sell_price),
                    'executed_price': None,
                    'quantity': float(sell_size),
                    'pos_side': 'short',
                    'order_type': 'limit',
                    'side': 'sell',
                    'status': 'live',
                    'position_group_id': group_id,
                })
                print(f"已挂卖单: 价格{sell_price} 数量{sell_size}")

            if buy_size and float(buy_size) > 0:
                client_order_id = await get_client_order_id()
                buy_order = await open_position(
                    self,
                    account_id, 
                    symbol, 
                    'buy', 
                    'short', 
                    float(buy_size), 
                    float(buy_price), 
                    'limit',
                    client_order_id
                )
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': buy_order['id'],
                    'clorder_id': client_order_id,
                    'price': float(buy_price),
                    'executed_price': None,
                    'quantity': float(buy_size),
                    'pos_side': 'short',
                    'order_type': 'limit',
                    'side': 'buy',
                    'status': 'live',
                    'position_group_id': group_id,
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

    #生成一个获取订单信息的测试方法
    async def get_order_info(self, account_id: int, order_id: str):
        """获取订单信息"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return None
        
        try:
            order_info = exchange.fetch_order(order_id, None, None, {'instType': 'SWAP'})
            print(f"订单信息: {order_info}")
            return order_info
        except Exception as e:
            print(f"获取订单信息失败: {e}")

    
    async def check_and_close_position(self, exchange, account_id, symbol, price: float = None):
        """检查止盈止损 并关闭持仓"""
        positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
        # print(f"当前持仓: {positions}")
        for pos in positions:
            contracts = Decimal(str(pos['contracts']))
            if contracts <= 0:
                continue  # 没仓位就跳过
            pos_side = pos['side']  # 'long' 或 'short'
            if not price:
                entry_price = Decimal(str(price))
            else:
                entry_price = Decimal(str(pos['entryPrice']))

            # 计算浮动盈亏比例
            current_price = await get_market_price(exchange, symbol)
            if pos_side == 'long':
                price_change = Decimal((current_price - entry_price) / entry_price)
            else:
                price_change = Decimal((entry_price - current_price) / entry_price)

            print(f"浮动变化: {abs(price_change):.4%}, 仓位方向: {pos_side}, 当前价格: {current_price}, 开仓价格: {entry_price}, 合约数: {contracts}")
            stop_profit_loss = Decimal(self.config.stop_profit_loss)  # 确保 stop_profit_loss 是 Decimal 类型
            # 判断止盈/止损
            # print(f"止盈止损: {stop_profit_loss:.4%}, 浮动变化: {abs(price_change)}")
            if abs(price_change) <= -stop_profit_loss:  # ±0.7%
                print(f"{pos_side.upper()} 触发止损：浮动变化 {price_change:.4%}, 当前价格 {current_price}, 开仓价格 {entry_price}, 合约数 {contracts}")
                close_side = 'sell' if pos_side == 'long' else 'buy'

                # 平仓
                client_order_id = await get_client_order_id()
                close_order = await open_position(
                    self,
                    account_id,
                    symbol,
                    close_side,
                    pos_side,
                    float(pos['contracts']),
                    None,  # 市价单
                    'market',
                    client_order_id
                )

                # ✅ 更新数据库状态
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': close_order['id'],
                    'clorder_id': client_order_id,
                    'price': float(current_price),
                    'executed_price': None,
                    'quantity': float(pos['contracts']),
                    'pos_side': pos_side,
                    'order_type': 'market',
                    'side': close_side,
                    'status': 'filled',
                    'is_clopos': 1,
                })

                await self.db.update_order_by_symbol(account_id, symbol, {'is_clopos': 1}) # 更新所有平仓订单

                await self.cancel_all_orders(account_id, symbol) # 取消所有未成交的订单

    
        
    
   