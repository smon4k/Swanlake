
import asyncio
from decimal import Decimal
from common_functions import get_exchange, get_market_price, get_market_precision, open_position
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
            open_orders = await self.db.get_single_active_order(account_id) # 获取未撤销的和未平仓的订单
            # print("open_orders", open_orders)
            if not open_orders:
                print("没有未撤销以及平仓的订单")
                return
            # 检查订单是否存在
            order_info = exchange.fetch_order(open_orders['order_id'], open_orders['symbol'], {'instType': 'SWAP'})
            print("order_info", order_info)
            if order_info or order_info['info']:
                # print(f"订单存在: {order_info}")
                if order_info['info']['state'] == 'filled':
                    # 更新订单信息
                    await self.db.update_order_by_id(account_id, order_info['id'], {'executed_price': order_info['info']['fillPx'], 'status': 'filled'})
                    await self.manage_grid_orders(order_info, account_id) #检查网格

        except Exception as e:
            print(f"检查持仓失败: {e}")

    async def manage_grid_orders(self, order: dict, account_id: int):
        """基于订单成交价进行撤单和网格管理，使用calculate_position_size计算挂单数量"""
        try:
            exchange = self.get_exchange(account_id)
            if not exchange:
                print("未找到交易所实例")
                return
                
            # 2. 平掉已成交的单子
            await self.close_filled_order(account_id, order)
            
            symbol = order['info']['instId']
            # order_id = order['info']['ordId']
            filled_price = Decimal(str(order['info']['fillPx']))  # 订单成交价
            print(f"订单成交价: {filled_price}")

            
            # 3. 计算新挂单价格（基于订单成交价±0.2%）
            sell_price = filled_price * (Decimal('1') + Decimal(str(self.config.grid_step)))
            buy_price = filled_price * (Decimal('1') - Decimal(str(self.config.grid_step)))
            # print(f"计算挂单价: 卖{sell_price} 买{buy_price}")
            # return
            # 4. 使用calculate_position_size计算挂单数量
            sell_size = await self.calculate_position_size(
                account_id, 
                symbol, 
                self.config.grid_sell_percent  # 例如0.05表示5%
            )
            buy_size = await self.calculate_position_size(
                account_id,
                symbol,
                self.config.grid_buy_percent   # 例如0.04表示4%
            )
            print(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            # return
            # 5. 创建新挂单（确保数量有效）
            if sell_size and float(sell_size) > 0:
                sell_order = await open_position(
                    account_id, 
                    symbol, 
                    'sell', 
                    'short', 
                    float(sell_size), 
                    float(sell_price), 
                    'limit'
                )
                await self.record_order(
                    exchange, 
                    account_id, 
                    sell_order['id'], 
                    float(sell_price), 
                    float(sell_size), 
                    symbol
                )
                print(f"已挂卖单: 价格{sell_price} 数量{sell_size}")

            if buy_size and float(buy_size) > 0:
                buy_order = await open_position(
                    account_id, 
                    symbol, 
                    'buy', 
                    'long', 
                    float(buy_size), 
                    float(buy_price), 
                    'limit'
                )
                await self.record_order(
                    exchange, 
                    account_id, 
                    buy_order['id'], 
                    float(buy_price), 
                    float(buy_size), 
                    symbol
                )
                print(f"已挂买单: 价格{buy_price} 数量{buy_size}")

        except Exception as e:
            print(f"网格订单管理失败: {str(e)}")
            import traceback
            traceback.print_exc()

    async def close_filled_order(self, account_id: int, order: dict):
        """根据已成交订单执行平仓（精确匹配持仓）"""
        exchange = self.get_exchange(account_id)
        if not exchange:
            print("交易所连接失败")
            return False
        
        try:
            symbol = order['symbol']
            order_id = order['id']
            
            # 获取订单详细信息（确保是最新状态）
            order_info = exchange.fetch_order(order_id, symbol)
            if not order_info:
                print(f"无法获取订单信息: {order_id}")
                return False
                
            # 检查订单是否已成交
            if order_info['status'] != 'closed' and order_info['filled'] <= 0:
                print(f"订单未成交: {order_id} (状态: {order_info['status']})")
                return False
                
            # 获取当前所有持仓
            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            # print("当前持仓数据:", positions)
            
            # 匹配对应方向的持仓
            target_pos = None
            order_side = order_info['side']  # 原订单方向
            pos_side = order_info.get('info', {}).get('posSide')  # 从订单信息获取持仓方向
            
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
                Decimal(str(order_info['filled'])),  # 订单成交量
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
            
            # 更新订单状态
            # update_position = exchange.fetch_positions_history([symbol], None, None, {'instType': 'SWAP', 'posId': target_pos['id']})
            # print("update_position", update_position)
            # # 检查 update_position 是否为空列表
            # if not update_position:
            #     print("未找到更新后的持仓历史信息")
            #     return False
            # # 获取最新的记录
            # latest_position = update_position[-1]

            # await self.update_order_by_id(account_id, order_id, {'clopos_status': 1})

            # 记录平仓订单
            market_price = await get_market_price(exchange, symbol)
            await self.record_order(
                exchange,
                account_id,
                close_order['id'],
                market_price,
                close_size,
                symbol,
                1
            )
            
            print(f"平仓成功: 订单ID {close_order['id']}")
            return True
            
        except Exception as e:
            print(f"平仓过程中出错: {e}")
            import traceback
            traceback.print_exc()
            return False
        
    async def calculate_position_size(self, account_id: int, symbol: str, position_percent: Decimal) -> Decimal:
        """计算仓位大小"""
        exchange = await get_exchange(account_id)
        if not exchange:
            return Decimal('0')

        try:
            trading_pair = symbol.replace("-", ",")
            balance = exchange.fetch_balance({"ccy": trading_pair})
            total_equity = Decimal(str(balance["USDT"]['total']))
            print(f"账户余额: {total_equity}")
            price = await get_market_price(exchange, symbol)
            market_precision = await get_market_precision(exchange, symbol, 'SWAP')
            position_size = (total_equity * position_percent) / (price * Decimal(market_precision['amount']))
            position_size = position_size.quantize(Decimal(market_precision['price']), rounding='ROUND_DOWN')
            return min(position_size, self.config.max_position)
        except Exception as e:
            print(f"计算仓位失败: {e}")
            return Decimal('0')
        
    
   