import json
import os
from typing import Dict, Tuple
import ccxt
from decimal import Decimal
from database import Database
import asyncio
from datetime import datetime, timezone
import uuid
import logging

async def get_exchange(self, account_id: int) -> ccxt.Exchange:
    """获取交易所实例（通过account_id）"""
    account_info = await self.db.get_account_info(account_id)
    if account_info:
        exchange_id = account_info['exchange']
        exchange_class = getattr(ccxt, exchange_id)
        exchange = exchange_class({
            'apiKey': account_info['api_key'],
            'secret': account_info['api_secret'],
            'password': account_info.get('api_passphrase', None),
            "options": {"defaultType": "swap"},
            "timeout": 30000,
            # 'enableRateLimit': True,
        })
        is_simulation = os.getenv("IS_SIMULATION", '1')
        if is_simulation == '1': # 1表示模拟环境
            exchange.set_sandbox_mode(True)
        return exchange
    return None

async def get_market_price(exchange: ccxt.Exchange, symbol: str) -> Decimal:
    """获取当前市场价格"""
    ticker = exchange.fetch_ticker(symbol)
    return Decimal(str(ticker['last']))

async def get_market_precision(exchange: ccxt.Exchange, symbol: str, instType: str = 'SWAP') -> Tuple[Decimal, Decimal]:
    """获取市场的价格和数量精度"""
    try:
        markets = exchange.fetch_markets_by_type(instType, {'instId': f"{symbol}"})
        # print("markets:", markets)
        contract_size = Decimal(str(markets[0]['contractSize']))  # 默认是1，适用于BTC
        price_precision = Decimal(str(markets[0]['precision']['price']))
        amount_precision = Decimal(str(markets[0]['precision']['amount']))
        min_amount = Decimal(str(markets[0]['limits']['amount']['min']))  # 最小下单量
        return {
            'min_amount': min_amount,
            'contract_size': contract_size,
            'price': price_precision,
            'amount': amount_precision,
        }
    except Exception as e:
        print(f"获取市场精度失败: {e}")
        return Decimal('0.0001'), Decimal('0.0001')  # 设置默认精度值


async def get_client_order_id():
    prefix = 'Zx'
    date_str = datetime.now().strftime('%Y%m%d')
    unique_str = str(uuid.uuid4()).replace('-', '')[6:19]  # 模仿 uniqid() 第7到19位
    ascii_str = ''.join(str(ord(c)) for c in unique_str)[:8]
    client_order_id = prefix + date_str + ascii_str
    return client_order_id


async def open_position(self, account_id: int, symbol: str, side: str, pos_side: str, amount: float, price: float, order_type: str, client_order_id: str = None, is_reduce_only: bool = False):
        """开仓、平仓下单"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return None
        
        params = {
            'posSide': pos_side,
            'tdMode': 'cross',
            'clOrdId': client_order_id,
            'reduceOnly': is_reduce_only,
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

#获取账户余额
async def get_account_balance(exchange: ccxt.Exchange, symbol:str) -> Decimal:
    """获取账户余额"""
    try:
        params = {}
        if symbol:
            trading_pair = symbol.replace("-", ",")
            params = {
                'ccy': trading_pair
            }
        balance = exchange.fetch_balance(params)
        total_equity = Decimal(str(balance["USDT"]['total']))
        return total_equity
    except Exception as e:
        print(f"获取账户余额失败: {e}")
        return Decimal('0')

async def cleanup_opposite_positions(self, exchange: ccxt.Exchange, account_id: int, symbol: str, direction: str):
    """平掉相反方向仓位"""
    try:
        # 从订单表中获取未平仓的反向订单数据
        unclosed_opposite_orders = await self.db.get_unclosed_opposite_orders(account_id, symbol, direction)
        print("未平仓的反向订单数据:", unclosed_opposite_orders)
        if not unclosed_opposite_orders:
            print("未找到未平仓的反向订单")
            return
        for order in unclosed_opposite_orders:
            order_id = order['id']
            order_side = order['side']
            order_size = Decimal(str(order['filled'])) if 'filled' in order else Decimal(str(order['amount']))

            if order_side != direction and order_size > 0:
                print("orderId:", order_id)
                close_side = 'sell' if order_side == 'long' else 'buy'
                market_price = await get_market_price(exchange, symbol)
                client_order_id = await get_client_order_id()
                # 执行平仓操作
                close_order = await self.open_position(
                    account_id,
                    symbol,
                    close_side,
                    order_side,
                    float(order_size),
                    market_price,
                    'market',
                    client_order_id,
                    True,  # 设置为平仓
                )

                if not close_order:
                    await asyncio.sleep(0.2)
                    continue
                
                await self.db.update_order_by_id(account_id, order_id, {'is_clopos': 1})

                # 记录平仓订单和持仓
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': close_order['id'],
                    'price': float(market_price),
                    'executed_price': None,
                    'quantity': float(order_size),
                    'pos_side': order_side,
                    'order_type': 'market',
                    'side': close_side,
                    'status': 'filled',
                    'is_clopos': 1,
                })
                # await self.record_order(
                #     exchange,
                #     account_id,
                #     close_order['id'],
                #     market_price,
                #     order_size,
                #     symbol,
                # )
                # 进行更新订单状态
                await asyncio.sleep(0.2)

    except Exception as e:
        print(f"清理仓位失败: {e}")  


async def milliseconds_to_local_datetime(milliseconds: int) -> str:
    """
    将毫秒时间戳转换为 UTC 时间的字符串格式
    :param milliseconds: 毫秒时间戳
    :return: 格式化的时间字符串 (YYYY-MM-DD HH:MM:SS)
    """
    # 将毫秒时间戳转换为秒
    seconds = int(milliseconds) / 1000.0
    # 转换为 UTC 时间
    utc_time = datetime.fromtimestamp(seconds, tz=timezone.utc)
    # 格式化为字符串
    formatted_time = utc_time.strftime('%Y-%m-%d %H:%M:%S')
    return formatted_time

async def get_latest_filled_price_from_position_history(exchange, symbol: str, pos_side: str = None) -> Decimal:
    """
    获取最近一条历史持仓记录的成交价 avgPx（适配 OKX 双向持仓）

    :param exchange: ccxt.okx 实例
    :param symbol: 交易对（如 'BTC/USDT:USDT'）
    :param pos_side: 'long' 或 'short'，可选
    :return: Decimal 类型的成交价，没有记录则返回 Decimal('0')
    """
    try:
        # 只请求最近一条记录（limit = 1）
        positions = exchange.fetch_position_history(symbol, None, 1)

        if not positions:
            print("⚠️ 没有历史持仓记录")
            return Decimal('0')

        pos = positions[0]

        # 检查方向（可选）
        if pos_side and pos['info'].get('direction') != pos_side.upper():
            # print(f"⚠️ 最近的记录方向是 {pos['info'].get('direction')}，不匹配 {pos_side}")
            return Decimal('0')

        avg_px = pos['info'].get('closeAvgPx', '0')
        # print(f"✅ 最近成交价: {avg_px}（方向: {pos['info'].get('direction')}）")

        return Decimal(avg_px)

    except Exception as e:
        print(f"❌ 获取最新持仓历史失败: {e}")
        return Decimal('0')
    

async def cancel_all_orders(self, account_id: int, symbol: str, side: str = 'all'):
    """取消所有未成交的订单"""
    exchange = await get_exchange(self, account_id)
    if not exchange:
        return None
    
    try:
        open_orders = exchange.fetch_open_orders(symbol, None, None, {'instType': 'SWAP'}) # 获取未成交的订单
        # print(f"未成交订单: {open_orders}")
        for order in open_orders:
            order_side = order.get('side', '').lower()

            # 根据 side 参数过滤订单
            if side != 'all' and order_side != side:
                continue  # 跳过不匹配的订单

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

async def get_max_position_value(self, account_id: int, symbol: str) -> Decimal:
    """根据交易对匹配对应的最大仓位值"""
    normalized_symbol = symbol.upper().replace('-SWAP', '')
    max_position_list = self.db.account_config_cache.get(account_id, {}).get('max_position_list', [])
    max_position_list_arr = json.loads(max_position_list)
    for item in max_position_list_arr:
        if item.get('symbol') == normalized_symbol:
            return Decimal(item.get('value'))
    return Decimal('0')  # 如果没有匹配到，返回0

async def get_grid_percent_list(self, account_id: int, direction: str) -> Decimal:
    """根据交易对匹配对应的最大仓位值"""
    grid_percent_list = self.db.account_config_cache.get(account_id, {}).get('grid_percent_list', [])
    grid_percent_list_arr = json.loads(grid_percent_list)
    for item in grid_percent_list_arr:
        if item.get('direction') == direction:
            return item
    return []  # 如果没有匹配到，返回[]

async def fetch_positions_history(self, account_id: int, inst_type: str = "SWAP", inst_id: str = None, limit: str = '100', after: str = None, before: str = None):
    """
    分页获取历史持仓记录（已平仓仓位）
    :param account_id: 账户ID
    :param inst_type: 交易类型，如 SWAP
    :param inst_id: 指定交易对（如 BTC-USDT-SWAP），可选
    :param limit: 每页获取数量（最大100）
    :return: 历史持仓记录列表
    """
    exchange = await get_exchange(self, account_id)
    if not exchange:
        return []

    history = []
    while True:
        try:
            params = {
                "instType": inst_type,
                "after": after,
                "before": before,
            }

            # 调用交易所接口获取历史持仓
            result = exchange.fetch_positions_history([inst_id], None, limit, params)

            # 如果返回结果为空，说明已经获取完所有数据
            if not result:
                break

            return result

        except Exception as e:
            print(f"获取历史持仓失败: {e}")
            break
    
async def fetch_current_positions(self, account_id: int, symbol: str, inst_type: str = "SWAP") -> list:
    """获取当前持仓信息，返回持仓数据列表"""
    try:
        exchange = await get_exchange(self, account_id)
        if not exchange:
            raise Exception("无法获取交易所对象")
        positions = exchange.fetch_positions_for_symbol(symbol, {"instType": inst_type})
        return positions

    except Exception as e:
        print(f"获取当前持仓信息失败: {e}")
        return []

# 获取账户总持仓数
async def get_total_positions(self, account_id: int, symbol: str, inst_type: str = "SWAP") -> Decimal:
    """获取账户总持仓数"""
    try:
        positions = await fetch_current_positions(self, account_id, symbol, inst_type)
        total_positions = sum(abs(Decimal(position['info']['pos'])) for position in positions)
        return total_positions

    except Exception as e:
        print(f"获取账户总持仓数失败: {e}")
        return Decimal('0')
    
#更新订单状态以及进行配对订单、计算利润
async def update_order_status(self, order: dict, account_id: int, executed_price: float = None, fill_date_time: str = None):
    """更新订单状态以及进行配对订单、计算利润"""
    exchange = await get_exchange(self, account_id)
    if not exchange:
        return
    print("开始匹配订单") 
    side = 'sell' if order['side'] == 'buy' else 'buy'
    get_order_by_price_diff = await self.db.get_order_by_price_diff_v2(account_id, order['info']['instId'], executed_price, side)
    # print("get_order_by_price_diff", get_order_by_price_diff)
    profit = 0
    group_id = ""
    # new_price = await get_market_price(exchange, order['info']['instId'])
    # print(f"最新价格: {new_price}")
    if get_order_by_price_diff:
        if order['side'] == 'sell' and (Decimal(executed_price) >= Decimal(get_order_by_price_diff['executed_price'])):
        # if order['side'] == 'buy':
            # 计算利润
            group_id = str(uuid.uuid4())
            profit = (Decimal(executed_price) - Decimal(get_order_by_price_diff['executed_price'])) * Decimal(min(order['amount'], get_order_by_price_diff['quantity']))
            print(f"配对订单成交，利润 buy: {profit}")
            logging.info(f"配对订单成交，利润 buy: {profit}")
        if order['side'] == 'buy' and (Decimal(executed_price) <= Decimal(get_order_by_price_diff['executed_price'])):
        # if order['side'] == 'sell':
            # 计算利润
            group_id = str(uuid.uuid4())
            profit = (Decimal(get_order_by_price_diff['executed_price']) - Decimal(executed_price)) * Decimal(min(order['amount'], get_order_by_price_diff['quantity']))
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
    else:
        await self.db.update_order_by_id(account_id, order['id'], {
            'executed_price': executed_price, 
            'status': order['info']['state'], 
            'fill_time': fill_date_time, 
        })


