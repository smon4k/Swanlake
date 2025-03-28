import time
import pymysql
import ccxt
from decimal import Decimal


# 买 做多
# 卖 做空

# 止盈 现价单
# 止损 市价单

# 1 收到通知买入卖出信号，现有仓位80%，开仓；现有仓位满仓张数可配置，参数80%可配置； 
# 2 止盈/止损按照价格涨跌0.7%进行平仓；参数0.7可配置
# 3 以做多为例。做空，反之亦然。
#    每上涨0.2%，挂卖剩余仓位的5%；
#    每下跌0.2%，如果之前有卖出，挂单接回卖出的仓位的4%；
#    参数 0.2%，5%, 4%可配置；
# 4 收到做多/空信号，清掉持有的空/多单，以及所有的挂单。
# 5 支持多账户；
# 6 支持多交易所。
# 张数 = 总资产 * 倍数 / （价格 * 0.01）


# 配置 MySQL 连接
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "123456",
    "database": "trading_bot",
}

def get_db_connection():
    return pymysql.connect(**DB_CONFIG)

# 交易参数
CONFIG = {
    "position_size": 0.8,  # 80% 仓位
    "stop_loss": 0.007,  # 0.7% 止损
    "grid_up": 0.002,  # 上涨 0.2%
    "grid_down": 0.002,  # 下跌 0.2%
    "grid_recover": 0.9,  # 回购 90%
    "grid_buy": 0.001,  # 额外买入 0.1%
    "grid_sell": 0.05,  # 卖出 5%
}

# 获取交易所账户
def get_accounts():
    conn = get_db_connection()
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM accounts")
        accounts = cursor.fetchall()
    conn.close()
    return accounts

# 获取当前市场价格
def get_market_price(exchange, symbol):
    ticker = exchange.fetch_ticker(symbol)
    return Decimal(ticker["last"])

# 插入订单数据
def insert_order(symbol, order_id, side, price, quantity, status):
    conn = get_db_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            INSERT INTO orders (symbol, order_id, side, price, quantity, status)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (symbol, order_id, side, price, quantity, status))
    conn.commit()
    conn.close()

# 插入持仓数据
def insert_position(account_id, symbol, position_size, entry_price):
    conn = get_db_connection()
    with conn.cursor() as cursor:
        cursor.execute("""
            INSERT INTO positions (account_id, symbol, position_size, entry_price)
            VALUES (%s, %s, %s, %s)
        """, (account_id, symbol, position_size, entry_price))
    conn.commit()
    conn.close()

# 处理交易信号
def process_signals():
    conn = get_db_connection()
    with conn.cursor() as cursor:
        cursor.execute("SELECT * FROM signals WHERE status='pending'")
        signals = cursor.fetchall()
    
    for signal in signals:
        signal_id, timestamp, symbol, direction, status = signal
        print(f"处理信号: {direction} {symbol}")
        execute_trade(symbol, direction)
        
        # 更新信号状态
        with conn.cursor() as cursor:
            cursor.execute("UPDATE signals SET status='processed' WHERE id=%s", (signal_id,))
        conn.commit()
    conn.close()

# 执行交易并设置止损和网格单
def execute_trade(symbol, direction):
    """
    使用多个账户对指定交易对和方向执行交易。
    参数:
        symbol (str): 交易对符号 (例如: "BTC/USDT")。
        direction (str): 交易方向，"long" 表示买入，"short" 表示卖出。
    工作流程:
        1. 获取账户详情，并为每个账户初始化交易所 API。
        2. 获取账户余额，并根据配置计算仓位大小。
        3. 获取当前市场价格，并计算止损价格。
        4. 根据交易方向下市价单 (买入或卖出)。
        5. 记录已执行订单，并将订单详情插入数据库。
        6. 设置止损单以管理风险。
        7. 为指定交易对配置网格挂单。
        8. （已注释）可选地将持仓数据插入数据库。
    注意:
        - 函数依赖于辅助函数，例如 `get_accounts`、`get_market_price`、`insert_order` 和 `setup_grid_orders`。
        - 配置值如 `CONFIG["position_size"]` 和 `CONFIG["stop_loss"]` 需要全局定义。
        - 函数当前会打印余额、订单执行和止损详情，用于调试。
    异常:
        如果任何 API 调用或订单下单失败，异常将被抛出。
    """
    accounts = get_accounts()
    for account in accounts:
        exchange_name, api_key, api_secret, api_passphrase = account[1:]
        exchange = getattr(ccxt, exchange_name)({
            "apiKey": api_key,
            "secret": api_secret,
            "password": api_passphrase,
            "options": {"defaultType": "swap"},
        })
        trading_pair = symbol.replace("/", ",")
        balance = exchange.fetch_balance({"ccy": trading_pair})["info"]['data'][0]['totalEq']
        # balance = exchange.fetch_balance()
        position_size = Decimal(balance) * Decimal(CONFIG["position_size"]) # 仓位大小
        market_price = get_market_price(exchange, symbol)
        print(f"当前价格: {market_price}")
        stop_loss_price = market_price * (Decimal(1) - Decimal(CONFIG["stop_loss"])) if direction == "long" else market_price * (Decimal(1) + Decimal(CONFIG["stop_loss"]))
        
        if direction == "long":
            order = exchange.create_order(symbol, 'limit', 'buy', position_size)
            insert_order(symbol, order['id'], 'buy', order['price'], order['amount'], 'open')
        else:
            order = exchange.create_order(symbol, 'limit', 'sell', position_size)
            insert_order(symbol, order['id'], 'sell', order['price'], order['amount'], 'open')

        print(f"订单执行: {order}")
        
        # 设置止损
        # stop_loss_order = exchange.create_order(
        #     symbol, "stopMarket", "sell" if direction == "long" else "buy", position_size, stop_loss_price
        # )
        # print(f"止损单设置: {stop_loss_order}")
        
        # 设置网格单
        setup_grid_orders(exchange, symbol, position_size, market_price, direction)

        # 插入持仓数据
        # insert_position(account[0], symbol, position_size, market_price)

# 设定网格挂单
def setup_grid_orders(exchange, symbol, position_size, market_price, direction):
    for i in range(1, 6):
        grid_price_up = market_price * (1 + CONFIG["grid_up"] * i)
        grid_price_down = market_price * (1 - CONFIG["grid_down"] * i)
        grid_size = position_size * CONFIG["grid_sell"]
        
        if direction == "long":
            sell_order = exchange.create_limit_sell_order(symbol, grid_size, grid_price_up)
            print(f"挂卖单: {sell_order}")
            
            buy_order = exchange.create_limit_buy_order(symbol, grid_size * CONFIG["grid_recover"], grid_price_down)
            print(f"回购挂单: {buy_order}")
        else:
            buy_order = exchange.create_limit_buy_order(symbol, grid_size, grid_price_down)
            print(f"挂买单: {buy_order}")
            
            sell_order = exchange.create_limit_sell_order(symbol, grid_size * CONFIG["grid_recover"], grid_price_up)
            print(f"回吐挂单: {sell_order}")
            
process_signals()
# 主循环
# while True:
#     process_signals()
#     time.sleep(5)  # 每 5 秒检查一次信号
