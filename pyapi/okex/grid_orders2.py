import asyncio
import time
import pymysql
import ccxt
from decimal import Decimal, getcontext
from typing import Dict, List, Optional, Tuple

# 设置Decimal精度
getcontext().prec = 8

class TradingBotConfig:
    """交易机器人配置类"""
    def __init__(self):
        # 仓位配置
        self.position_percent = Decimal('0.8')  # 开仓比例(80%)
        self.max_position = Decimal('100')      # 最大仓位张数
        
        # 止损止盈配置
        self.stop_profit_loss = Decimal('0.007')  # 0.7%
        
        # 网格交易配置
        self.grid_step = Decimal('0.002')       # 0.2%
        self.grid_sell_percent = Decimal('0.05')  # 5%
        self.grid_buy_percent = Decimal('0.04')   # 4%
        
        # 数据库配置
        self.db_config = {
            "host": "localhost",
            "user": "root",
            "password": "123456",
            "database": "trading_bot",
            "cursorclass": pymysql.cursors.DictCursor
        }
        
        # 交易配置
        self.check_interval = 3  # 价格检查间隔(秒)

class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        self.config = config
        self.exchanges: Dict[int, ccxt.Exchange] = {}  # 改为用account_id作为key
        self.active_orders: Dict[str, List[dict]] = {}
        self.positions: Dict[str, dict] = {}
        self.running = True
        self.account_cache: Dict[int, dict] = {}  # 账户信息缓存

    def get_db_connection(self):
        """获取数据库连接"""
        return pymysql.connect(**self.config.db_config)
    
    async def get_account_info(self, account_id: int) -> Optional[dict]:
        """从数据库获取账户信息（带缓存）"""
        if account_id in self.account_cache:
            return self.account_cache[account_id]
        
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT id, exchange, api_key, api_secret, api_passphrase 
                    FROM accounts WHERE id=%s
                """, (account_id,))
                account = cursor.fetchone()
                if account:
                    self.account_cache[account_id] = account
                return account
        except Exception as e:
            print(f"获取账户信息失败: {e}")
            return None
        finally:
            if conn:
                conn.close()
    
    def get_exchange(self, account_id: int) -> Optional[ccxt.Exchange]:
        """获取交易所实例（通过account_id）"""
        # if account_id in self.exchanges:
        #     return self.exchanges[account_id]
        
        account_info = self.account_cache.get(account_id)
        if not account_info:
            return None
        
        try:
            # print(account_info)
            exchange_class = getattr(ccxt, account_info['exchange'])
            self.exchanges[account_id] = exchange_class({
                "apiKey": account_info['api_key'],
                "secret": account_info['api_secret'],
                "password": account_info['api_passphrase'],
                "options": {"defaultType": "swap"},
                # "enableRateLimit": True
            })
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
    
    async def calculate_position_size(self, account_id: int, symbol: str) -> Decimal:
        """计算仓位大小"""
        exchange = self.get_exchange(account_id)
        # print("exchange",exchange)
        if not exchange:
            return Decimal('0')
        
        try:
            trading_pair = symbol.replace("/", ",")
            balance = exchange.fetch_balance({"ccy": trading_pair})
            # balance = await exchange.fetch_balance()
            total_equity = Decimal(str(balance["info"]['data'][0]['totalEq']))
            price = await self.get_market_price(exchange, symbol)
            position_size = (total_equity * self.config.position_percent) / (price * Decimal('0.01'))
            return min(position_size, self.config.max_position)
        except Exception as e:
            print(f"计算仓位失败: {e}")
            return Decimal('0')
    
    async def record_order(self, account_id: int, order_info: dict):
        """记录订单到数据库"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO orders 
                    (account_id, symbol, order_id, side, price, quantity, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (
                    account_id,
                    order_info['symbol'],
                    order_info['id'],
                    order_info['side'],
                    order_info.get('price'),
                    order_info['amount'],
                    order_info['status']
                ))
            conn.commit()
        except Exception as e:
            print(f"订单记录失败: {e}")
        finally:
            if conn:
                conn.close()
    
    async def save_position(self, account_id: int, symbol: str, position_data: dict):
        """保存持仓到数据库"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO positions 
                    (account_id, symbol, position_size, entry_price)
                    VALUES (%s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    position_size = VALUES(position_size),
                    entry_price = VALUES(entry_price)
                """, (
                    account_id,
                    symbol,
                    float(position_data['size']),
                    float(position_data['entry_price'])
                ))
            conn.commit()
        except Exception as e:
            print(f"保存持仓失败: {e}")
        finally:
            if conn:
                conn.close()
    
    async def remove_position(self, account_id: int, symbol: str):
        """从数据库删除持仓"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    "DELETE FROM positions WHERE account_id=%s AND symbol=%s",
                    (account_id, symbol)
                )
            conn.commit()
        except Exception as e:
            print(f"删除持仓失败: {e}")
        finally:
            if conn:
                conn.close()
    
    async def sync_positions_from_db(self):
        """从数据库同步持仓状态"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT p.*, a.exchange 
                    FROM positions p
                    JOIN accounts a ON p.account_id = a.id
                """)
                positions = cursor.fetchall()
                
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
        except Exception as e:
            print(f"同步持仓失败: {e}")
        finally:
            if conn:
                conn.close()
    
    async def cleanup_opposite_positions(self, account_id: int, symbol: str, direction: str):
        """清理相反方向仓位和挂单"""
        exchange = self.get_exchange(account_id)
        if not exchange:
            return
        
        pos_side = 'long' if direction == 'buy' else 'short'
        
        try:
            # 取消所有挂单
            open_orders = await exchange.fetch_open_orders(symbol)
            for order in open_orders:
                try:
                    await exchange.cancel_order(order['id'], symbol)
                    await asyncio.sleep(0.1)
                except Exception as e:
                    print(f"取消订单失败: {e}")
            
            # 平掉相反方向仓位
            positions = await exchange.fetch_positions([symbol])
            for pos in positions:
                if pos['posSide'] != pos_side and Decimal(str(pos['contracts'])) > 0:
                    close_side = 'sell' if pos['posSide'] == 'long' else 'buy'
                    await exchange.create_order(
                        symbol=symbol,
                        type='market',
                        side=close_side,
                        amount=float(Decimal(str(pos['contracts']))),
                        params={
                            'posSide': pos['posSide'],
                            'reduceOnly': True
                        }
                    )
                    await asyncio.sleep(0.2)
        except Exception as e:
            print(f"清理仓位失败: {e}")
    
    async def open_position(self, account_id: int, symbol: str, direction: str, amount: Decimal, price: Decimal):
        """开仓下单"""
        exchange = self.get_exchange(account_id)
        if not exchange:
            return None
        
        params = {
            'posSide': 'buy' if direction == 'long' else 'sell',
            'tdMode': 'cross'
        }
        try:
            print("create_order", symbol, direction, amount)
            order = exchange.create_order(
                symbol=symbol,
                type='limit',
                side='buy' if direction == 'long' else 'sell',
                amount=float(amount),
                price=price
            )
            return order
        except Exception as e:
            print(f"开仓失败: {e}")
            raise
    
    async def close_position(self, account_id: int, symbol: str, pos_side: str, 
                           amount: Decimal, order_type: str = 'market',
                           trigger_price: Decimal = None):
        """平仓下单"""
        exchange = self.get_exchange(account_id)
        if not exchange:
            return None
        
        params = {
            'posSide': pos_side,
            'tdMode': 'cross',
            'reduceOnly': True
        }
        
        try:
            side = 'sell' if pos_side == 'long' else 'buy'
            
            if order_type == 'market':
                order = await exchange.create_order(
                    symbol=symbol,
                    type='market',
                    side=side,
                    amount=float(amount),
                    params=params
                )
            elif order_type == 'limit':
                order = await exchange.create_order(
                    symbol=symbol,
                    type='limit',
                    side=side,
                    amount=float(amount),
                    price=float(trigger_price),
                    params=params
                )
            elif order_type == 'stop':
                order = await exchange.create_order(
                    symbol=symbol,
                    type='market',
                    side=side,
                    amount=float(amount),
                    params={
                        **params,
                        'stopLoss': {
                            'triggerPrice': float(trigger_price),
                            'slOrdPx': '-1'
                        }
                    }
                )
            
            return order
        except Exception as e:
            print(f"平仓失败: {e}")
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
        account = await self.get_account_info(account_id)
        if not account:
            return
        
        symbol = signal['symbol']
        direction = signal['direction']
        
        print(f"处理信号: 账户{account_id} {direction} {symbol}")

        # 1. 清理相反仓位
        await self.cleanup_opposite_positions(account_id, symbol, direction)
        
        # 2. 计算仓位
        position_size = await self.calculate_position_size(account_id, symbol)
        # print("position_size", position_size)
        if position_size <= Decimal('0'):
            return
        
        # 3. 开仓
        exchange = self.get_exchange(account_id)
        market_price = await self.get_market_price(exchange, symbol)
        order = await self.open_position(account_id, symbol, direction, position_size, market_price)
        
        # 4. 记录订单和持仓
        await self.record_order(account_id, order)
        
        position_data = {
            'account_id': account_id,
            'exchange': account['exchange'],
            'direction': direction,
            'entry_price': market_price,
            'size': position_size,
            'last_check_price': market_price,
            'sold_size': Decimal('0'),
            'bought_size': Decimal('0')
        }
        await self.save_position(account_id, symbol, position_data)
        self.positions[symbol] = position_data
    
    async def check_stop_conditions(self, symbol: str):
        """检查止损止盈"""
        position = self.positions.get(symbol)
        if not position:
            return
        
        account_id = position['account_id']
        exchange = self.get_exchange(account_id)
        if not exchange:
            return
        
        current_price = await self.get_market_price(exchange, symbol)
        entry_price = position['entry_price']
        direction = position['direction']
        
        if direction == 'long':
            price_diff = current_price - entry_price
            stop_condition = price_diff <= -entry_price * self.config.stop_profit_loss
            take_profit = price_diff >= entry_price * self.config.stop_profit_loss
        else:
            price_diff = entry_price - current_price
            stop_condition = price_diff <= -entry_price * self.config.stop_profit_loss
            take_profit = price_diff >= entry_price * self.config.stop_profit_loss
        
        if stop_condition or take_profit:
            print(f"触发{'止盈' if take_profit else '止损'}: {symbol}")
            await self.close_position(
                account_id=account_id,
                symbol=symbol,
                pos_side='long' if direction == 'buy' else 'short',
                amount=position['size'],
                order_type='stop' if stop_condition else 'limit',
                trigger_price=entry_price * (
                    1 - self.config.stop_profit_loss if direction == 'buy' 
                    else 1 + self.config.stop_profit_loss
                )
            )
            await self.remove_position(account_id, symbol)
            del self.positions[symbol]
    
    async def manage_grid_orders(self, symbol: str):
        """管理网格订单"""
        position = self.positions.get(symbol)
        if not position:
            return
        
        account_id = position['account_id']
        exchange = self.get_exchange(account_id)
        if not exchange:
            return
        
        current_price = await self.get_market_price(exchange, symbol)
        last_price = position['last_check_price']
        price_change = (current_price - last_price) / last_price
        
        if abs(price_change) >= self.config.grid_step:
            direction = position['direction']
            size = position['size']
            
            if direction == 'long':
                if price_change > 0:  # 上涨卖出
                    sell_size = size * self.config.grid_sell_percent
                    if sell_size > Decimal('0'):
                        order = await self.create_grid_order(
                            account_id, symbol, 'sell', sell_size, current_price
                        )
                        if order:
                            position['size'] -= sell_size
                            position['sold_size'] += sell_size
                else:  # 下跌买入
                    if position['sold_size'] > Decimal('0'):
                        buy_size = position['sold_size'] * self.config.grid_buy_percent
                        if buy_size > Decimal('0'):
                            order = await self.create_grid_order(
                                account_id, symbol, 'buy', buy_size, current_price
                            )
                            if order:
                                position['sold_size'] -= buy_size
                                position['size'] += buy_size
            else:  # 做空
                if price_change < 0:  # 下跌买入
                    buy_size = size * self.config.grid_sell_percent
                    if buy_size > Decimal('0'):
                        order = await self.create_grid_order(
                            account_id, symbol, 'buy', buy_size, current_price
                        )
                        if order:
                            position['size'] -= buy_size
                            position['bought_size'] += buy_size
                else:  # 上涨卖出
                    if position['bought_size'] > Decimal('0'):
                        sell_size = position['bought_size'] * self.config.grid_buy_percent
                        if sell_size > Decimal('0'):
                            order = await self.create_grid_order(
                                account_id, symbol, 'sell', sell_size, current_price
                            )
                            if order:
                                position['bought_size'] -= sell_size
                                position['size'] += sell_size
            
            position['last_check_price'] = current_price
            await self.save_position(account_id, symbol, position)
    
    async def signal_processing_task(self):
        """信号处理任务"""
        while self.running:
            try:
                conn = self.get_db_connection()
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
                symbols = list(self.positions.keys())
                for symbol in symbols:
                    await self.check_stop_conditions(symbol)
                    await self.manage_grid_orders(symbol)
                await asyncio.sleep(self.config.check_interval)
            except Exception as e:
                print(f"价格监控异常: {e}")
                await asyncio.sleep(5)
    
    async def initialize_accounts(self):
        """初始化所有活跃账户"""
        conn = None
        try:
            conn = self.get_db_connection()
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
    
    async def run(self):
        """运行主程序"""
        # 初始化账户和交易所
        await self.initialize_accounts()
        
        # 同步持仓
        await self.sync_positions_from_db()
        print(f"初始化完成，恢复{len(self.positions)}个持仓")
        
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