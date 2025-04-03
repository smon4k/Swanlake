import pymysql
from typing import Dict, List, Optional

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
                cursor.execute("""
                    SELECT id, exchange, api_key, api_secret, api_passphrase 
                    FROM accounts WHERE id=%s AND status=%s
                """, (account_id, 1))
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

    async def insert_signal(self, signal_data: Dict):
        """写入信号到signals表并返回结果"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO signals (account_id, timestamp, symbol, direction, status)
                    VALUES (%s, %s, %s, %s, %s)
                """, (
                    signal_data['account_id'],
                    signal_data['timestamp'],
                    signal_data['symbol'],
                    signal_data['direction'],
                    signal_data['status'],
                ))
                conn.commit()
                return {"status": "success", "message": "Signal inserted successfully"}
        except Exception as e:
            print(f"写入信号失败: {e}")
            return {"status": "error", "message": str(e)}
        finally:
            if conn:
                conn.close()
                
    async def record_order(self, account_id: int, order_id: str, price: float, quantity: float, symbol: str, order_info: Dict):
        """记录订单到数据库"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO orders 
                    (account_id, symbol, order_id, side, order_type, pos_side, quantity, price, executed_price, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    executed_price = VALUES(executed_price),
                    status = VALUES(status)
                """, (
                    account_id,
                    symbol,
                    order_id,
                    order_info['side'],
                    order_info['info']['ordType'],
                    order_info['info']['posSide'],
                    quantity,
                    price,
                    order_info['price'],
                    order_info['info']['state']
                ))
            conn.commit()
        except Exception as e:
            print(f"订单记录失败: {e}")
        finally:
            if conn:
                conn.close()

    async def get_order_by_id(self, account_id: int, order_id: str) -> Optional[Dict]:
        """从数据库获取指定订单信息"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT * FROM orders WHERE account_id=%s AND order_id=%s
                """, (account_id, order_id))
                order = cursor.fetchone()
                return order
        except Exception as e:
            print(f"获取指定订单信息失败: {e}")
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
                    UPDATE orders
                    SET {set_clause}
                    WHERE account_id=%s AND order_id=%s
                """
                cursor.execute(query, values)
            conn.commit()
        except Exception as e:
            print(f"更新订单信息失败: {e}")
        finally:
            if conn:
                conn.close()

    async def save_position(self, account_id: int, symbol: str, position_data: Dict):
        """保存持仓到数据库"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO positions 
                    (account_id, pos_id, symbol, position_size, entry_price, position_status, open_time)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    position_size = VALUES(position_size),
                    entry_price = VALUES(entry_price),
                    position_status = VALUES(position_status),
                    open_time = VALUES(open_time)
                """, (
                    account_id,
                    position_data.get('pos_id', None),
                    symbol,
                    float(position_data['position_size']),
                    float(position_data['entry_price']),
                    'open',
                    position_data.get('open_time', time.strftime('%Y-%m-%d %H:%M:%S'))
                ))
            conn.commit()
        except Exception as e:
            print(f"保存持仓失败: {e}")
        finally:
            if conn:
                conn.close()

    async def get_position_by_id(self, account_id: int, position_id: int) -> Optional[Dict]:
        """根据持仓ID和账户ID获取持仓信息"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("""
                    SELECT * FROM positions WHERE account_id=%s AND pos_id=%s
                """, (account_id, position_id))
                position = cursor.fetchone()
                return position
        except Exception as e:
            print(f"获取持仓信息失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def update_position_by_id(self, account_id: int, position_id: int, updates: Dict):
        """根据持仓ID更新持仓信息"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                set_clause = ", ".join([f"{key}=%s" for key in updates.keys()])
                values = list(updates.values()) + [account_id, position_id]
                query = f"""
                    UPDATE positions
                    SET {set_clause}
                    WHERE account_id=%s AND pos_id=%s
                """
                cursor.execute(query, values)
                conn.commit()
        except Exception as e:
            print(f"更新持仓信息失败: {e}")
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
                return positions
        except Exception as e:
            print(f"同步持仓失败: {e}")
        finally:
            if conn:
                conn.close()