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
        self.exchanges: Dict[str, ccxt.Exchange] = {}
        self.active_orders: Dict[str, List[dict]] = {}  # 跟踪活跃订单
        self.positions: Dict[str, dict] = {}  # 跟踪持仓 {symbol: position_data}
        self.running = True

    def get_db_connection(self):
        """获取数据库连接"""
        return pymysql.connect(**self.config.db_config)
    
    def get_exchange(self, account_info: dict) -> ccxt.Exchange:
        """获取交易所实例"""
        exchange_name = account_info['exchange'].lower()
        if exchange_name not in self.exchanges:
            exchange_class = getattr(ccxt, exchange_name)
            self.exchanges[exchange_name] = exchange_class({
                "apiKey": account_info['api_key'],
                "secret": account_info['api_secret'],
                "password": account_info.get('passphrase'),
                "options": {"defaultType": "swap"},
                "enableRateLimit": True
            })
        return self.exchanges[exchange_name]
    
    async def get_market_price(self, exchange: ccxt.Exchange, symbol: str) -> Decimal:
        """获取当前市场价格"""
        ticker = await exchange.fetch_ticker(symbol)
        return Decimal(str(ticker["last"]))
    
    async def calculate_position_size(self, exchange: ccxt.Exchange, symbol: str) -> Decimal:
        """计算仓位大小"""
        balance = await exchange.fetch_balance()
        total_equity = Decimal(str(balance['total']['USDT']))  # 假设使用USDT作为基准
        
        # 计算合约张数 (根据交易所规格调整)
        price = await self.get_market_price(exchange, symbol)
        position_size = (total_equity * self.config.position_percent) / price
        
        # 不超过最大仓位
        return min(position_size, self.config.max_position)
    
    async def record_order(self, account_id: int, order_info: dict):
        """记录订单到数据库"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO orders 
                    (account_id, symbol, order_id, side, pos_side, price, size, ord_type, status, timestamp)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, (
                    account_id,
                    order_info['symbol'],
                    order_info['id'],
                    order_info['side'],
                    order_info.get('posSide'),
                    order_info.get('price'),
                    order_info['amount'],
                    order_info['type'],
                    order_info['status'],
                    order_info['timestamp'] if 'timestamp' in order_info else int(time.time())
                ))
            conn.commit()
        except Exception as e:
            print(f"订单记录失败: {e}")
        finally:
            if conn:
                conn.close()
    
    async def cleanup_opposite_positions(self, exchange: ccxt.Exchange, symbol: str, direction: str):
        """清理相反方向仓位和挂单"""
        pos_side = 'long' if direction == 'buy' else 'short'
        
        # 1. 取消所有挂单
        try:
            open_orders = await exchange.fetch_open_orders(symbol)
            for order in open_orders:
                try:
                    await exchange.cancel_order(order['id'], symbol)
                    await asyncio.sleep(0.1)  # 限流
                except Exception as e:
                    print(f"取消订单失败: {e}")
        except Exception as e:
            print(f"获取挂单失败: {e}")
        
        # 2. 平掉相反方向仓位
        try:
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
                    await asyncio.sleep(0.2)  # 限流
        except Exception as e:
            print(f"平仓失败: {e}")
    
    async def open_position(self, exchange: ccxt.Exchange, symbol: str, direction: str, amount: Decimal):
        """开仓下单"""
        params = {
            'posSide': 'long' if direction == 'buy' else 'short',
            'tdMode': 'cross'
        }
        
        try:
            order = await exchange.create_order(
                symbol=symbol,
                type='market',
                side=direction,
                amount=float(amount),
                params=params
            )
            return order
        except Exception as e:
            print(f"开仓失败: {e}")
            raise
    
    async def close_position(self, exchange: ccxt.Exchange, symbol: str, pos_side: str, amount: Decimal):
        """平仓下单"""
        params = {
            'posSide': pos_side,
            'tdMode': 'cross',
            'reduceOnly': True
        }
        
        try:
            side = 'sell' if pos_side == 'long' else 'buy'
            order = await exchange.create_order(
                symbol=symbol,
                type='market',
                side=side,
                amount=float(amount),
                params=params
            )
            return order
        except Exception as e:
            print(f"平仓失败: {e}")
            raise
    
    async def create_grid_order(self, exchange: ccxt.Exchange, symbol: str, side: str, size: Decimal, price: Decimal):
        """创建单个网格订单"""
        if size <= Decimal('0'):
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
            
            # 记录活跃订单
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
        
        # 获取账户信息
        conn = self.get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM accounts WHERE id=%s", (account_id,))
                account = cursor.fetchone()
        finally:
            conn.close()
        
        if not account:
            print(f"账户 {account_id} 不存在")
            return
        
        exchange = self.get_exchange(account)
        symbol = signal['symbol']
        direction = signal['direction']  # 'buy' or 'sell'
        
        print(f"处理信号: {direction} {symbol}")

        # 1. 清空相反方向仓位和挂单
        await self.cleanup_opposite_positions(exchange, symbol, direction)
        
        # 2. 计算仓位大小
        position_size = await self.calculate_position_size(exchange, symbol)
        if position_size <= Decimal('0'):
            print("仓位计算为0，跳过执行")
            return
        
        # 3. 执行开仓
        market_price = await self.get_market_price(exchange, symbol)
        order = await self.open_position(
            exchange, symbol, direction, position_size
        )
        
        # 4. 记录订单
        await self.record_order(account_id, order)
        
        # 更新本地状态
        self.positions[symbol] = {
            'account_id': account_id,
            'direction': direction,
            'entry_price': market_price,
            'size': position_size,
            'last_check_price': market_price,
            'sold_size': Decimal('0'),  # 已卖出量(做多时)
            'bought_size': Decimal('0')  # 已买入量(做空时)
        }
    
    async def check_stop_conditions(self, symbol: str):
        """检查止损止盈条件"""
        position = self.positions.get(symbol)
        if not position:
            return
        
        account_id = position['account_id']
        conn = self.get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM accounts WHERE id=%s", (account_id,))
                account = cursor.fetchone()
        finally:
            conn.close()
        
        if not account:
            return
        
        exchange = self.get_exchange(account)
        current_price = await self.get_market_price(exchange, symbol)
        entry_price = position['entry_price']
        direction = position['direction']
        
        # 计算涨跌幅
        if direction == 'buy':
            profit_ratio = (current_price - entry_price) / entry_price
            stop_condition = profit_ratio <= -self.config.stop_profit_loss
            take_profit = profit_ratio >= self.config.stop_profit_loss
        else:
            profit_ratio = (entry_price - current_price) / entry_price
            stop_condition = profit_ratio <= -self.config.stop_profit_loss
            take_profit = profit_ratio >= self.config.stop_profit_loss
        
        # 触发止损/止盈
        if stop_condition or take_profit:
            print(f"触发{'止盈' if take_profit else '止损'}条件: {symbol}")
            await self.close_position(
                exchange, symbol, 
                'long' if direction == 'buy' else 'short',
                position['size']
            )
            del self.positions[symbol]  # 移除持仓记录
    
    async def manage_grid_orders(self, symbol: str):
        """管理网格订单"""
        position = self.positions.get(symbol)
        if not position:
            return
        
        account_id = position['account_id']
        conn = self.get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM accounts WHERE id=%s", (account_id,))
                account = cursor.fetchone()
        finally:
            conn.close()
        
        if not account:
            return
        
        exchange = self.get_exchange(account)
        current_price = await self.get_market_price(exchange, symbol)
        last_price = position['last_check_price']
        price_change = (current_price - last_price) / last_price
        
        # 检查是否达到网格阈值
        if abs(price_change) >= self.config.grid_step:
            direction = position['direction']
            size = position['size']
            
            # 做多方向处理
            if direction == 'buy':
                if price_change > 0:  # 价格上涨
                    sell_size = size * self.config.grid_sell_percent
                    if sell_size > Decimal('0'):
                        order = await self.create_grid_order(
                            exchange, symbol, 'sell', sell_size, current_price
                        )
                        if order:
                            position['size'] -= sell_size
                            position['sold_size'] += sell_size
                else:  # 价格下跌
                    if position['sold_size'] > Decimal('0'):
                        buy_size = position['sold_size'] * self.config.grid_buy_percent
                        if buy_size > Decimal('0'):
                            order = await self.create_grid_order(
                                exchange, symbol, 'buy', buy_size, current_price
                            )
                            if order:
                                position['sold_size'] -= buy_size
                                position['size'] += buy_size
            
            # 做空方向处理（逻辑相反）
            else:  
                if price_change < 0:  # 价格下跌
                    buy_size = size * self.config.grid_sell_percent
                    if buy_size > Decimal('0'):
                        order = await self.create_grid_order(
                            exchange, symbol, 'buy', buy_size, current_price
                        )
                        if order:
                            position['size'] -= buy_size
                            position['bought_size'] += buy_size
                else:  # 价格上涨
                    if position['bought_size'] > Decimal('0'):
                        sell_size = position['bought_size'] * self.config.grid_buy_percent
                        if sell_size > Decimal('0'):
                            order = await self.create_grid_order(
                                exchange, symbol, 'sell', sell_size, current_price
                            )
                            if order:
                                position['bought_size'] -= sell_size
                                position['size'] += sell_size
            
            # 更新最后检查价格
            position['last_check_price'] = current_price
    
    async def signal_processing_task(self):
        """任务1：处理交易信号"""
        while self.running:
            try:
                conn = self.get_db_connection()
                with conn.cursor() as cursor:
                    cursor.execute(
                        "SELECT * FROM signals WHERE status='pending' AND processed=0 ORDER BY timestamp ASC LIMIT 1"
                    )
                    signal = cursor.fetchone()
                
                if signal:
                    await self.process_signal(signal)
                    
                    # 标记信号已处理
                    with conn.cursor() as cursor:
                        cursor.execute(
                            "UPDATE signals SET processed=1 WHERE id=%s",
                            (signal['id'],)
                        )
                        conn.commit()
                
                await asyncio.sleep(1)  # 每秒检查一次
                
            except Exception as e:
                print(f"信号处理任务异常: {e}")
                await asyncio.sleep(5)
            finally:
                if 'conn' in locals():
                    conn.close()
    
    async def price_monitoring_task(self):
        """任务2：监控价格变化"""
        while self.running:
            try:
                # 获取所有需要监控的持仓
                symbols = list(self.positions.keys())
                if not symbols:
                    await asyncio.sleep(self.config.check_interval)
                    continue
                
                # 批量处理每个持仓
                for symbol in symbols:
                    # 1. 检查止损止盈
                    await self.check_stop_conditions(symbol)
                    
                    # 2. 管理网格订单
                    await self.manage_grid_orders(symbol)
                
                await asyncio.sleep(self.config.check_interval)
                
            except Exception as e:
                print(f"价格监控任务异常: {e}")
                await asyncio.sleep(5)
    
    async def run(self):
        """运行交易机器人"""
        # 初始化交易所连接
        conn = self.get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM accounts WHERE status='active'")
                accounts = cursor.fetchall()
                for account in accounts:
                    self.get_exchange(account)  # 初始化所有活跃账户
        finally:
            conn.close()
        
        # 启动双任务
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
        print("程序已安全停止")