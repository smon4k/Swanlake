import logging
import pymysql
from typing import Dict, List, Optional
import json

TABLE_PREFIX = "g_"

def table(name: str) -> str:
    return f"{TABLE_PREFIX}{name}"

class Database:
    def __init__(self, db_config: Dict):
        self.db_config = db_config
        self.account_cache: Dict[int, dict] = {}  # 账户信息缓存
        self.account_config_cache: Dict[int, dict] = {}  # 账户配置信息缓存
        self.tactics_accounts_cache: Dict[str, List[int]] = {}  # 策略账户信息缓存

    def get_db_connection(self):
        """获取数据库连接"""
        return pymysql.connect(**self.db_config)

    # 根据账户id读取配置文件数据
    async def get_config_by_account_id(self, account_id: int) -> Optional[Dict]:
        """从数据库获取配置文件数据"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT * FROM {table('config')} WHERE account_id=%s
                """, (account_id,))
                config = cursor.fetchone()
                if config:
                    # print(f'配置已经缓存: {account_id}, {config}')
                    self.account_config_cache[account_id] = config  # 缓存配置信息
                return config
        except Exception as e:
            print(f"获取配置文件数据失败: {e}")
            logging.error(f"获取配置文件数据失败: {e}")
            return None

    async def get_account_info(self, account_id: int) -> Optional[Dict]:
        """从数据库获取账户信息（带缓存）"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT id, exchange, api_key, api_secret, api_passphrase 
                    FROM {table('accounts')} WHERE id=%s AND status=%s
                """, (account_id, 1))
                account = cursor.fetchone()
                if account:
                    self.account_cache[account_id] = account
                return account
        except Exception as e:
            print(f"获取账户信息失败: {e}")
            logging.error(f"获取账户信息失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def insert_signal(self, signal_data: Dict):
        """写入信号到signals表并返回结果"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    INSERT INTO {table('signals')} (name, timestamp, symbol, direction, price, size, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (
                    signal_data['name'],
                    signal_data['timestamp'],
                    signal_data['symbol'],
                    signal_data['direction'],
                    signal_data['price'],
                    signal_data['size'],
                    signal_data['status'],
                ))
                conn.commit()
                return {"status": "success", "message": "Signal inserted successfully"}
        except Exception as e:
            print(f"写入信号失败: {e}")
            logging.error(f"写入信号失败: {e}")
            return {"status": "error", "message": str(e)}
        finally:
            if conn:
                conn.close()
    
    #获取信号表数据最新的一条数据
    async def get_latest_signal(self, symbol: Optional[str] = None, name: Optional[str] = None, account_id: int = 0) -> Optional[Dict]:
        """获取信号表数据最新的一条数据，如果 symbol 为空则返回所有的最新一条"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                query = f"SELECT * FROM {table('signals')} WHERE 1=1"
                params = []
                
                if symbol:
                    query += " AND symbol = %s"
                    params.append(symbol)
                if name:
                    query += " AND name = %s"
                    params.append(name)
                if account_id > 0:
                    query += " AND account_id = %s"
                    params.append(account_id)
                
                query += " ORDER BY id DESC LIMIT 1"
                cursor.execute(query, tuple(params))
                signal = cursor.fetchone()
                return signal
        except Exception as e:
            print(f"获取最新信号失败: {e}")
            logging.error(f"获取最新信号失败: {e}")
            return None
        finally:
            if conn:
                conn.close()


    # 获取信号表中做多和做空的最新一条记录
    async def get_latest_signal_by_direction(self) -> Optional[Dict]:
        """获取信号表中做多和做空的最新一条记录"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT * FROM {table('signals')}
                    WHERE (direction='long' AND size=1) OR (direction='short' AND size=-1)
                    ORDER BY id DESC LIMIT 1
                """)
                signal = cursor.fetchone()
                return signal
        except Exception as e:
            print(f"获取最新信号失败: {e}")
            logging.error(f"获取最新信号失败: {e}")
            return None
        finally:
            if conn:
                conn.close()
                
    async def record_order(self, account_id: int, order_id: str, price: float, quantity: float, symbol: str, order_info: Dict, is_clopos: int = 0):
        """记录订单到数据库"""
        conn = None
        try:
            print("order_info:", order_info)
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    INSERT INTO {table('orders')}
                    (account_id, symbol, order_id, side, order_type, pos_side, quantity, price, executed_price, status, is_clopos)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    executed_price = VALUES(executed_price),
                    status = VALUES(status),
                    is_clopos = VALUES(is_clopos)
                """, (
                    account_id,
                    symbol,
                    order_id,
                    order_info['side'],
                    order_info['info']['ordType'],
                    order_info['info']['posSide'],
                    quantity,
                    price,
                    order_info['info']['fillPx'],
                    order_info['info']['state'],
                    is_clopos
                ))
            conn.commit()
        except Exception as e:
            print(f"订单记录失败: {e}")
            logging.error(f"订单记录失败: {e}")
        finally:
            if conn:
                conn.close()
    
        # 添加订单数据，只添加订单一些基本的信息数据
    async def add_order(self, order_info: Dict):
        """添加订单数据"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    INSERT INTO {table('orders')}
                    (account_id, symbol, position_group_id, profit, order_id, clorder_id, side, order_type, pos_side, quantity, price, executed_price, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    executed_price = VALUES(executed_price),
                    status = VALUES(status)
                """, (
                    order_info['account_id'],
                    order_info['symbol'],
                    order_info['position_group_id'] if 'position_group_id' in order_info else '',
                    order_info.get('profit') if order_info.get('profit') is not None else 0,
                    order_info['order_id'],
                    order_info['clorder_id'],
                    order_info['side'],
                    order_info['order_type'],
                    order_info['pos_side'],
                    order_info['quantity'],
                    order_info['price'],
                    order_info['executed_price'],
                    order_info['status'],
                ))
            conn.commit()
        except Exception as e:
            print(f"添加订单数据失败: {e}")
            logging.error(f"添加订单数据失败: {e}")
            return {"status": "error", "message": str(e)}
        finally:
            if conn:
                conn.close()

    async def get_order_by_id(self, account_id: int, order_id: str) -> Optional[Dict]:
        """从数据库获取指定订单信息"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT * FROM {table('orders')} WHERE account_id=%s AND order_id=%s
                """, (account_id, order_id))
                order = cursor.fetchone()
                return order
        except Exception as e:
            print(f"获取指定订单信息失败: {e}")
            logging.error(f"获取指定订单信息失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def update_order_by_id(self, account_id: int, order_id: str, updates: Dict):
        """根据订单ID更新订单信息"""
        conn = None
        try:
            # print("更新订单信息:", account_id, order_id, updates)
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                set_clause = ", ".join([f"{key}=%s" for key in updates.keys()])
                values = list(updates.values()) + [account_id, order_id]
                query = f"""
                    UPDATE {table('orders')}
                    SET {set_clause}
                    WHERE account_id=%s AND order_id=%s
                """
                cursor.execute(query, values)
            conn.commit()
        except Exception as e:
            print(f"更新订单信息失败: {e}")
            logging.error(f"更新订单信息失败: {e}")
        finally:
            if conn:
                conn.close()

    # 根据账户ID和交易对更新订单数据
    async def update_order_by_symbol(self, account_id: int, symbol: str, updates: Dict):
        """根据账户ID和交易对更新订单数据"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                set_clause = ", ".join([f"{key}=%s" for key in updates.keys()])
                values = list(updates.values()) + [account_id, symbol]
                query = f"""
                    UPDATE {table('orders')}
                    SET {set_clause}
                    WHERE account_id=%s AND symbol=%s
                """
                cursor.execute(query, values)
            conn.commit()
        except Exception as e:
            print(f"更新订单信息失败: {e}")
            logging.error(f"更新订单信息失败: {e}")
        finally:
            if conn:
                conn.close()

    # 获取指定账户和交易对的所有未撤单订单
    async def get_active_orders(self, account_id: int) -> List[Dict]:
        """获取指定账户中所有未撤单订单（status为'live', buy sell limit订单），按ID升序排列"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT * FROM {table('orders')} 
                    WHERE account_id=%s AND (status = 'live' OR status = 'partially_filled') AND (side = 'buy' OR side = 'sell') AND order_type = 'limit'  ORDER BY id DESC
                """, (account_id))
                results = cursor.fetchall()
                return results
        except Exception as e:
            print(f"获取订单失败: {e}")
            logging.error(f"获取订单失败: {e}")
            return []
        finally:
            if conn:
                conn.close()
    
    # 获取订单表中最新成交的一条记录
    async def get_latest_filled_order(self, account_id: int, symbol: str) -> Optional[Dict]:
        """
        获取指定账户和交易对的最新成交订单记录

        :param account_id: 账户ID
        :param symbol: 交易对
        :return: 最新成交的订单记录，如果没有则返回None
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                query = f"""
                    SELECT * FROM {table('orders')}
                    WHERE account_id = %s 
                    AND symbol = %s 
                    AND status = 'filled'
                    ORDER BY id DESC
                    LIMIT 1
                """
                cursor.execute(query, (account_id, symbol))
                result = cursor.fetchone()
                if result:
                    return result
                else:
                    return None
        except Exception as e:
            print(f"获取最新成交订单记录失败: {e}")
            logging.error(f"获取最新成交订单记录失败: {e}")
            return None
        finally:
            if conn:
                conn.close()


    # 获取未平仓的反向订单数据
    async def get_unclosed_opposite_quantity(self, account_id, symbol, direction) -> float:
        """
        获取未平仓反向订单的总数量（quantity总和）
        :param account_id: 账户ID
        :param symbol: 交易对
        :param direction: 目标方向（long/short）
        :return: 总未平仓反向订单的数量（float）
        """
        opposite_direction = 'short' if direction == 'long' else 'long'
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                # 查询总 quantity（未被取消、未平仓、反向方向、未被标记为已平仓）
                query = f"""
                    SELECT SUM(quantity) AS total_quantity
                    FROM {table('orders')}
                    WHERE account_id = %s 
                    AND symbol = %s 
                    AND pos_side = %s 
                    AND status != 'cancelled' 
                    AND status != 'closed'
                    AND is_clopos = 0
                """
                cursor.execute(query, (account_id, symbol, opposite_direction))
                result = cursor.fetchone()
                total_quantity = result[0] if result[0] is not None else 0
                return float(total_quantity)
        except Exception as e:
            print(f"数据库查询错误: {e}")
            logging.error(f"数据库查询错误: {e}")
            return 0
        finally:
            if conn:
                conn.close()
    async def mark_orders_as_closed(self, account_id: int, symbol: str, direction: str):
        """将某账户某交易对指定方向的未平仓订单标记为已平仓"""
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    UPDATE {table('orders')}
                    SET is_clopos = 1
                    WHERE account_id = %s
                    AND symbol = %s 
                    AND pos_side = %s 
                    AND status != 'cancelled'
                    AND is_clopos = 0
                """, (account_id, symbol, direction))
                conn.commit()
        except Exception as e:
            print(f"标记订单为已平仓失败: {e}")
            logging.error(f"标记订单为已平仓失败: {e}")
        finally:
            if conn:
                conn.close()


    
    # 获取最新订单方向以及持仓方向的已成交订单数据
    async def get_completed_order(self, account_id, symbol, direction):
        """
        从订单表获取指定订单方向以及持仓方向的已成交订单数据
        :param account_id: 账户ID
        :param symbol: 交易对
        :param direction: 目标方向（long/short）
        :return: 已成交订单数据列表
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                # 查询已成交（status为filled）的指定订单方向以及持仓方向的订单
                query = f"""
                    SELECT id, account_id, timestamp, symbol, order_id, side, order_type, pos_side, quantity, price, executed_price, status, is_clopos
                    FROM {table('orders')}
                    WHERE account_id = %s AND symbol = %s AND pos_side = %s AND status = 'filled' AND is_clopos = 0
                    ORDER BY id DESC
                """
                cursor.execute(query, (account_id, symbol, direction))
                order = cursor.fetchone()
                return order
        except Exception as e:
            print(f"数据库查询错误: {e}")
            logging.error(f"数据库查询错误: {e}")
            return []
        finally:
            conn.close()
    
    async def get_order_by_price_diff(self, account_id, symbol, direction, latest_price: float):
        """
        查询订单表中买入或卖出已成交的position_group_id为空的，按照成交时间降序排序，成交价格和最新价格之差的绝对值升序排序的一条数据
        :param account_id: 账户ID
        :param symbol: 交易对
        :param direction: 目标方向（long/short）
        :return: 符合条件的订单数据
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                # 查询已成交（status为filled）的指定订单方向以及持仓方向的订单
                # SELECT id, account_id, timestamp, symbol, order_id, side, order_type, side, quantity, price, executed_price, status, is_clopos FROM g_orders WHERE account_id = 2 AND symbol = 'ETH-USDT-SWAP' AND side = 'sell' AND status = 'filled' AND (is_clopos = 0 or is_clopos = 1) AND position_group_id = ''  AND executed_price IS NOT NULL ORDER BY ABS(1811.19 - executed_price) ASC, fill_time DESC LIMIT 1
                query = f"""
                    SELECT id, account_id, timestamp, symbol, order_id, side, order_type, side, quantity, price, executed_price, status, is_clopos
                    FROM {table('orders')}
                    WHERE account_id = %s AND symbol = %s AND side = %s AND status = 'filled' AND (is_clopos = 0 or is_clopos = 1)
                    AND position_group_id = ''
                    AND executed_price IS NOT NULL
                    ORDER BY ABS(%s - executed_price) ASC, fill_time DESC
                    LIMIT 1
                """
                cursor.execute(query, (account_id, symbol, direction, latest_price))
                order = cursor.fetchone()
                return order
        except Exception as e:
            print(f"数据库查询错误: {e}")
            logging.error(f"数据库查询错误: {e}")
            return []
        finally:
            conn.close()
    
    async def get_order_by_price_diff_v2(self, account_id: int, symbol: str, latest_price: float, mode: str = 'sell') -> Optional[Dict]:
        """
        根据基准订单，查询符合条件的一条订单（做多找卖，做空找买）
        :param account_id: 账户ID
        :param symbol: 交易对
        :param base_order: 基准订单(dict)，例如买单或者卖单
        :param mode: 查询方向 'sell'（找卖单）或者 'buy'（找买单）
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                if mode == 'sell':
                    # 找价格高于买单成交价的卖单
                    condition = "executed_price > %s"
                    order_side = 'sell'
                    sort_order = "executed_price ASC"
                else:
                    # 找价格低于卖单成交价的买单
                    condition = "executed_price < %s"
                    order_side = 'buy'
                    sort_order = "executed_price DESC"

                cursor.execute(f"""
                    SELECT * FROM {table('orders')}
                    WHERE account_id = %s 
                    AND symbol = %s 
                    AND side = %s
                    AND status = 'filled'
                    AND (is_clopos = 0 OR is_clopos = 1)
                    AND position_group_id = ''
                    AND executed_price IS NOT NULL
                    AND {condition}
                    ORDER BY {sort_order}, fill_time DESC
                    LIMIT 1
                """, (account_id, symbol, order_side, latest_price))
                match_order = cursor.fetchone()

            return match_order
        except Exception as e:
            print(f"查询配对订单失败: {e}")
            logging.error(f"查询配对订单失败: {e}")
            return None
        finally:
            if conn:
                conn.close()
    
    #生成一个获取币种最大仓位配置数据，获取g_config里面的max_position_list策略字段数据（[{"symbol":"ETH-USDT","value":"1000","tactics":"Y1.1"},{"symbol":"BTC-USDT","value":"1000","tactics":"Q2.4"}]），检索所有配置数据，将对应的策略对应到指定的用户Id 例如：Y1.1：[account_1, account_2]
    async def get_account_max_position(self) -> Optional[Dict]:
        """
        获取指定账户的最大仓位配置数据
        :return: 最大仓位配置数据
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT a.id as account_id, c.max_position_list as max_position_list
                    FROM {table('accounts')} a
                    INNER JOIN {table('config')} c ON a.id = c.account_id
                    WHERE a.status = %s
                """, (1))
                result = cursor.fetchall()
                if result:
                    tactics_accounts = {}
                    for row in result:
                        account_id = row.get('account_id')
                        max_position_list = row.get('max_position_list')
                        if not max_position_list:
                            continue
                        max_position_list_arr = json.loads(max_position_list)
                        # print(max_position_list_arr)
                        for pos in max_position_list_arr:
                            tactic = pos.get("tactics")
                            if tactic:
                                tactics_accounts.setdefault(tactic, []).append(account_id)
                    self.tactics_accounts_cache = tactics_accounts
                    return tactics_accounts
                else:
                    return None
        except Exception as e:
            print(f"获取最大仓位配置数据失败: {e}")
            logging.error(f"获取最大仓位配置数据失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def get_tactics_by_account_and_symbol(self, account_id: int, symbol: str) -> Optional[str]:
        """
        获取配置表中指定用户和指定币种的max_position_list下面对应的tactics
        :param account_id: 用户ID
        :param symbol: 币种
        :return: 对应的tactics，如果没有则返回None
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT max_position_list
                    FROM {table('config')}
                    WHERE account_id = %s
                """, (account_id,))
                result = cursor.fetchone()
                if result and result.get('max_position_list'):
                    max_position_list = json.loads(result['max_position_list'])
                    for item in max_position_list:
                        if item.get('symbol') == symbol:
                            return item.get('tactics')
                return None
        except Exception as e:
            print(f"获取tactics失败: {e}")
            logging.error(f"获取tactics失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

        
