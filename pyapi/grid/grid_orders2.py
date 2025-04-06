import asyncio
import time
import ccxt
from decimal import Decimal, getcontext
from typing import Dict, List, Optional, Tuple
from decimal import Decimal, ROUND_DOWN
from database import Database
from process_signal import ProcessSignal
from trading_bot_config import TradingBotConfig
from aiohttp import web
from datetime import datetime, timezone
from dotenv import load_dotenv
import os

# 设置Decimal精度
getcontext().prec = 8

class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        load_dotenv()
        self.config = config
        self.exchanges: Dict[int, ccxt.Exchange] = {}  # 改为用account_id作为key
        self.active_orders: Dict[str, List[dict]] = {}
        self.positions: Dict[str, dict] = {}
        self.running = True
        # self.account_cache: Dict[int, dict] = {}  # 账户信息缓存
        self.db = Database(self.config.db_config)
        self.process_signal = ProcessSignal(self.config.db_config)
        self.app = web.Application()
        self.app.add_routes([
            web.post('/insert_signal', self.handle_insert_signal)  # 新增路由
        ])

    async def handle_insert_signal(self, request):
        """处理写入信号的API请求"""
        try:
            data = await request.json()
            # 解析请求体中的参数
            symbol = data.get('symbol')
            account_id = os.getenv("ACCOUNT_ID", 1)
            direction = 'long' if data.get('side') == 'buy' else 'short'  # 假设请求体中的'side'对应数据库中的'direction'
            # 当前时间的格式化字符串
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

            if not symbol or not direction:
                return web.json_response({"error": "Missing required parameters"}, status=400)

            # 调用数据库方法写入信号
            result = await self.db.insert_signal({
                'account_id': account_id,  # 假设account_id是从请求头中获取的
                'symbol': symbol,
                'direction': direction,
                'status': 'pending',
                'timestamp': timestamp,
            })
            if result['status'] == 'success':
                return web.json_response(result, status=200)
            else:
                return web.json_response(result, status=500)
        except Exception as e:
            return web.json_response({"error": str(e)}, status=500)
        
    async def get_account_info(self, account_id: int) -> Optional[dict]:
        """从数据库获取账户信息（带缓存）"""
        return await self.db.get_account_info(account_id)

    def get_exchange(self, account_id: int) -> Optional[ccxt.Exchange]:
        """获取交易所实例（通过account_id）"""
        # print(self.db.account_cache)
        account_info = self.db.account_cache.get(account_id)
        if not account_info:
            return None

        try:
            exchange_class = getattr(ccxt, account_info['exchange'])
            self.exchanges[account_id] = exchange_class({
                "apiKey": account_info['api_key'],
                "secret": account_info['api_secret'],
                "password": account_info['api_passphrase'],
                "options": {"defaultType": "swap"},
                # "enableRateLimit": True
            })
            self.exchanges[account_id].set_sandbox_mode(True)  # 开启模拟交易
            return self.exchanges[account_id]
        except Exception as e:
            print(f"创建交易所实例失败: {e}")
            return None

    async def get_market_price(self, exchange: ccxt.Exchange, symbol: str) -> Decimal:
        """获取当前市场价格"""
        try:
            ticker = exchange.fetch_ticker(symbol)
            return Decimal(str(ticker["last"]))
        except Exception as e:
            print(f"获取市场价格失败: {e}")
            raise

    async def get_market_precision(self, exchange: ccxt.Exchange, symbol: str, instType: str = 'SWAP') -> Tuple[Decimal, Decimal]:
        """获取市场的价格和数量精度"""
        try:
            markets = exchange.fetch_markets_by_type(instType, {'instId': f"{symbol}"})
            price_precision = Decimal(str(markets[0]['precision']['price']))
            amount_precision = Decimal(str(markets[0]['precision']['amount']))
            return {
                'price': price_precision,
                'amount': amount_precision,
            }
        except Exception as e:
            print(f"获取市场精度失败: {e}")
            return Decimal('0.0001'), Decimal('0.0001')  # 设置默认精度值

    async def calculate_position_size(self, account_id: int, symbol: str, position_percent: Decimal) -> Decimal:
        """计算仓位大小"""
        exchange = self.get_exchange(account_id)
        print(exchange)
        if not exchange:
            return Decimal('0')

        try:
            trading_pair = symbol.replace("-", ",")
            balance = exchange.fetch_balance({"ccy": trading_pair})
            total_equity = Decimal(str(balance["USDT"]['total']))
            print(f"账户余额: {total_equity}")
            price = await self.get_market_price(exchange, symbol)
            market_precision = await self.get_market_precision(exchange, symbol, 'SWAP')
            position_size = (total_equity * position_percent) / (price * Decimal(market_precision['amount']))
            position_size = position_size.quantize(Decimal(market_precision['price']), rounding='ROUND_DOWN')
            return min(position_size, self.config.max_position)
        except Exception as e:
            print(f"计算仓位失败: {e}")
            return Decimal('0')

    async def record_order(self, exchange: ccxt.Exchange, account_id: int, order_id: str, price: Decimal, quantity: Decimal, symbol: str, is_clopos: int = 0):
        """记录订单到数据库"""
        order_info = exchange.fetch_order(order_id, symbol, {'instType': 'SWAP'})
        if not order_info:
            print(f"无法获取订单信息，订单ID: {order_id}")
            return
        await self.db.record_order(account_id, order_id, float(price), float(quantity), symbol, order_info, is_clopos)

    async def get_order_by_id(self, account_id: int, order_id: str) -> Optional[dict]:
        """从数据库获取指定订单信息"""
        return await self.db.get_order_by_id(account_id, order_id)

    async def update_order_by_id(self, account_id: int, order_id: str, updates: dict):
        """根据订单ID更新订单信息"""
        await self.db.update_order_by_id(account_id, order_id, updates)

    async def save_position(self, account_id: int, symbol: str, position_data: dict):
        """保存持仓到数据库"""
        await self.db.save_position(account_id, symbol, position_data)

    async def get_position_by_id(self, account_id: int, position_id: int) -> Optional[dict]:
        """根据持仓ID和账户ID获取持仓信息"""
        return await self.db.get_position_by_id(account_id, position_id)

    async def update_position_by_id(self, account_id: int, position_id: int, updates: dict):
        """根据持仓ID更新持仓信息"""
        await self.db.update_position_by_id(account_id, position_id, updates)

    async def remove_position(self, account_id: int, symbol: str):
        """从数据库删除持仓"""
        await self.db.remove_position(account_id, symbol)

    async def sync_positions_from_db(self):
        """从数据库同步持仓状态"""
        positions = await self.db.sync_positions_from_db()
        for pos in positions:
            symbol = pos['symbol']
            account_id = pos['account_id']
            self.positions[symbol] = {
                'account_id': account_id,
                'exchange': pos['exchange'],
                'direction': 'long' if Decimal(str(pos['position_size'])) > 0 else 'short',
                'entry_price': Decimal(str(pos['entry_price'])),
                'size': abs(Decimal(str(pos['position_size']))),
                'last_check_price': Decimal(str(pos['entry_price'])),
                'sold_size': Decimal('0'),
                'bought_size': Decimal('0')
            }
        # 其余代码保持不变
    
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
            close_order = await self.open_position(
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
            market_price = await self.get_market_price(exchange, symbol)
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
    
    #取消所有未成交的订单
    async def cancel_all_orders(self, account_id: int, symbol: str):
        """取消所有未成交的订单"""
        exchange = self.get_exchange(account_id)
        if not exchange:
            return None
        
        try:
            open_orders = exchange.fetch_open_orders(symbol, None, None, {'instType': 'SWAP'}) # 获取未成交的订单
            for order in open_orders:
                try:
                    cancel_order =  exchange.cancel_order(order['id'], symbol) # 进行撤单
                    if cancel_order['info']['sCode'] == 0:
                        existing_order = await self.get_order_by_id(account_id, order['id'])
                        if existing_order:
                            # 更新订单信息
                            await self.update_order_by_id(account_id, order['id'], {
                                'status': 'canceled'
                            })
                        print(f"取消订单成功: {cancel_order}")
                except Exception as e:
                    print(f"取消订单失败: {e}")
        except Exception as e:
            print(f"获取未成交订单失败: {e}")

    async def cleanup_opposite_positions(self, account_id: int, symbol: str, direction: str):
        """平掉相反方向仓位"""
        exchange = self.get_exchange(account_id)
        # print(exchange)
        if not exchange:
            return
                
        try:
            # 平掉相反方向仓位 long 做多 short 做空
            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            print("positions", positions)
            # closed_orders = exchange.fetch_closed_orders(symbol, {'state': 'filled'})
            # print("closed_orders", closed_orders)
            for pos in positions:
                if pos['side'] != direction and Decimal(str(pos['contracts'])) > 0: # 大于0 完全成交
                    print("orderId:", pos['id'])
                    side = 'sell' if pos['side'] == 'long' else 'buy'
                    market_price = await self.get_market_price(exchange, symbol)
                    order = await self.open_position(account_id, symbol, side, pos['side'], Decimal(str(pos['contracts'])), market_price, 'market')
                    # print(order)
                    if not order:
                        await asyncio.sleep(0.2)
                        continue
                    # 4. 记录订单和持仓
                    await self.record_order(exchange, account_id, order['id'], market_price, Decimal(str(pos['contracts'])), symbol, 1)
                    await asyncio.sleep(0.2)
        except Exception as e:
            print(f"清理仓位失败: {e}")
    
    async def open_position(self, account_id: int, symbol: str, side: str, pos_side: str, amount: float, price: float, order_type: str):
        """开仓、平仓下单"""
        exchange = self.get_exchange(account_id)
        if not exchange:
            return None
        
        params = {
            'posSide': pos_side,
            'tdMode': 'cross'
        }
        try:
            # print("create_order", symbol, direction, price, amount)
            order = exchange.create_order(
                symbol=symbol,
                type=order_type,
                side=side,
                amount=float(amount),
                price=price,
                params=params
            )
            # print("order", order)
            if order['info'].get('sCode') == '0':
                return order
            else:
                print(f"开仓失败: {order['info'].get('sMsg', '未知错误')}")
                return None
        except Exception as e:
            print(f"开仓失败: {e}")
            raise
    
    async def create_grid_order(self, account_id: int, symbol: str, 
                              side: str, size: Decimal, price: Decimal):
        """创建网格订单"""
        exchange = self.get_exchange(account_id)
        if not exchange or size <= Decimal('0'):
            return None
        
        pos_side = 'long' if side == 'buy' else 'short'
        params = {
            'posSide': pos_side,
            'tdMode': 'cross'
        }
        
        try:
            order = await exchange.create_order(
                symbol=symbol,
                type='limit',
                side=side,
                amount=float(size),
                price=float(price),
                params=params
            )
            
            if symbol not in self.active_orders:
                self.active_orders[symbol] = []
            self.active_orders[symbol].append(order)
            
            return order
        except Exception as e:
            print(f"网格订单创建失败: {e}")
            return None
    
    async def check_positions(self, account_id: int):
        """检查持仓"""
        try:
            # print("check_positions")
            exchange = self.get_exchange(account_id)
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
                    await self.update_order_by_id(account_id, order_info['id'], {'executed_price': order_info['info']['fillPx'], 'status': 'filled'})
                    await self.manage_grid_orders(order_info, account_id) #检查网格

        except Exception as e:
            print(f"检查持仓失败: {e}")

    async def check_stop_conditions(self, pos: dict, account_id: int):
        """检查止损止盈"""
        try:
            # print("pos", pos)
            exchange = self.get_exchange(account_id)
            if not exchange:
                return
            if Decimal(str(pos['contracts'])) > 0:
                # print("pos", pos)
                symbol = pos['symbol']
                current_price = await self.get_market_price(exchange, symbol)
                entry_price = Decimal(str(pos['entryPrice'])) # 开仓价
                direction = pos['side']
                if direction == 'long':  # 如果持仓方向是多头（做多）
                    price_diff = current_price - entry_price  # 计算当前价格与开仓价的差值（上涨 = 盈利， 下跌 = 亏损）
                    stop_condition = price_diff <= -entry_price * self.config.stop_profit_loss  # 亏损超过设定比例0.7时止损
                    take_profit = price_diff >= entry_price * self.config.stop_profit_loss  # 盈利达到设定比例时止盈
                else:  # 如果持仓方向是空头（做空）
                    price_diff = entry_price - current_price  # 计算开仓价与当前价格的差值（下跌 = 盈利， 上涨 = 亏损）
                    stop_condition = price_diff <= -entry_price * self.config.stop_profit_loss  # 亏损超过设定比例0.7时止损
                    take_profit = price_diff >= entry_price * self.config.stop_profit_loss  # 盈利达到设定比例时止盈

                if stop_condition or take_profit:
                    print(f"触发{'止盈' if take_profit else '止损'}: {symbol}")
                    side = 'buy' if direction == 'long' else 'sell'
                    order = await self.open_position(account_id, symbol, side, direction, Decimal(str(pos['contracts'])), current_price, 'market')
                    if not order:
                        await asyncio.sleep(0.2)
                        return
                    # 记录订单
                    await self.record_order(exchange, account_id, order['id'], current_price, Decimal(str(pos['contracts'])), symbol)

                # trades = exchange.fetch_my_trades(symbol, None, None, {'instType': 'SWAP'})
                # # print("trades", trades)
                # if trades:
                #     last_trade = trades[-1]  # 取最新的一笔成交
                #     entry_price = Decimal(str(last_trade['price'])) #上次成交价
                    #     # print("last_price", entry_price)
        except Exception as e:
            print(f"检查止损止盈失败: {e}")

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
            return
            # 5. 创建新挂单（确保数量有效）
            if sell_size and float(sell_size) > 0:
                sell_order = await self.open_position(
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
                buy_order = await self.open_position(
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
                await self.process_signal.process_signal(signal)
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
    
    async def initialize_accounts(self):
        """初始化所有活跃账户"""
        conn = None
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id FROM accounts")
                accounts = cursor.fetchall()
                for account in accounts:
                    account_id = account['id']
                    await self.get_account_info(account_id)  # 加载到缓存
                    self.get_exchange(account_id)  # 初始化交易所实例
        except Exception as e:
            print(f"初始化账户失败: {e}")
        finally:
            if conn:
                conn.close()

    async def start_api_server(self):
        """启动API服务器"""
        runner = web.AppRunner(self.app)
        await runner.setup()
        site = web.TCPSite(runner, 'localhost', 8082)  # 指定监听的地址和端口
        await site.start()
        print("API服务器已启动，监听端口8082")
    
    async def run(self):
        """运行主程序"""
        # 初始化账户和交易所
        await self.initialize_accounts()
        
        # 同步持仓
        # await self.sync_positions_from_db()
        # print(f"初始化完成，恢复{len(self.positions)}个持仓")

        # 启动API服务器
        # asyncio.create_task(self.start_api_server())

        # 启动任务
        await self.signal_processing_task()
        # await asyncio.gather(
        #     self.signal_processing_task(),
        #     self.price_monitoring_task()
        # )

if __name__ == "__main__":
    config = TradingBotConfig()
    bot = OKXTradingBot(config)
    
    try:
        asyncio.run(bot.run())
    except KeyboardInterrupt:
        bot.running = False
        print("程序安全退出")