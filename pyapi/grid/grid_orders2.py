import asyncio
import time
import ccxt
from decimal import Decimal, getcontext
from typing import Dict, List, Optional, Tuple
from decimal import Decimal, ROUND_DOWN
from database import Database
from trading_bot_config import TradingBotConfig
from aiohttp import web
from datetime import datetime, UTC
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
            timestamp = datetime.now(UTC).strftime('%Y-%m-%d %H:%M:%S')
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
        if not exchange:
            return Decimal('0')

        try:
            trading_pair = symbol.replace("-", ",")
            balance = exchange.fetch_balance({"ccy": trading_pair})
            total_equity = Decimal(str(balance["USDT"]['total']))
            # print(f"账户余额: {total_equity}")
            price = await self.get_market_price(exchange, symbol)
            market_precision = await self.get_market_precision(exchange, symbol, 'SWAP')
            position_size = (total_equity * position_percent) / (price * Decimal(market_precision['amount']))
            position_size = position_size.quantize(Decimal(market_precision['price']), rounding='ROUND_DOWN')
            return min(position_size, self.config.max_position)
        except Exception as e:
            print(f"计算仓位失败: {e}")
            return Decimal('0')

    async def record_order(self, exchange: ccxt.Exchange, account_id: int, order_id: str, price: Decimal, quantity: Decimal, symbol: str):
        """记录订单到数据库"""
        order_info = exchange.fetch_order(order_id, symbol, {'instType': 'SWAP'})
        if not order_info:
            print(f"无法获取订单信息，订单ID: {order_id}")
            return
        await self.db.record_order(account_id, order_id, float(price), float(quantity), symbol, order_info)

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
    
    async def cleanup_opposite_positions(self, account_id: int, symbol: str, direction: str):
        """清理相反方向仓位和挂单"""
        exchange = self.get_exchange(account_id)
        # print(exchange)
        if not exchange:
            return
                
        try:
            # 取消所有挂单
            open_orders = exchange.fetch_open_orders(symbol, None, None, {'instType': 'SWAP'}) # 获取未成交的订单
            # print("open_orders", open_orders)
            for order in open_orders:
                try:
                    cancel_order =  exchange.cancel_order(order['id'], symbol) # 进行撤单
                    if cancel_order['info']['sCode'] == 0:
                        # print(f"取消订单成功: {cancel_order}")
                        # 检查订单是否存在
                        existing_order = await self.get_order_by_id(account_id, order['id'])
                        if existing_order:
                            # 更新订单信息
                            await self.update_order_by_id(account_id, order['id'], {
                                'status': 'canceled'
                            })
                    await asyncio.sleep(0.1)
                except Exception as e:
                    print(f"取消订单失败: {e}")
        
            # 平掉相反方向仓位 long 做多 short 做空
            positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            # print("positions", positions)
            # closed_orders = exchange.fetch_closed_orders(symbol, {'state': 'filled'})
            # print("closed_orders", closed_orders)
            for pos in positions:
                if pos['side'] != direction and Decimal(str(pos['contracts'])) > 0: # 大于0 完全成交
                    # print("orderId:", pos['id'])
                    side = 'sell' if pos['side'] == 'long' else 'buy'
                    market_price = await self.get_market_price(exchange, symbol)
                    order = await self.open_position(account_id, symbol, side, pos['side'], Decimal(str(pos['contracts'])), market_price, 'market')
                    # print(order)
                    if not order:
                        await asyncio.sleep(0.2)
                        continue
                    # 4. 记录订单和持仓
                    await self.record_order(exchange, account_id, order['id'], market_price, Decimal(str(pos['contracts'])), symbol)
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
    
    async def process_signal(self, signal: dict):
        """处理交易信号"""
        account_id = signal['account_id']
        # account = await self.get_account_info(account_id)
        # if not account:
        #     return
        
        symbol = signal['symbol']
        direction = signal['direction']
        
        print(f"处理信号: 账户{account_id} {direction} {symbol}")

        # 1. 清理相反仓位
        await self.cleanup_opposite_positions(account_id, symbol, direction)
            
        # 2. 计算仓位
        position_size = await self.calculate_position_size(account_id, symbol, self.config.position_percent)
        # print("position_size", position_size)
        if position_size <= Decimal('0'):
            return
        
        # 3. 开仓
        exchange = self.get_exchange(account_id)
        market_price = await self.get_market_price(exchange, symbol)
        side = 'buy' if direction == 'long' else 'sell'
        order = await self.open_position(account_id, symbol, side, direction, position_size, market_price, 'limit')
        # # print(order)
        if not order:
            return
        # 4. 记录订单和持仓
        await self.record_order(exchange, account_id, order['id'], market_price, position_size, symbol)

        # 5. 记录持仓信息
        # positions = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
        # print("positions", positions)
        # for pos in positions:
        #     position = exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP', 'posId': pos['id']})
        #     print("position", position)
            # if pos['side'] == direction and Decimal(pos['contracts']) > 0:
            #     position_data = {
            #         'account_id': account_id,
            #         'pos_id': pos['id'],
            #         'symbol': symbol,
            #         'position_size': Decimal(pos['contracts']) * Decimal(pos['contractSize']),
            #         'entry_price': float(market_price),
            #         'position_status': 'open',
            #         'open_time': time.strftime('%Y-%m-%d %H:%M:%S')
            #     }
            #     await self.save_position(account_id, symbol, position_data)
            #     self.positions[symbol] = position_data
    async def check_positions(self, account_id: int):
        """检查持仓"""
        try:
            # print("check_positions")
            exchange = self.get_exchange(account_id)
            if not exchange:
                return
            positions = exchange.fetch_positions(None, {'instType': 'SWAP'})
            # print("positions", positions)
            for pos in positions:
                if Decimal(str(pos['contracts'])) > 0:
                    await self.check_stop_conditions(pos, account_id) #检查止损止盈
                    await self.manage_grid_orders(pos, account_id) #检查网格

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
                    side = 'long' if direction == 'buy' else 'short'
                    order = await self.open_position(account_id, symbol, direction, side, Decimal(str(pos['contracts'])), current_price, 'market')
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

    async def manage_grid_orders(self, position: dict, account_id: int):
        """管理网格订单"""
        try:
            exchange = self.get_exchange(account_id)
            if not exchange:
                return
            symbol = position['info']['instId']
            # print("symbol", symbol)
            current_price = await self.get_market_price(exchange, symbol)
            last_price = Decimal(str(position['entryPrice'])) # 开仓价
            price_change = (current_price - last_price) / last_price
            # print("current_price", current_price)
            # print("last_price", last_price)
            # print("price_change", abs(price_change))
            direction = position['side']  # 'long' 或 'short'
            # size = position['contracts']
            
            if abs(price_change) >= self.config.grid_step:  # 例如 0.2%
                if direction == 'long':  # 做多 long 方向
                    print("做多")
                    if price_change > 0:  # 上涨 0.2% ，卖出剩余仓位的 5%
                        # sell_size = Decimal(size) * self.config.grid_sell_percent
                        sell_size = await self.calculate_position_size(account_id, symbol, self.config.grid_sell_percent)
                        print("sell_size", sell_size)
                        if sell_size > Decimal('0'):
                            order = await self.open_position(account_id, symbol, 'sell', 'short', sell_size, current_price, 'limit')
                            await self.record_order(exchange, account_id, order['id'], current_price, sell_size, symbol)
                    elif price_change < 0:  # 下跌 0.2%，如果之前有卖出，买回 4%
                        # buyback_size = size * self.config.grid_buyback_percent
                        buyback_size = await self.calculate_position_size(account_id, symbol, self.config.grid_buy_percent)
                        print("buyback_size", buyback_size)
                        if buyback_size > Decimal('0'):
                            order = await self.open_position(account_id, symbol, 'buy', 'long', buyback_size, current_price, 'limit')
                            await self.record_order(exchange, account_id, order['id'], current_price, buyback_size, symbol)
                else:  # 做空 short 方向
                    print("做空")
                    if price_change < 0:  # 下跌 0.2% ，卖出剩余仓位的 5%
                        # sell_size = size * self.config.grid_sell_percent
                        sell_size = await self.calculate_position_size(account_id, symbol, self.config.grid_sell_percent)
                        print("sell_size", sell_size)
                        if sell_size > Decimal('0'):
                            order = await self.open_position(account_id, symbol, 'sell', 'short', sell_size, current_price, 'limit')
                            await self.record_order(exchange, account_id, order['id'], current_price, sell_size, symbol)
                    elif price_change > 0:  # 上涨 0.2%，如果之前有卖出，买回 4%
                        # buyback_size = size * self.config.grid_buyback_percent
                        buyback_size = await self.calculate_position_size(account_id, symbol, self.config.grid_buy_percent)
                        print("buyback_size", buyback_size)
                        if buyback_size > Decimal('0'):
                            order = await self.open_position(account_id, symbol, 'buy', 'long', buyback_size, current_price, 'limit')
                            await self.record_order(exchange, account_id, order['id'], current_price, buyback_size, symbol)
        except Exception as e:
            print(f"管理网格订单失败: {e}")
    
    async def signal_processing_task(self):
        """信号处理任务"""
        while self.running:
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
                    with conn.cursor() as cursor:
                        cursor.execute(
                            "UPDATE signals SET status='processed' WHERE id=%s",
                            (signal['id'],)
                        )
                    conn.commit()
            
                await asyncio.sleep(1)
            except Exception as e:
                print(f"信号处理异常: {e}")
                await asyncio.sleep(5)
            finally:
                if 'conn' in locals():
                    conn.close()
    
    async def price_monitoring_task(self):
        """价格监控任务"""
        while self.running:
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
        site = web.TCPSite(runner, 'localhost', 8088)  # 指定监听的地址和端口
        await site.start()
        print("API服务器已启动，监听端口8088")
    
    async def run(self):
        """运行主程序"""
        # 初始化账户和交易所
        await self.initialize_accounts()
        
        # 同步持仓
        # await self.sync_positions_from_db()
        # print(f"初始化完成，恢复{len(self.positions)}个持仓")

        # 启动API服务器
        asyncio.create_task(self.start_api_server())

        # 启动任务
        # await self.price_monitoring_task()
        await asyncio.gather(
            self.signal_processing_task(),
            self.price_monitoring_task()
        )

if __name__ == "__main__":
    config = TradingBotConfig()
    bot = OKXTradingBot(config)
    
    try:
        asyncio.run(bot.run())
    except KeyboardInterrupt:
        bot.running = False
        print("程序安全退出")