import logging
import pymysql
from typing import Dict, List, Optional

TABLE_PREFIX = "g_"

def table(name: str) -> str:
    return f"{TABLE_PREFIX}{name}"

class Database:
    def __init__(self, db_config: Dict):
        self.db_config = db_config
        self.account_cache: Dict[int, dict] = {}  # 账户信息缓存

    def get_db_connection(self):
        """获取数据库连接"""
        return pymysql.connect(**self.db_config)

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
                    INSERT INTO {table('signals')} (account_id, timestamp, symbol, direction, price, size, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (
                    signal_data['account_id'],
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
    async def get_latest_signal(self, account_id: int) -> Optional[Dict]:
        """获取信号表数据最新的一条数据"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(f"""
                    SELECT * FROM {table('signals')} WHERE account_id=%s ORDER BY id DESC LIMIT 1
                """, (account_id,))
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
                    (account_id, symbol, position_group_id, order_id, clorder_id, side, order_type, pos_side, quantity, price, executed_price, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    executed_price = VALUES(executed_price),
                    status = VALUES(status)
                """, (
                    order_info['account_id'],
                    order_info['symbol'],
                    order_info['position_group_id'],
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
                    WHERE account_id=%s AND (status = 'live' OR status = 'partially_filled') AND (side = 'buy' OR side = 'sell') AND order_type = 'limit'  ORDER BY id DESC LIMIT 2
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