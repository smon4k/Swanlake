import asyncio
from datetime import datetime
from decimal import Decimal
import logging
import uuid
from database import Database
from trading_bot_config import TradingBotConfig
from common_functions import cancel_all_orders, get_account_balance, get_exchange, get_market_price, get_market_precision, get_max_position_value, get_total_positions, open_position, get_client_order_id

class SignalProcessingTask:
    """交易信号处理类"""
    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock):
        self.db = db
        self.config = config
        self.running = True
        self.signal_lock = signal_lock

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
                    async with self.signal_lock:  # 🚨加锁，避免 price_monitoring 同时执行
                        print("🔁 处理信号中...")
                        logging.info("🔁 处理信号中...")
                        if signal['name'] in self.db.tactics_accounts_cache:
                            account_tactics_list = self.db.tactics_accounts_cache[signal['name']]
                            for account_id in account_tactics_list:
                                await self.process_signal(signal, account_id)
                        else:
                            print("🚫 无对应账户策略信号")
                            logging.info("🚫 无对应账户策略信号")
                        with conn.cursor() as cursor:
                            cursor.execute(
                                "UPDATE g_signals SET status='processed' WHERE id=%s",
                                (signal['id'],)
                            )
                        conn.commit()
                await asyncio.sleep(self.config.check_interval)
            except Exception as e:
                print(f"信号处理异常: {e}")
                logging.error(f"信号处理异常: {e}")
                await asyncio.sleep(5)
            finally:
                if 'conn' in locals():
                    conn.close()

    async def process_signal(self, signal: dict, account_id: int):
        """处理交易信号（完整版）"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return
        # account_id = signal['account_id']
        sign_id = signal['id']
        symbol = signal['symbol']
        name = signal['name']
        pos_side = signal['direction'] # 'long' 或 'short'
        side =  'buy' if pos_side == 'long' else 'sell'  # 'buy' 或 'sell'
        size = signal['size']      # 1, 0, -1
        price = signal['price']    # 0.00001
        
        print(f"📡 账户 {account_id} 处理信号:  {name} {symbol} {side} {size}")
        logging.info(f"📡 账户 {account_id} 处理信号: {name} {symbol} {side} {size}")

        try:
            # 1. 解析操作类型
            # operation = self.parse_operation(side, size)
            
            # 1. 解析操作类型 执行对应操作
            # buy 1: 买入开多 buy long  结束：sell 0
            # buy 0: 买入平空 结束做多 buy short
            # sell -1: 卖出开空 sell short 结束： buy 0
            # sell 0: 卖出平多 结束做空 sell long
            if (side == 'buy' and size == 1) or (side == 'sell' and size == -1): # 开仓
                strategy_info = await self.db.get_strategy_info(name)
                # 1.1 开仓前先平掉反向仓位
                await self.cleanup_opposite_positions(account_id, symbol, pos_side)

                # 1.2 取消所有未成交的订单
                await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单

                # 1.3 开仓
                await self.handle_open_position(
                    account_id,
                    symbol,
                    pos_side,
                    side,
                    price
                )

                #1.4 处理记录开仓方向数据
                # has_open_position = await self.db.has_open_position(name, side)
                # if has_open_position:
                await self.db.update_signals_trade_by_id(sign_id, {
                    'pair_id': sign_id,
                    'position_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                    'count_profit_loss': strategy_info['count_profit_loss'],
                    'stage_profit_loss': strategy_info['stage_profit_loss'],
                })
                    
            elif (side == 'buy' and size == 0) or (side == 'sell' and size == 0): # 平仓
                # 1.4 平仓
                # await self.handle_close_position(
                #     account_id,
                #     symbol,
                #     pos_side,
                #     side
                # )

                # 1.5 取消所有未成交的订单
                await cancel_all_orders(self, account_id, symbol) # 取消所有未成交的订单

                # 1.6 平掉反向仓位
                await self.cleanup_opposite_positions(account_id, symbol, pos_side)

                # 1.7 更新数据库订单为已平仓
                direction = 'long' if side == 'sell' else 'short' # 开仓方向
                has_open_position = await self.db.get_latest_signal_by_name_and_direction(name, direction)
                if has_open_position:
                    # 计算盈亏
                    loss_profit = 0
                    # 获取价格并确保Decimal转换
                    open_price = Decimal(str(has_open_position['price'])) # 开仓价
                    close_price = Decimal(str(price)) # 平仓价

                    open_side = 'buy' if side == 'sell' else 'sell' # 开仓方向
                    if open_side == 'buy':
                        loss_profit = close_price - open_price  # 多单：平仓价 - 开仓价 > 0 盈利
                    else:
                        loss_profit = open_price - close_price  # 空单：开仓价 - 平仓价 > 0 盈利
                    # 方法1：强制转为常规小数（推荐）
                    loss_profit_normal = format(loss_profit, 'f')  # 输出 "0.00003333"

                    # 明确盈亏状态 盈利为1，亏损为0
                    is_profit = float(loss_profit_normal) > 0 

                    print(f"✅ 平仓成功: {name} {symbol} {side} {size} at {price}, Profit: {loss_profit_normal}, Is Profit: {is_profit}")
                    logging.info(f"✅ 平仓成功: {name} {symbol} {side} {size} at {price}, Profit: {loss_profit_normal}, Is Profit: {is_profit}")
                    is_save_strategy = await self.pre_update_strategy_check(account_id, symbol, is_profit, name, open_price, loss_profit_normal) # 校验是否更新策略
                    if is_save_strategy:
                        await self.db.update_max_position_by_tactics(name, is_profit) # 更新策略数据

                    strategy_info = await self.db.get_strategy_info(name)
                    await self.db.update_signals_trade_by_id(sign_id, {
                        'pair_id': has_open_position['pair_id'],
                        'position_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                        'loss_profit':  loss_profit_normal,
                        'count_profit_loss': strategy_info['count_profit_loss'],
                        'stage_profit_loss': strategy_info['stage_profit_loss'],
                    })
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
                    'position_group_id': '',
                })

                await self.db.mark_orders_as_closed(account_id, symbol, opposite_direction)
                print(f"成功平掉{opposite_direction}方向总持仓：{total_size}")
                logging.info(f"成功平掉{opposite_direction}方向总持仓：{total_size}")

            else:
                print(f"平仓失败，方向: {opposite_direction}，数量: {total_size}")
                logging.info(f"平仓失败，方向: {opposite_direction}，数量: {total_size}")

        except Exception as e:
            print(f"清理反向持仓出错: {e}")
            logging.error(f"清理反向持仓出错: {e}")



                
    async def handle_open_position(self, account_id: int, symbol: str, pos_side: str, side: str, price: Decimal):
        try:
            """处理开仓"""
            print(f"⚡ 开仓操作: {pos_side} {side} {price} {symbol}")
            logging.info(f"⚡ 开仓操作: {pos_side} {side} {price} {symbol}")
            exchange = await get_exchange(self, account_id)
            
            # 1. 平掉反向仓位
            # await self.cleanup_opposite_positions(account_id, symbol, pos_side)
            total_position_value = await get_total_positions(self, account_id, symbol, 'SWAP') # 获取总持仓价值
            print("总持仓数", total_position_value)
            logging.info(f"总持仓数：{total_position_value}")
            if total_position_value is None:
                print(f"总持仓数获取失败")
                logging.error(f"总持仓数获取失败")
                return
            market_precision = await get_market_precision(exchange, symbol) # 获取市场精度
            # print("市场精度", market_precision)
            # return
            total_position_quantity = 0
            if(total_position_value > 0):
                total_position_quantity = Decimal(total_position_value) * Decimal(market_precision['amount']) * price # 计算总持仓价值
                print("总持仓价值", total_position_quantity)
                logging.info(f"总持仓价值：{total_position_quantity}")
            
            # 2. 计算开仓量
            # price = await get_market_price(exchange, symbol)
            commission_price_difference = Decimal(self.db.account_config_cache[account_id].get('commission_price_difference'))
            price_float = price * (commission_price_difference / 100) # 计算价格浮动比例
            # print("价格浮动比例", price_float, commission_price_difference)
            if(pos_side == 'short'): # 做空
                price = price - price_float # 信号价 - 价格浮动比例
            elif(pos_side =='long'): # 做多
                price = price + price_float # 信号价 + 价格浮动比例

            balance = await get_account_balance(exchange, symbol)
            print(f"账户余额: {balance}")
            logging.info(f"账户余额: {balance}")
            if balance is None:
                print(f"账户余额获取失败")
                logging.error(f"账户余额获取失败")
                return
        
            max_position = await get_max_position_value(self, account_id, symbol) # 获取配置文件对应币种最大持仓
            position_percent = Decimal(self.db.account_config_cache[account_id].get('position_percent'))
            # max_balance = max_position * position_percent #  最大仓位数 * 开仓比例
            # if balance >= max_balance: # 超过最大仓位限制
            #     balance = max_position
            # print(f"最大开仓数量: {max_balance}")
            logging.info(f"最大开仓数量: {max_position}")
            size = await self.calculate_position_size(market_precision, max_position, position_percent, price, account_id)
            # print(f"开仓价: {price}")
            logging.info(f"开仓价: {price}")
            # print(f"开仓量: {size}")
            logging.info(f"开仓量: {size}")
            size_total_quantity = Decimal(size) * Decimal(market_precision['amount']) * price
            # print(f"开仓价值: {size_total_quantity}")
            logging.info(f"开仓价值: {size_total_quantity}")
            if size <= 0:
                # print(f"开仓量为0，不执行开仓")
                logging.info(f"开仓量为0，不执行开仓")
                return
            
            # 3. 判断当前币种是否超过最大持仓
            # if size_total_quantity >= max_position:
            #     print(f"开仓量超过最大仓位限制，不执行开仓")
            #     logging.info(f"开仓量超过最大仓位限制，不执行开仓")
            #     return
            
            # 4. 判断所有仓位是否超过最大持仓量
            total_size_position_quantity = 0
            if total_position_quantity > 0:
                total_size_position_quantity = Decimal(total_position_quantity) + Decimal(size_total_quantity)

            # print("开仓以及总持仓价值", total_size_position_quantity)
            logging.info(f"开仓以及总持仓价值：{total_size_position_quantity}")
            if total_size_position_quantity >= max_position: # 总持仓价值大于等于最大持仓
                logging.info(f"最大持仓数：{max_position}")
                # print(f"最大持仓数：{max_position}")
                logging.info(f"总持仓数大于等于最大持仓，不执行挂单")
                # print(f"总持仓数大于等于最大持仓，不执行挂单")
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
                    'position_group_id': '',
                })
        except Exception as e:
            print(f"开仓异常: {e}")
            logging.error(f"开仓异常: {e}")

    async def calculate_position_size(self, market_precision: object, balance: Decimal, position_percent: Decimal, price: float, account_id: int) -> Decimal:
        """计算仓位大小"""
        try:
            # market_precision = await get_market_precision(exchange, symbol, 'SWAP')
            # print("market_precision", market_precision, price)

            position_size = (balance * position_percent) / (price * Decimal(market_precision['contract_size']))
            position_size = position_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')

            # total_position = Decimal(self.db.account_config_cache[account_id].get('total_position', 0)) # 获取配置文件对应币种最大持仓
            # return min(position_size, total_position)
            return position_size
        except Exception as e:
            print(f"计算仓位失败: {e}")
            return Decimal('0')
    
    async def pre_update_strategy_check(self, account_id: int, symbol: str, increase: bool, tactics_name: str, open_price: float, loss_profit_normal: str) -> bool:


        """
        更新策略前的处理逻辑判断
        返回True表示可以继续更新，False表示不满足条件
        """
        try:
            # 1.0 获取配置文件
            config = await self.db.get_config_by_account_and_symbol(account_id, symbol)
            max_loss_number = float(config.get('max_loss_number')) if config.get('max_loss_number') else 5 # 最大亏损次数
            min_loss_ratio = float(config.get('min_loss_ratio')) if config.get('min_loss_ratio') else 0.001 # 最小亏损比例

            # 2.0 获取策略表连续几次亏损 
            strategy_info = await self.db.get_strategy_info(tactics_name)
            #计算总盈亏
            count_profit_loss = strategy_info.get('count_profit_loss', 0) # 总盈亏
            stage_profit_loss = strategy_info.get('stage_profit_loss', 0) # 阶段性盈亏

            if float(loss_profit_normal) > 0: # 盈利
                stage_profit_loss = 0 # 阶段性盈亏清0
                profit_loss = float(count_profit_loss) + float(loss_profit_normal)
                if profit_loss > 0:
                    count_profit_loss = profit_loss
                else:
                    count_profit_loss = float(loss_profit_normal)
            else:
                stage_profit_loss = float(stage_profit_loss) + float(loss_profit_normal)
                profit_loss = float(count_profit_loss) + float(loss_profit_normal)
                count_profit_loss = profit_loss
            
            # if count_profit_loss <= 0:
            #     count_profit_loss = 0

            if increase:
                await self.db.update_strategy_loss_number(tactics_name, 0, count_profit_loss, stage_profit_loss) # 如果盈利，修改亏损数量为0
            else:
                
                # 连续亏损次数
                loss_number = strategy_info.get('loss_number', 0)
                add_loss_number = loss_number + 1
                # print("add_loss_number", add_loss_number, "count_profit_loss", count_profit_loss, "stage_profit_loss", stage_profit_loss)
                await self.db.update_strategy_loss_number(tactics_name, add_loss_number, count_profit_loss, stage_profit_loss) # 如果亏损，修改亏损数量+1

                #2.1 如果C/开仓价的绝对值小于0.1%，不增不减（可配置）。
                loss_ratio = abs(float(loss_profit_normal)) / float(open_price) #亏损/开仓价的绝对值，小于0.1%就认为可以忽略 0.1可配置
                if loss_ratio < min_loss_ratio:
                    return False
                
                if(add_loss_number > max_loss_number): # 连续亏损5次，不更新最大仓位
                    return False

            return True
        except Exception as e:
            print(f"策略更新前检查异常: {e}")
            logging.error(f"策略更新前检查异常: {e}")
            return False
