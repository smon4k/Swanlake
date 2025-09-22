
import asyncio
from decimal import Decimal
import json
import logging
import uuid
from common_functions import get_account_balance, get_grid_percent_list, get_market_precision, cancel_all_orders, get_client_order_id, get_exchange, get_total_positions, get_market_price, get_max_position_value, open_position, milliseconds_to_local_datetime
from database import Database
from trading_bot_config import TradingBotConfig
from stop_loss_task import StopLossTask
from savings_task import SavingsTask
import traceback

class PriceMonitoringTask:
    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock, stop_loss_task: StopLossTask):
        self.config = config
        self.db = db
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task  # 保存引用
    
    async def price_monitoring_task(self):
        """价格监控任务"""
        while getattr(self, 'running', True): 
            try:
                if self.signal_lock.locked():
                    print("⏸ 信号处理中，跳过一次监控")
                    logging.info("⏸ 信号处理中，跳过一次监控")
                    await asyncio.sleep(1)
                    continue
                # positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
                for account_id in self.db.account_cache:
                    # print(f"检查账户持仓: {account_id}")
                    # logging.info(f"检查账户持仓: {account_id}")
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
            
            account_symbols = self.db.account_config_cache[account_id].get('max_position_list', [])
            account_symbols_arr = json.loads(account_symbols)
            if not account_symbols:
                print(f"账户未配置监控币种: {account_id}")
                logging.info(f"账户未配置监控币种: {account_id}")
                return
            # print(f"监控币种: {account_symbols}")
            has_position = False
            for symbol in account_symbols_arr:
                symbol_tactics = symbol['symbol'] + '-SWAP' # 获取对应的币种
                tactics = symbol['tactics'] # 获取对应的策略
                signal = await self.db.get_latest_signal(symbol_tactics, tactics)  # 获取最新已成交的信号
                if not signal:
                    # print(f"未找到最新的信号: {account_id} {symbol_tactics}")
                    # logging.info(f"未找到最新的信号: {account_id} {symbol_tactics}")
                    continue

            # 获取订单未成交的订单
            open_orders = await self.db.get_active_orders(account_id) # 获取未撤销的和未平仓的订单
            # print("open_orders", open_orders)
            # return
            if not open_orders:
                # print("没有获取到数据库未成交订单")
                # logging.warning("没有获取到数据库未成交订单")
                return
            latest_order = None
            latest_fill_time = 0

            for order in open_orders:
                symbol = order['symbol']
                # if symbol.endswith('-SWAP'):
                #     symbol_tactics = symbol.replace('-SWAP', '')
                order_info = exchange.fetch_order(order['order_id'], symbol, {'instType': 'SWAP'})
                # print(order_info)
                positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
                pos_contracts = positions[0]['contracts'] if positions else 0

                # 处理无持仓情况
                if not positions:
                    print(f"🔍 无持仓信息，取消订单: {account_id} {order['order_id']} {symbol} {order['side']}")
                    logging.info(f"🔍 无持仓信息，取消订单: {account_id} {order['order_id']} {symbol} {order['side']}")
                    await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
                    await cancel_all_orders(self, account_id, symbol)
                    continue

                if order_info['info']['state'] == 'canceled': # 订单已撤销
                    print(f"订单已撤销: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                    logging.info(f"订单已撤销: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                    await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
                    if not positions or pos_contracts <= 0: # 无持仓信息
                        await cancel_all_orders(self, account_id, symbol) # 取消当前账户下指定币种 所有未成交的订单
                    continue
                elif order_info['info']['state'] == 'filled' or order_info['info']['state'] == 'partially_filled': # 订单已成交或者部分成交
                    if order_info['info']['state'] == 'partially_filled': # 订单部分成交
                        print(f"订单部分成交: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                        logging.info(f"订单部分成交: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                        total_amount = Decimal(order_info['amount'])
                        filled_amount = Decimal(order_info['filled'])
                        if filled_amount < total_amount * Decimal('0.7'): # 部分成交数量小于70%，未成交
                            print(f"订单部分成交数量小于70%，订单: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                            logging.info(f"订单部分成交数量小于70%，订单: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                            continue
                    # tactics = await self.db.get_tactics_by_account_and_symbol(account_id, symbol_tactics) # 获取账户币种策略配置名称
                    # signal = await self.db.get_latest_signal(order['symbol'], tactics)  # 获取最新信号
                    # await self.check_and_close_position(exchange, account_id, order['symbol'], signal['price'])
                    # 检查订单是否存在
                    # print(f"检查订单: {account_id} {order['order_id']} {order['symbol']} {order['side']} {order['status']}")
                    # logging.info(f"检查订单: {account_id} {order['order_id']} {order['symbol']} {order['side']} {order['status']}")
                    # print("order_info", order_info)
                    # print(order_info['info']['state'])
                    fill_date_time = None
                    fill_time = order_info['info'].get('fillTime')
                    executed_price = None

                    print(f"订单已成交: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                    # logging.info(f"订单已成交: {account_id} {order['order_id']} {order['symbol']} {order['side']} {order['status']}")
                    fill_date_time = await milliseconds_to_local_datetime(fill_time) # 格式化成交时间
                    fill_time = float(fill_time)
                    if fill_time > latest_fill_time: # 找到最新的成交时间
                        latest_fill_time = int(fill_time) 
                        latest_order = order_info

                        print(f"订单已成交，用户：{account_id}, 成交币种：{latest_order['symbol']}, 成交方向: {latest_order['side']}, 成交时间: {latest_order['info']['fillTime']}, 成交价格: {latest_order['info']['fillPx']}")
                        logging.info(f"订单已成交，用户：{account_id}, 成交币种：{latest_order['symbol']}, 成交方向: {latest_order['side']}, 成交时间: {latest_order['info']['fillTime']}, 成交价格: {latest_order['info']['fillPx']}")
                        # print(f"订单存在: {latest_order}")
                        # 网格管理 下单
                        executed_price = latest_order['info']['fillPx'] # 成交价格
                        mangr_orders = await self.manage_grid_orders(latest_order, account_id) #检查网格
                        if mangr_orders:
                            await self.db.update_order_by_id(account_id, order_info['id'], {'executed_price': executed_price, 'status': order_info['info']['state'], 'fill_time': fill_date_time})
                            await self.update_order_status(order_info, account_id, executed_price, fill_date_time, symbol) # 更新订单状态以及进行配对订单
                            await self.stop_loss_task.accounts_stop_loss_task(account_id) # 重置上次检查时间，立即检查止盈止损
                # else:
                #     print(f"订单状态未知: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                #     logging.info(f"订单状态未知: {account_id} {order['order_id']} {symbol} {order['side']} {order['status']}")
                #     await self.db.update_order_by_id(account_id, order_info['id'], {'status': order_info['info']['state']})
                #     if not positions or pos_contracts <= 0: # 无持仓信息
                #         await cancel_all_orders(self, account_id, symbol) # 取消当前账户下指定币种 所有未成交的订单
        except Exception as e:
            print(f"检查持仓失败: {e}")
            logging.error(f"检查持仓失败: {e}")
    
    #更新订单状态以及进行配对订单、计算利润
    async def update_order_status(self, order: dict, account_id: int, executed_price: float = None, fill_date_time: str = None, symbol: str = None):
        """更新订单状态以及进行配对订单、计算利润"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            print("开始匹配订单") 
            logging.info("开始匹配订单")
            side = 'sell' if order['side'] == 'buy' else 'buy'
            get_order_by_price_diff = await self.db.get_order_by_price_diff_v2(account_id, order['info']['instId'], executed_price, side)
            logging.info(f"获取配对订单: {get_order_by_price_diff['order_id'] if get_order_by_price_diff else '无配对订单'}")
            logging.info(f"side: {order['side']}, executed_price: {executed_price}, symbol: {symbol}, instId: {order['info']['instId']}")
            # print("get_order_by_price_diff", get_order_by_price_diff)
            profit = 0
            group_id = ""
            # new_price = await get_market_price(exchange, order['info']['instId'])
            # print(f"最新价格: {new_price}")
            if get_order_by_price_diff:
                market_precision = await get_market_precision(exchange, symbol) # 获取市场精度
                # if order['side'] == 'sell' and (Decimal(executed_price) >= Decimal(get_order_by_price_diff['executed_price'])):
                if order['side'] == 'sell':
                # if order['side'] == 'buy':
                    # 计算利润
                    group_id = str(uuid.uuid4())
                    profit = (Decimal(str(executed_price)) - Decimal(str(get_order_by_price_diff['executed_price']))) * Decimal(str(min(float(order['amount']), float(get_order_by_price_diff['quantity'])))) * Decimal(str(market_precision['contract_size'])) * (Decimal('1') - Decimal('0.00002'))
                    print(f"配对订单成交，利润 buy: {profit}")
                    logging.info(f"配对订单成交，利润 buy: {profit}")
                # if order['side'] == 'buy' and (Decimal(executed_price) <= Decimal(get_order_by_price_diff['executed_price'])):
                if order['side'] == 'buy':
                # if order['side'] == 'sell':
                    # 计算利润
                    group_id = str(uuid.uuid4())
                    profit = (Decimal(str(get_order_by_price_diff['executed_price'])) - Decimal(str(executed_price))) * Decimal(str(min(float(order['amount']), float(get_order_by_price_diff['quantity'])))) * Decimal(str(market_precision['contract_size'])) * (Decimal('1') - Decimal('0.00002'))
                    print(f"配对订单成交，利润 sell: {profit}")
                    logging.info(f"配对订单成交，利润 sell: {profit}")
                if profit != 0:
                    await self.db.update_order_by_id(account_id, get_order_by_price_diff['order_id'], {
                        'profit': profit, 
                        'position_group_id': group_id
                    })
                await self.db.update_order_by_id(account_id, order['id'], {
                    'executed_price': executed_price, 
                    'status': order['info']['state'], 
                    'fill_time': fill_date_time, 
                    'profit': profit, 
                    'position_group_id': group_id
                })
            # else:
            #     await self.db.update_order_by_id(account_id, order['id'], {
            #         'executed_price': executed_price, 
            #         'status': order['info']['state'], 
            #         'fill_time': fill_date_time, 
            #     })
        except Exception as e:
            print(f"配对计算利润失败: {str(e)}")
            logging.error(f"配对计算利润失败: {str(e)}")

    async def manage_grid_orders(self, order: dict, account_id: int):
        """基于订单成交价进行撤单和网格管理，计算挂单数量"""
        try:
            exchange = await get_exchange(self, account_id)
            if not exchange:
                print("未找到交易所实例")
                logging.info("未找到交易所实例")
                return False
                
            symbol = order['info']['instId']

            # 2. 平掉相反方向仓位
            # await cleanup_opposite_positions(self, exchange, account_id, symbol, order['side'])

            
            # order_id = order['info']['ordId']
            filled_price = Decimal(order['info']['fillPx'])
            # filled_price = Decimal(str(order['info']['fillPx']))  # 订单成交价
            print(f"最新订单成交价: {filled_price}")
            logging.info(f"最新订单成交价: {filled_price}")
            
            price = await get_market_price(exchange, symbol) # 获取最新市场价格
            logging.info(f"最新市场价格: {price}")

            # 3. 计算新挂单价格（基于订单成交价±0.2%）
            grid_step = Decimal(str(self.db.account_config_cache[account_id].get('grid_step')))
            price_diff_ratio = abs(filled_price - price) / price
            # 如果价格差超过 0.3%，使用最新价格作为成交价
            if price_diff_ratio > grid_step:
                filled_price = price
                print(f"价格差超过 {grid_step * 100}%，使用最新价格作为成交价: {filled_price}")
                logging.info(f"价格差超过 {grid_step * 100}%，使用最新价格作为成交价: {filled_price}")
                
            buy_price = filled_price * (Decimal('1') - grid_step)
            sell_price = filled_price * (Decimal('1') + grid_step)
            # print(f"计算挂单价: 卖{sell_price} 买{buy_price}")
            # return

            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            if not positions:
                print("网格下单 无持仓信息")
                logging.info("网格下单 无持仓信息")
                return True
            # print("positions", positions)
            total_position_value = await get_total_positions(self, account_id, symbol, 'SWAP') # 获取总持仓价值
            print("总持仓数", total_position_value)
            logging.info(f"总持仓数: {total_position_value}")
            if total_position_value <= 0:
                print("网格下单 无持仓信息")
                logging.info("网格下单 无持仓信息")
                return True

            balance = await get_account_balance(exchange, symbol)
            print(f"账户余额: {balance}")
            logging.info(f"账户余额: {balance}")

            
            # Remove '-SWAP' from the symbol if it exists
            symbol_tactics = symbol
            if symbol.endswith('-SWAP'):
                symbol_tactics = symbol.replace('-SWAP', '')

            tactics = await self.db.get_tactics_by_account_and_symbol(account_id, symbol_tactics) # 获取账户币种策略配置名称
            if not tactics:
                print(f"未找到策略配置: {account_id} {symbol_tactics}")
                logging.info(f"未找到策略配置: {account_id} {symbol_tactics}")
                return False
            signal = await self.db.get_latest_signal(symbol, tactics)  # 获取最新信号
            side = 'buy' if signal['direction'] == 'long' else 'sell'

            market_precision = await get_market_precision(exchange, symbol) # 获取市场精度

            total_position_quantity = Decimal(total_position_value) * Decimal(market_precision['amount']) * price # 计算总持仓价值
            print("总持仓价值", total_position_quantity)
            logging.info(f"总持仓价值: {total_position_quantity}")
            
            await cancel_all_orders(self, account_id, symbol) # 取消当前账户下指定币种 所有未成交的订单

            # 4. 使用calculate_position_size计算挂单数量
            # buy_size = await calculate_position_size(self, exchange, symbol,self.config.grid_buy_percent, buy_price)   # 例如0.04表示4%
            # sell_size = await calculate_position_size(self, exchange, symbol, self.config.grid_sell_percent, sell_price)  # 例如0.05表示5%
            percent_list = await get_grid_percent_list(self, account_id, signal['direction'])
            buy_percent = percent_list.get('buy')
            # print('buy_percent', buy_percent)
            # print("market_precision", market_precision)
            buy_size = (total_position_value * Decimal(str(buy_percent)))
            # print('buy_size', buy_size)
            buy_size = buy_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')
            if buy_size < market_precision['min_amount']:
                print(f"买单数量小于最小下单量: {buy_size} < {market_precision['min_amount']}")
                logging.info(f"买单数量小于最小下单量: {buy_size} < {market_precision['min_amount']}")
                return False
            
            buy_size_total_quantity = Decimal(buy_size) * Decimal(market_precision['amount']) * buy_price

            # sell_percent = self.config.grid_percent_config[signal['direction']]['sell']
            sell_percent = percent_list.get('sell')
            # print('sell_percent', sell_percent)
            sell_size = total_position_value * Decimal(str(sell_percent))
            sell_size = sell_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')
            if sell_size < market_precision['min_amount']:
                print(f"卖单数量小于最小下单量: {sell_size} < {market_precision['min_amount']}")
                logging.info(f"卖单数量小于最小下单量: {sell_size} < {market_precision['min_amount']}")
                return False
            
            sell_size_total_quantity = Decimal(sell_size) * Decimal(market_precision['amount']) * sell_price

            print(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            logging.info(f"计算挂单量: 卖{sell_size} 买{buy_size}")
            
            # 5. 判断所有仓位是否超过最大持仓量
            # cancel_size = 'all'
            max_position = await get_max_position_value(self, account_id, symbol) # 获取配置文件对应币种最大持仓
            buy_total_size_position_quantity = Decimal(total_position_quantity) + Decimal(buy_size_total_quantity) - Decimal(sell_size_total_quantity)
            print("开仓以及总持仓挂买价值", buy_total_size_position_quantity)
            logging.info(f"开仓以及总持仓挂买价值：{buy_total_size_position_quantity} {max_position}")
            is_buy = True
            if buy_total_size_position_quantity >= max_position: # 总持仓价值大于等于最大持仓
                # cancel_size = 'buy' # 取消未成交的订单只取消买单
                is_buy = False # 不执行挂单
                print("下单量超过最大持仓，不执行挂单")
                logging.info("下单量超过最大持仓，不执行挂单")
                return False
            
            sell_total_size_position_quantity = Decimal(total_position_quantity) - Decimal(sell_size_total_quantity)
            print("开仓以及总持仓挂卖价值", sell_total_size_position_quantity)
            logging.info(f"开仓以及总持仓挂卖价值：{sell_total_size_position_quantity}")
            
            # 5. 创建新挂单（确保数量有效）
            group_id = str(uuid.uuid4())
            # signal = await self.db.get_latest_signal(symbol)  # 获取最新信号
            if signal:
                pos_side = 'long'
                if side == 'buy' and signal['size'] == 1: # 开多
                    pos_side = 'long'
                if side == 'sell' and signal['size'] == -1: # 开空
                    pos_side = 'short'

                print("开空开多", pos_side)
                buy_order = None
                sell_order = None
                if is_buy and buy_size and float(buy_size) > 0:
                    # await cancel_all_orders(self, account_id, symbol) # 取消当前账户下指定币种 所有未成交的订单
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
                        False
                    )
                    
                if sell_size and float(sell_size) > 0:
                    # await cancel_all_orders(self, account_id, symbol) # 取消当前账户下指定币种 所有未成交的订单
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
                        client_order_id,
                        False
                    )

                if buy_order and sell_order:
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
                        'position_group_id': '',
                    })
                    print(f"已挂买单: 价格{buy_price} 数量{buy_size}")
                    logging.info(f"已挂买单: 价格{buy_price} 数量{buy_size}")
                    
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
                        'position_group_id': '',
                    })
                    print(f"已挂卖单: 价格{sell_price} 数量{sell_size}")
                    logging.info(f"已挂卖单: 价格{sell_price} 数量{sell_size}")
                    return True
                else:
                    await cancel_all_orders(self, account_id, symbol) # 取消当前账户下指定币种 所有未成交的订单
                    print("网格下单失败，未能成功挂买单或卖单")
                    logging.info("网格下单失败，未能成功挂买单或卖单")
                    return True
            else:
                print("未获取到信号")
                logging.info("未获取到信号")
                return True
        except Exception as e:
            print(f"网格订单管理失败: {str(e)}")
            logging.error(f"网格订单管理失败: {str(e)}")
            traceback.print_exc()
            return False

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
        try:
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
                    price_change = (Decimal(current_price) - entry_price) / entry_price
                else:
                    price_change = (entry_price - Decimal(current_price)) / entry_price

                # print(f"浮动变化: {abs(price_change):.4%}, 仓位方向: {pos_side}, 当前价格: {current_price}, 开仓价格: {entry_price}, 合约数: {contracts}")
                # logging.info(f"浮动变化: {abs(price_change):.4%}, 仓位方向: {pos_side}, 当前价格: {current_price}, 开仓价格: {entry_price}, 合约数: {contracts}")
                stop_profit_loss = Decimal(Decimal(str(self.db.account_config_cache[account_id].get('stop_profit_loss'))))  # 确保 stop_profit_loss 是 Decimal 类型
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
                        'position_group_id': '',
                    })

                    await self.db.update_order_by_symbol(account_id, symbol, {'is_clopos': 1}) # 更新所有平仓订单

                    await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单
                    await cancel_all_orders(self, account_id, symbol, True) # 取消所有委托订单

        except Exception as e:
            print(f"检查止盈止损失败: {e}")
            logging.error(f"检查止盈止损失败: {e}")
            traceback.print_exc()
            

    
        
    
   