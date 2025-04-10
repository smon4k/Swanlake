
import asyncio
from decimal import Decimal
import logging
import uuid
from common_functions import get_market_precision, cancel_all_orders, get_client_order_id, get_exchange, get_latest_filled_price_from_position_history, get_market_price, open_position, milliseconds_to_local_datetime
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
                logging.error(f"价格监控异常: {e}")
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
                # print("没有获取到持仓订单")
                # logging.warning("没有获取到持仓订单")
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
                logging.info(f"订单已成交，成交方向: {latest_order['side']}, 成交时间: {latest_order['info']['fillTime']}, 成交价格: {latest_order['info']['fillPx']}")
                # print(f"订单存在: {latest_order}")
                if latest_order['info']['state'] == 'filled':
                    # 检查止盈止损
                    await self.manage_grid_orders(latest_order, account_id) #检查网格

        except Exception as e:
            print(f"检查持仓失败: {e}")
            logging.error(f"检查持仓失败: {e}")

    async def manage_grid_orders(self, order: dict, account_id: int):
        """基于订单成交价进行撤单和网格管理，计算挂单数量"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                print("未找到交易所实例")
                logging.info("未找到交易所实例")
                return
                
            symbol = order['info']['instId']

            await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单

            # 2. 平掉相反方向仓位
            # await cleanup_opposite_positions(self, exchange, account_id, symbol, order['side'])

            
            # order_id = order['info']['ordId']
            filled_price = Decimal(order['info']['fillPx'])
            # filled_price = Decimal(str(order['info']['fillPx']))  # 订单成交价
            print(f"最新订单成交价: {filled_price}")
            logging.info(f"最新订单成交价: {filled_price}")
            # return
            # 3. 计算新挂单价格（基于订单成交价±0.2%）
            buy_price = filled_price * (Decimal('1') - Decimal(str(self.config.grid_step)))
            sell_price = filled_price * (Decimal('1') + Decimal(str(self.config.grid_step)))
            # print(f"计算挂单价: 卖{sell_price} 买{buy_price}")
            # return

            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            if not positions:
                print("网格下单 无持仓信息")
                logging.info("网格下单 无持仓信息")
                return
            # print("positions", positions)
            total_position_value = Decimal('0')
            for pos in positions:
                pos_amt = Decimal(str(pos.get('contracts') or 0))
                if pos_amt == 0:
                    continue
                value = abs(pos_amt)
                total_position_value += value
            price = await get_market_price(exchange, symbol)

            signal = await self.db.get_latest_signal(account_id)  # 获取最新信号
            side = 'buy' if signal['direction'] == 'long' else 'sell'

            market_precision = await get_market_precision(exchange, symbol, 'SWAP') # 获取市场精度

            print("总持仓数量", total_position_value)
            total_position_quantity = Decimal(total_position_value) * Decimal(market_precision['amount']) * price / self.config.multiple # 计算总持仓价值
            print("总持仓价值", total_position_quantity)
            if side == 'buy' and (total_position_quantity >= self.config.max_position): # 总持仓价值大于等于最大持仓
                print("下单量超过最大持仓，不执行挂单")
                logging.info("下单量超过最大持仓，不执行挂单")
                return
            
            if side == 'sell' and (total_position_quantity <= 0.01): # 总持仓价值小于等于0.01
                print("下单量小于0.01，不执行挂单")
                logging.info("下单量小于0.01，不执行挂单")
                return
            # 4. 使用calculate_position_size计算挂单数量
            # buy_size = await calculate_position_size(self, exchange, symbol,self.config.grid_buy_percent, buy_price)   # 例如0.04表示4%
            # sell_size = await calculate_position_size(self, exchange, symbol, self.config.grid_sell_percent, sell_price)  # 例如0.05表示5%

            
            buy_size = total_position_value * Decimal(str(self.config.grid_buy_percent))
            buy_size = buy_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')

            sell_size = total_position_value * Decimal(str(self.config.grid_sell_percent))
            sell_size = sell_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')

            print(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            logging.info(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            
            # 5. 创建新挂单（确保数量有效）
            group_id = str(uuid.uuid4())
            signal = await self.db.get_latest_signal(account_id)  # 获取最新信号
            if signal:
                pos_side = 'long'
                if side == 'buy' and signal['size'] == 1: # 开多
                    pos_side = 'long'
                if side == 'sell' and signal['size'] == -1: # 开空
                    pos_side = 'short'

                print("开空开多", pos_side)

                if buy_size and float(buy_size) > 0:
                    client_order_id = await get_client_order_id()
                    buy_order = await open_position(
                        self,
                        account_id, 
                        symbol, 
                        'buy', 
                        pos_side, 
                        float(buy_size), 
                        float(buy_price), 
                        'limit',
                        client_order_id,
                        True
                    )
                    await self.db.add_order({
                        'account_id': account_id,
                        'symbol': symbol,
                        'order_id': buy_order['id'],
                        'clorder_id': client_order_id,
                        'price': float(buy_price),
                        'executed_price': None,
                        'quantity': float(buy_size),
                        'pos_side': pos_side,
                        'order_type': 'limit',
                        'side': 'buy',
                        'status': 'live',
                        'position_group_id': group_id,
                    })
                    print(f"已挂买单: 价格{buy_price} 数量{buy_size}")
                    logging.info(f"已挂买单: 价格{buy_price} 数量{buy_size}")

                if sell_size and float(sell_size) > 0:
                    client_order_id = await get_client_order_id()
                    sell_order = await open_position(
                        self,
                        account_id, 
                        symbol, 
                        'sell', 
                        pos_side, 
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
                        'pos_side': pos_side,
                        'order_type': 'limit',
                        'side': 'sell',
                        'status': 'live',
                        'position_group_id': group_id,
                    })
                    print(f"已挂卖单: 价格{sell_price} 数量{sell_size}")
                    logging.info(f"已挂卖单: 价格{sell_price} 数量{sell_size}")
            else:
                print("未获取到信号")
                logging.info("未获取到信号")
        except Exception as e:
            print(f"网格订单管理失败: {str(e)}")
            logging.error(f"网格订单管理失败: {str(e)}")
            import traceback
            traceback.print_exc()

    #生成一个获取订单信息的测试方法
    async def get_order_info(self, account_id: int, order_id: str):
        """获取订单信息"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return None
        
        try:
            order_info = exchange.fetch_order(order_id, None, None, {'instType': 'SWAP'})
            print(f"订单信息: {order_info}")
            logging.info(f"订单信息: {order_info}")
            return order_info
        except Exception as e:
            print(f"获取订单信息失败: {e}")
            logging.error(f"获取订单信息失败: {e}")

    
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

            # print(f"浮动变化: {abs(price_change):.4%}, 仓位方向: {pos_side}, 当前价格: {current_price}, 开仓价格: {entry_price}, 合约数: {contracts}")
            # logging.info(f"浮动变化: {abs(price_change):.4%}, 仓位方向: {pos_side}, 当前价格: {current_price}, 开仓价格: {entry_price}, 合约数: {contracts}")
            stop_profit_loss = Decimal(self.config.stop_profit_loss)  # 确保 stop_profit_loss 是 Decimal 类型
            # 判断止盈/止损
            # print(f"止盈止损: {stop_profit_loss:.4%}, 浮动变化: {abs(price_change)}")
            if abs(price_change) <= -stop_profit_loss:  # ±0.7%
                print(f"{pos_side.upper()} 触发止损：浮动变化 {price_change:.4%}, 当前价格 {current_price}, 开仓价格 {entry_price}, 合约数 {contracts}")
                logging.info(f"{pos_side.upper()} 触发止损：浮动变化 {price_change:.4%}, 当前价格 {current_price}, 开仓价格 {entry_price}, 合约数 {contracts}")
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
                    client_order_id,
                    True,
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
                    'position_group_id': str(uuid.uuid4()),
                })

                await self.db.update_order_by_symbol(account_id, symbol, {'is_clopos': 1}) # 更新所有平仓订单

                await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单

    
        
    
   