import logging
import pymysql
from typing import Dict, List, Optional
import json
from datetime import datetime

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
                cursor.execute(
                    f"""
                    SELECT * FROM {table('config')} WHERE account_id=%s
                """,
                    (account_id,),
                )
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
                cursor.execute(
                    f"""
                    SELECT id, exchange, api_key, api_secret, api_passphrase, financ_state, status, auto_loan
                    FROM {table('accounts')} WHERE id=%s AND status=%s
                """,
                    (account_id, 1),
                )
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

    async def update_account_info(self, account_id: int, updates: Dict):
        """更新用户是否开启自动借币功能"""
        conn = None
        try:
            # print("更新订单信息:", account_id, order_id, updates)
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                set_clause = ", ".join([f"{key}=%s" for key in updates.keys()])
                values = list(updates.values()) + [account_id]
                query = f"""
                    UPDATE {table('accounts')}
                    SET {set_clause}
                    WHERE id=%s
                """
                cursor.execute(query, values)
            conn.commit()

            # 更新缓存
            await self.get_account_info(account_id)
        except Exception as e:
            print(f"更新用户信息失败: {e}")
            logging.error(f"更新用户信息失败: {e}")
        finally:
            if conn:
                conn.close()

    async def insert_signal(self, signal_data: Dict):
        """写入信号到signals表并返回结果"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    INSERT INTO {table('signals')} (name, timestamp, symbol, direction, price, size, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """,
                    (
                        signal_data["name"],
                        signal_data["timestamp"],
                        signal_data["symbol"],
                        signal_data["direction"],
                        signal_data["price"],
                        signal_data["size"],
                        signal_data["status"],
                    ),
                )
                conn.commit()
                return {"status": "success", "message": "Signal inserted successfully"}
        except Exception as e:
            print(f"写入信号失败: {e}")
            logging.error(f"写入信号失败: {e}")
            return {"status": "error", "message": str(e)}
        finally:
            if conn:
                conn.close()

    # 获取信号表数据最新的一条数据
    async def get_latest_signal(
        self,
        symbol: Optional[str] = None,
        name: Optional[str] = None,
        status: Optional[str] = None,
    ) -> Optional[Dict]:
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
                if status:
                    query += " AND status = %s"
                    params.append(status)

                query += " ORDER BY id DESC LIMIT 1"
                cursor.execute(query, tuple(params))
                signal = cursor.fetchone()

                if signal:
                    logging.debug(
                        f"📡 查询到信号: symbol={symbol}, name={name}, "
                        f"方向={signal.get('direction', 'N/A')}, 大小={signal.get('size', 'N/A')}"
                    )
                else:
                    logging.warning(
                        f"⚠️ 未找到信号: symbol={symbol}, name={name}, status={status}"
                    )

                return signal
        except Exception as e:
            logging.error(
                f"❌ 获取最新信号失败: symbol={symbol}, name={name}, 错误={e}",
                exc_info=True,
            )
            return None
        finally:
            if conn:
                conn.close()

    # 获取最近一次

    # 获取信号表中做多和做空的最新一条记录
    async def get_latest_signal_by_direction(self) -> Optional[Dict]:
        """获取信号表中做多和做空的最新一条记录"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    SELECT * FROM {table('signals')}
                    WHERE (direction='long' AND size=1) OR (direction='short' AND size=-1)
                    ORDER BY id DESC LIMIT 1
                """
                )
                signal = cursor.fetchone()
                return signal
        except Exception as e:
            print(f"获取最新信号失败: {e}")
            logging.error(f"获取最新信号失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def record_order(
        self,
        account_id: int,
        order_id: str,
        price: float,
        quantity: float,
        symbol: str,
        order_info: Dict,
        is_clopos: int = 0,
    ):
        """记录订单到数据库"""
        conn = None
        try:
            print("order_info:", order_info)
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    INSERT INTO {table('orders')}
                    (account_id, symbol, order_id, side, order_type, pos_side, quantity, price, executed_price, status, is_clopos, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    executed_price = VALUES(executed_price),
                    status = VALUES(status),
                    is_clopos = VALUES(is_clopos),
                    updated_at = NOW()
                """,
                    (
                        account_id,
                        symbol,
                        order_id,
                        order_info["side"],
                        order_info["info"]["ordType"],
                        order_info["info"]["posSide"],
                        quantity,
                        price,
                        order_info["info"]["fillPx"],
                        order_info["info"]["state"],
                        is_clopos,
                    ),
                )
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
                cursor.execute(
                    f"""
                    INSERT INTO {table('orders')}
                    (account_id, symbol, position_group_id, profit, order_id, clorder_id, side, order_type, pos_side, quantity, price, executed_price, status, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    executed_price = VALUES(executed_price),
                    status = VALUES(status),
                    updated_at = NOW()
                """,
                    (
                        order_info["account_id"],
                        order_info["symbol"],
                        (
                            order_info["position_group_id"]
                            if "position_group_id" in order_info
                            else ""
                        ),
                        (
                            order_info.get("profit")
                            if order_info.get("profit") is not None
                            else 0
                        ),
                        order_info["order_id"],
                        order_info["clorder_id"],
                        order_info["side"],
                        order_info["order_type"],
                        order_info["pos_side"],
                        order_info["quantity"],
                        order_info["price"],
                        order_info["executed_price"],
                        order_info["status"],
                    ),
                )
            conn.commit()
            logging.info(
                f"✅ 添加订单: 账户={order_info['account_id']}, "
                f"订单ID={order_info['order_id']}, 币种={order_info['symbol']}, "
                f"方向={order_info['side']}, 价格={order_info['price']}, "
                f"数量={order_info['quantity']}, 状态={order_info['status']}"
            )
        except Exception as e:
            logging.error(
                f"❌ 添加订单失败: 账户={order_info.get('account_id', 'N/A')}, "
                f"订单={order_info.get('order_id', 'N/A')}, 错误={e}",
                exc_info=True,
            )
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
                cursor.execute(
                    f"""
                    SELECT * FROM {table('orders')} WHERE account_id=%s AND order_id=%s
                """,
                    (account_id, order_id),
                )
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
            # 记录更新内容
            update_fields = ", ".join([f"{k}={v}" for k, v in updates.items()])
            logging.info(
                f"📝 更新订单: 账户={account_id}, 订单={order_id}, "
                f"更新字段=[{update_fields}]"
            )

            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                set_clause = ", ".join([f"{key}=%s" for key in updates.keys()])
                # 添加 updated_at 字段的自动更新
                set_clause += ", updated_at = NOW()"
                values = list(updates.values()) + [account_id, order_id]
                query = f"""
                    UPDATE {table('orders')}
                    SET {set_clause}
                    WHERE account_id=%s AND order_id=%s
                """
                cursor.execute(query, values)
                affected_rows = cursor.rowcount

            conn.commit()

            if affected_rows > 0:
                logging.debug(f"✅ 订单更新成功: 账户={account_id}, 订单={order_id}")
            else:
                logging.warning(
                    f"⚠️ 订单更新无影响: 账户={account_id}, 订单={order_id} (可能不存在)"
                )

        except Exception as e:
            logging.error(
                f"❌ 更新订单失败: 账户={account_id}, 订单={order_id}, 错误={e}",
                exc_info=True,
            )
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
                # 添加 updated_at 字段的自动更新
                set_clause += ", updated_at = NOW()"
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
                cursor.execute(
                    f"""
                    SELECT * FROM {table('orders')} 
                    WHERE account_id=%s AND (status = 'live' OR status = 'partially_filled') AND (side = 'buy' OR side = 'sell') AND order_type = 'limit'  ORDER BY id DESC
                """,
                    (account_id),
                )
                results = cursor.fetchall()

                if results:
                    order_summary = []
                    for order in results[:5]:  # 只显示前5个
                        order_summary.append(
                            f"{order['order_id'][:10]}...({order['side']}@{order['price']})"
                        )
                    logging.debug(
                        f"📋 账户 {account_id} 查询到 {len(results)} 个活跃订单: "
                        f"{', '.join(order_summary)}"
                    )
                else:
                    logging.debug(f"📋 账户 {account_id} 无活跃订单")

                return results
        except Exception as e:
            logging.error(f"❌ 账户 {account_id} 获取订单失败: {e}", exc_info=True)
            return []
        finally:
            if conn:
                conn.close()

    # 获取订单表中最新成交的一条记录
    async def get_latest_filled_order(
        self, account_id: int, symbol: str
    ) -> Optional[Dict]:
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

    # 获取未成交的委托订单数据
    async def get_unclosed_orders(
        self, account_id: int, symbol: str, order_type: str
    ) -> List[Dict]:
        """获取未成交的委托订单数据"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    SELECT * FROM {table('orders')}
                    WHERE account_id = %s 
                    AND symbol = %s 
                    AND status = 'live'
                    AND order_type = %s
                    ORDER BY id DESC
                    LIMIT 1
                """,
                    (account_id, symbol, order_type),
                )
                results = cursor.fetchone()

                if results:
                    logging.debug(
                        f"📋 查询到未成交订单: 账户={account_id}, 币种={symbol}, "
                        f"类型={order_type}, 订单ID={results.get('order_id', 'N/A')}"
                    )
                else:
                    logging.debug(
                        f"📋 无未成交订单: 账户={account_id}, 币种={symbol}, 类型={order_type}"
                    )

                return results
        except Exception as e:
            logging.error(
                f"❌ 查询未成交订单失败: 账户={account_id}, 币种={symbol}, "
                f"类型={order_type}, 错误={e}",
                exc_info=True,
            )
            return []
        finally:
            if conn:
                conn.close()

    # 获取未平仓的反向订单数据
    async def get_unclosed_opposite_quantity(
        self, account_id, symbol, direction
    ) -> float:
        """
        获取未平仓反向订单的总数量（quantity总和）
        :param account_id: 账户ID
        :param symbol: 交易对
        :param direction: 目标方向（long/short）
        :return: 总未平仓反向订单的数量（float）
        """
        opposite_direction = "short" if direction == "long" else "long"
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
                cursor.execute(
                    f"""
                    UPDATE {table('orders')}
                    SET is_clopos = 1, updated_at = NOW()
                    WHERE account_id = %s
                    AND symbol = %s 
                    AND pos_side = %s 
                    AND status != 'cancelled'
                    AND is_clopos = 0
                """,
                    (account_id, symbol, direction),
                )
                conn.commit()
        except Exception as e:
            print(f"标记订单为已平仓失败: {e}")
            logging.error(f"标记订单为已平仓失败: {e}")
        finally:
            if conn:
                conn.close()

    # ✅ 【新增方法】获取最近的已成交开仓订单（用于补救网格单）
    async def get_recent_filled_open_order(
        self, account_id: int, symbol: str, minutes_back: int = 30
    ) -> Optional[Dict]:
        """
        获取该币种最近的已成交开仓订单（limit订单）

        用于检测"有持仓但缺网格单"的情况，然后重新触发网格单创建

        Args:
            account_id: 账户ID
            symbol: 交易对
            minutes_back: 查询过去多少分钟内的订单（默认30分钟）

        Returns:
            最近的已成交开仓订单
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                # 注意：如果 updated_at 为 NULL，则使用 created_at 作为备选
                query = f"""
                    SELECT * FROM {table('orders')}
                    WHERE account_id = %s 
                    AND symbol = %s 
                    AND status = 'filled'
                    AND order_type = 'limit'
                    AND (side = 'buy' OR side = 'sell')
                    AND COALESCE(updated_at, created_at, NOW()) > DATE_SUB(NOW(), INTERVAL %s MINUTE)
                    ORDER BY COALESCE(updated_at, created_at) DESC
                    LIMIT 1
                """
                cursor.execute(query, (account_id, symbol, minutes_back))
                result = cursor.fetchone()

                if result:
                    logging.info(
                        f"✅ 找到最近的已成交订单: 账户={account_id}, 币种={symbol}, "
                        f"订单ID={result.get('order_id', 'N/A')[:15]}..., "
                        f"更新时间={result.get('updated_at', result.get('created_at', 'N/A'))}"
                    )
                else:
                    logging.debug(
                        f"📭 无最近的已成交订单: 账户={account_id}, 币种={symbol}"
                    )

                return result
        except Exception as e:
            logging.error(
                f"❌ 查询最近已成交订单失败: 账户={account_id}, 币种={symbol}, 错误={e}",
                exc_info=True,
            )
            return None
        finally:
            if conn:
                conn.close()

    # ✅ 【新增方法】检查是否有特定条件的挂单
    async def has_pending_order(
        self,
        account_id: int,
        symbol: str,
        side: str = None,
        status: str = None,
        include_all: bool = False,
        after_time=None,
    ) -> bool:
        """
        检查是否有特定条件的未成交订单

        Args:
            account_id: 账户ID
            symbol: 交易对
            side: 订单方向 (buy/sell)，为None时不限制
            status: 订单状态 (live/partially_filled)，为None时默认查活跃订单
            include_all: 是否包括已撤销的订单
            after_time: 只查询该时间戳之后创建的订单（用于关联到特定的开仓订单）

        Returns:
            True 表示存在该条件的订单，False 表示不存在
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                query = f"""
                    SELECT COUNT(*) as count FROM {table('orders')}
                    WHERE account_id = %s 
                    AND symbol = %s
                    AND order_type = 'limit'
                """
                params = [account_id, symbol]

                # ✅ 添加时间过滤：只查询开仓订单之后创建的网格单
                if after_time:
                    query += " AND created_at > %s"
                    params.append(after_time)

                if side:
                    query += " AND side = %s"
                    params.append(side)

                if status:
                    # 指定了具体状态
                    query += " AND status = %s"
                    params.append(status)
                elif include_all:
                    # 查所有订单（包括已撤销的）
                    query += " AND status IN ('live', 'partially_filled', 'canceled', 'filled', 'closed')"
                else:
                    # 默认只查活跃订单
                    query += " AND (status = 'live' OR status = 'partially_filled')"

                cursor.execute(query, params)
                result = cursor.fetchone()
                count = result["count"] if result else 0

                exists = count > 0
                if exists and not include_all:
                    logging.debug(
                        f"✅ 找到活跃挂单: 账户={account_id}, 币种={symbol}, "
                        f"方向={side or '任意'}, 状态={status or '活跃'}, 数量={count}"
                    )

                return exists
        except Exception as e:
            logging.error(
                f"❌ 检查挂单失败: 账户={account_id}, 币种={symbol}, 错误={e}",
                exc_info=True,
            )
            return False
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

    async def get_order_by_price_diff(
        self, account_id, symbol, direction, latest_price: float
    ):
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

    async def get_order_by_price_diff_v2(
        self, account_id: int, symbol: str, latest_price: float, mode: str = "sell"
    ) -> Optional[Dict]:
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
                if mode == "sell":
                    # 找价格高于买单成交价的卖单
                    condition = "executed_price > %s"
                    order_side = "sell"
                    sort_order = "executed_price ASC"
                else:
                    # 找价格低于卖单成交价的买单
                    condition = "executed_price < %s"
                    order_side = "buy"
                    sort_order = "executed_price DESC"

                cursor.execute(
                    f"""
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
                """,
                    (account_id, symbol, order_side, latest_price),
                )
                match_order = cursor.fetchone()

                if match_order:
                    logging.debug(
                        f"✅ 查询到配对订单: 账户={account_id}, 币种={symbol}, "
                        f"查找方向={mode}, 订单ID={match_order['order_id'][:15]}..., "
                        f"价格={match_order.get('executed_price', 'N/A')}"
                    )
                else:
                    logging.debug(
                        f"📭 未找到配对订单: 账户={account_id}, 币种={symbol}, "
                        f"查找方向={mode}, 基准价={latest_price}"
                    )

            return match_order
        except Exception as e:
            logging.error(
                f"❌ 查询配对订单失败: 账户={account_id}, 币种={symbol}, "
                f"方向={mode}, 错误={e}",
                exc_info=True,
            )
            return None
        finally:
            if conn:
                conn.close()

    # 生成一个获取币种最大仓位配置数据，获取g_config里面的max_position_list策略字段数据（[{"symbol":"ETH-USDT","value":"1000","tactics":"Y1.1"},{"symbol":"BTC-USDT","value":"1000","tactics":"Q2.4"}]），检索所有配置数据，将对应的策略对应到指定的用户Id 例如：Y1.1：[account_1, account_2]
    async def get_account_max_position(self) -> Optional[Dict]:
        """
        获取指定账户的最大仓位配置数据
        :return: 最大仓位配置数据
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    SELECT a.id as account_id, c.max_position_list as max_position_list
                    FROM {table('accounts')} a
                    INNER JOIN {table('config')} c ON a.id = c.account_id
                    WHERE a.status = %s
                """,
                    (1),
                )
                result = cursor.fetchall()
                if result:
                    tactics_accounts = {}
                    for row in result:
                        account_id = row.get("account_id")
                        max_position_list = row.get("max_position_list")
                        if not max_position_list:
                            continue
                        max_position_list_arr = json.loads(max_position_list)
                        # print(max_position_list_arr)
                        for pos in max_position_list_arr:
                            tactic = pos.get("tactics")
                            if tactic:
                                tactics_accounts.setdefault(tactic, []).append(
                                    account_id
                                )
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

    async def get_tactics_by_account_and_symbol(
        self, account_id: int, symbol: str
    ) -> Optional[str]:
        """
        获取配置表中指定用户和指定币种的max_position_list下面对应的tactics
        :param account_id: 用户ID
        :param symbol: 币种
        :return: 对应的tactics，如果没有则返回None
        """
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    SELECT max_position_list
                    FROM {table('config')}
                    WHERE account_id = %s
                """,
                    (account_id,),
                )
                result = cursor.fetchone()
                if result and result.get("max_position_list"):
                    max_position_list = json.loads(result["max_position_list"])
                    for item in max_position_list:
                        if item.get("symbol") == symbol:
                            tactics = item.get("tactics")
                            logging.debug(
                                f"✅ 找到策略配置: 账户={account_id}, 币种={symbol}, "
                                f"策略={tactics}"
                            )
                            return tactics

                logging.warning(f"⚠️ 未找到策略配置: 账户={account_id}, 币种={symbol}")
                return None
        except Exception as e:
            logging.error(
                f"❌ 获取策略配置失败: 账户={account_id}, 币种={symbol}, 错误={e}",
                exc_info=True,
            )
            return None
        finally:
            if conn:
                conn.close()

    async def get_config_by_account_and_symbol(
        self, account_id: int, symbol: str
    ) -> Optional[Dict]:
        """
        获取配置表中指定用户和指定币种的max_position_list下面对应的配置数据
        :param account_id: 用户ID
        :param symbol: 币种
        :return: 对应的配置数据，如果没有则返回None
        """
        symbol_tactics = symbol
        if symbol.endswith("-SWAP"):
            symbol_tactics = symbol.replace("-SWAP", "")
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    SELECT max_position_list
                    FROM {table('config')}
                    WHERE account_id = %s
                """,
                    (account_id,),
                )
                result = cursor.fetchone()
                if result and result.get("max_position_list"):
                    max_position_list = json.loads(result["max_position_list"])
                    for item in max_position_list:
                        if item.get("symbol") == symbol_tactics:
                            return item
                return None
        except Exception as e:
            print(f"获取配置数据失败: {e}")
            logging.error(f"获取配置数据失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def insert_strategy_trade(self, trade_data: Dict) -> Dict:
        """
        写入策略交易记录到 g_strategy_trade 表
        :param trade_data: 包含交易记录的字典
        :return: 写入结果
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    INSERT INTO {table('strategy_trade')}
                    (strategy_name, open_time, open_side, open_price, close_time, close_side, close_price, loss_profit, symbol, exchange)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                    (
                        trade_data.get("strategy_name"),
                        trade_data.get("open_time"),
                        trade_data.get("open_side"),
                        trade_data.get("open_price"),
                        trade_data.get("close_time"),
                        trade_data.get("close_side"),
                        trade_data.get("close_price"),
                        trade_data.get("loss_profit"),
                        trade_data.get("symbol"),
                        trade_data.get("exchange"),
                    ),
                )
                conn.commit()
                return {
                    "status": "success",
                    "message": "Strategy trade inserted successfully",
                }
        except Exception as e:
            print(f"写入策略交易记录失败: {e}")
            logging.error(f"写入策略交易记录失败: {e}")
            return {"status": "error", "message": str(e)}
        finally:
            if conn:
                conn.close()

    async def get_latest_signal_by_name_and_direction(
        self, name: str, direction: str
    ) -> Optional[Dict]:
        """
        获取g_signals表中指定name和direction的最新一条数据
        :param name: 信号名称
        :param direction: 信号方向（如 'long' 或 'short'）
        :return: 返回最新一条信号数据（dict），如果没有则返回None
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor(pymysql.cursors.DictCursor) as cursor:
                cursor.execute(
                    f"""
                    SELECT * FROM {table('signals')}
                    WHERE name = %s AND direction = %s
                    ORDER BY id DESC LIMIT 1
                """,
                    (name, direction),
                )
                result = cursor.fetchone()
                return result
        except Exception as e:
            print(f"查询信号数据失败: {e}")
            logging.error(f"查询信号数据失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    async def update_signals_trade_by_id(self, sign_id: int, updates: Dict) -> bool:
        """
        根据id更新策略交易记录（g_signals表）
        :param sign_id: 策略交易记录id
        :param updates: 需要更新的字段及其值的字典
        :return: 更新是否成功
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                set_clause = ", ".join([f"{key}=%s" for key in updates.keys()])
                values = list(updates.values()) + [sign_id]
                query = f"""
                    UPDATE {table('signals')}
                    SET {set_clause}
                    WHERE id=%s
                """
                cursor.execute(query, values)
            conn.commit()
            return True
        except Exception as e:
            print(f"更新策略交易记录失败: {e}")
            logging.error(f"更新策略交易记录失败: {e}")
            return False
        finally:
            if conn:
                conn.close()

    # 获取g_config里面所有用户数据，然后根据策略名称进行筛选出对应的max_postion_list里面对应的value值，进行修改，增加5%或者减少5%
    async def update_max_position_by_tactics(
        self,
        tactics_name: str,
        increase: bool = True,
        sign_id: int = 0,
        loss_profit_normal: str = "",
        open_price: str = "",
        stage_profit_loss: float = 0,
    ) -> bool:
        """
        根据策略名称调整所有用户的max_position_list中对应策略的value值，增加或减少5%
        :param tactics_name: 策略名称
        :param increase: True为盈利 减少5%，False为亏损 增加5%
        :return: 是否全部更新成功
        """
        try:
            # print(f"开始批量更新max_position_list，策略名称: {tactics_name}, 增加: {increase}, 关联信号ID: {sign_id}, 亏损: {loss_profit_normal}, 开仓价: {open_price}")
            logging.info(
                f"开始批量更新max_position_list，策略名称: {tactics_name}, 增加: {increase}, 关联信号ID: {sign_id}, 亏损: {loss_profit_normal}, 开仓价: {open_price}"
            )
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                # 获取所有用户的max_position_list
                strategy_info = await self.get_strategy_info(tactics_name)
                max_position = strategy_info.get("max_position")  # 最大仓位
                min_position = strategy_info.get("min_position")  # 最小仓位
                # stage_profit_loss = strategy_info.get('stage_profit_loss', 0) # 阶段性盈亏
                logging.info(
                    f"最大仓位: {max_position}, 最小仓位: {min_position}, 阶段性盈亏: {stage_profit_loss}"
                )

                cursor.execute(
                    f"SELECT account_id, max_position_list FROM {table('config')} AS c INNER JOIN {table('accounts')} AS a ON c.account_id=a.id WHERE a.status = 1"
                )
                configs = cursor.fetchall()
                for row in configs:
                    account_id = (
                        row.get("account_id") if isinstance(row, dict) else row[0]
                    )
                    max_position_list = (
                        row.get("max_position_list")
                        if isinstance(row, dict)
                        else row[1]
                    )
                    if not max_position_list:
                        continue
                    try:
                        max_position_arr = json.loads(max_position_list)
                    except Exception as e:
                        logging.error(f"解析max_position_list失败: {e}")
                        continue
                    updated = False
                    position_cache = -1
                    for item in max_position_arr:
                        # 增减比例
                        # max_position = float(item.get('value')) if item.get('value') else 2000 # 最大仓位
                        # increase_ratio = float(item.get('increase_ratio')) if item.get('increase_ratio') else 5 # 盈利增加比例 5%
                        decrease_ratio = (
                            float(item.get("decrease_ratio"))
                            if item.get("decrease_ratio")
                            else 5
                        )  # 亏损减少比例 5%
                        loss_number = (
                            int(item.get("loss_number"))
                            if item.get("loss_number")
                            else 0
                        )  # 连续亏损次数
                        max_loss_number = (
                            float(item.get("max_loss_number"))
                            if item.get("max_loss_number")
                            else 5
                        )  # 最大亏损次数
                        min_loss_ratio = (
                            float(item.get("min_loss_ratio"))
                            if item.get("min_loss_ratio")
                            else 0.001
                        )  # 最小亏损比例
                        clear_value = (
                            float(item.get("clear_value"))
                            if item.get("clear_value")
                            else max_position
                        )  # 清0值
                        # 2.1 如果C/开仓价的绝对值小于0.1%，不增不减（可配置）。
                        loss_ratio = abs(float(loss_profit_normal)) / float(
                            open_price
                        )  # 亏损/开仓价的绝对值，小于0.1%就认为可以忽略 0.1可配置
                        if loss_ratio < min_loss_ratio:
                            # print(f"账户{account_id}亏损{loss_profit_normal}/开仓价{open_price}的绝对值小于{min_loss_ratio}")
                            logging.info(
                                f"账户{account_id}亏损{loss_profit_normal}/开仓价{open_price}的绝对值小于{min_loss_ratio}"
                            )
                            continue

                        add_loss_number = loss_number + 1
                        if (
                            not increase and add_loss_number > max_loss_number
                        ):  # 如果继续亏损且连续亏损5次，不更新最大仓位
                            # print(f"账户{account_id}连续亏损{add_loss_number}次大于最大仓位{max_loss_number}，不更新最大仓位")
                            logging.info(
                                f"账户{account_id}连续亏损{add_loss_number}次大于最大仓位{max_loss_number}，不更新最大仓位"
                            )
                            continue

                        # logging.info(f"账户{account_id}开始更新max_position_list，策略名称: {tactics_name}, 增加: {increase}, 关联信号ID: {sign_id}, 亏损: {loss_profit_normal}, 开仓价: {open_price}")

                        if (
                            item.get("tactics") == tactics_name
                            and item.get("value") is not None
                            and item.get("value") != ""
                        ):
                            try:
                                value = float(item.get("value"))
                                # logging.info(f"账户{account_id} 当前最大仓位: {value}, 盈利增加比例: {increase_ratio}%, 亏损减少比例: {decrease_ratio}%, 连续亏损次数: {loss_number}, 最大亏损次数: {max_loss_number}, 最小亏损比例: {min_loss_ratio}, 清0值: {clear_value}, 阶段性盈亏: {stage_profit_loss}")
                                if stage_profit_loss == 0 or abs(
                                    float(loss_profit_normal)
                                ) > abs(
                                    stage_profit_loss
                                ):  # 如果阶段盈亏小于等于0或者单次盈亏超过阶段性盈亏绝对值 重置最大仓位
                                    logging.info(
                                        f"账户{account_id}单次盈亏{loss_profit_normal}超过阶段性盈亏{stage_profit_loss:.8f}，重置最大仓位为初始值{clear_value}"
                                    )
                                    value = clear_value
                                    loss_number = 0
                                else:
                                    logging.info(
                                        f"账户{account_id}单次盈亏{loss_profit_normal}未超过阶段性盈亏{stage_profit_loss:.8f}，按规则调整最大仓位"
                                    )
                                    if increase:  # 盈利 减少百分比
                                        logging.info(
                                            f"账户{account_id}盈利，次数保持不变"
                                        )
                                        # value = round(value * (1 - increase_ratio / 100), 8)
                                        # 盈利时次数保持不变
                                        pass
                                    else:  # 亏损 增加百分比
                                        logging.info(
                                            f"账户{account_id}亏损，按比例{decrease_ratio}%增加最大仓位, value值：{value}"
                                        )
                                        value = round(
                                            value * (1 + decrease_ratio / 100), 8
                                        )
                                        loss_number = add_loss_number
                                        logging.info(
                                            f"账户{account_id}亏损，按比例{decrease_ratio}%增加最大仓位, 连续亏损次数：{loss_number}"
                                        )

                                # 仓位最大值不能超过仓位最大仓位数
                                if value > max_position:
                                    value = max_position

                                # 仓位最小值不能低于仓位最小仓位数
                                if value < min_position:
                                    value = min_position
                                item["value"] = value
                                item["loss_number"] = loss_number
                                position_cache = value
                                updated = True
                            except Exception as e:
                                logging.error(f"更新value失败: {e}")
                    if updated:
                        new_max_position_list = json.dumps(
                            max_position_arr, ensure_ascii=False
                        )
                        cursor.execute(
                            f"UPDATE {table('config')} SET max_position_list=%s WHERE account_id=%s",
                            (new_max_position_list, account_id),
                        )
                        logging.info(
                            f"账户{account_id}更新max_position_list成功: {new_max_position_list}"
                        )
                        # 记录仓位变更记录
                        if position_cache != -1:
                            logging.info(
                                f"账户{account_id}记录仓位变更记录: {position_cache}"
                            )
                            await self.record_account_position_change(
                                account_id, position_cache, sign_id
                            )
                conn.commit()
            return True
        except Exception as e:
            print(f"批量更新max_position_list失败: {e}")
            logging.error(f"批量更新max_position_list失败: {e}")
            return False
        finally:
            if conn:
                conn.close()

    # 根据指定账户和策略名称修改对应账户配置数据
    # 暂时未启用
    async def update_max_position_by_account_tactics(
        self,
        account_id: int,
        tactics_name: str,
        increase: bool = True,
        sign_id: int = 0,
    ) -> bool:
        """
        根据策略名称调整指定账户的max_position_list中对应策略的value值，增加或减少5%
        :param account_id: 账户id
        :param tactics_name: 策略名称
        :param increase: True为盈利 减少5%，False为亏损 增加5%
        :param sign_id: 信号ID
        :return: 是否更新成功
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                strategy_info = await self.get_strategy_info(tactics_name)
                if not strategy_info:
                    logging.error(f"未找到策略信息: {tactics_name}")
                    return False
                max_position = strategy_info.get("max_position")
                min_position = strategy_info.get("min_position")

                cursor.execute(
                    f"SELECT account_id, max_position_list FROM {table('config')} WHERE account_id = %s",
                    (account_id,),
                )
                config = cursor.fetchone()
                if not config:
                    logging.error(f"未找到账户配置: {account_id}")
                    return False

                max_position_list = (
                    config.get("max_position_list")
                    if isinstance(config, dict)
                    else config[1]
                )
                if not max_position_list:
                    logging.error(f"账户配置 max_position_list 为空: {account_id}")
                    return False

                try:
                    max_position_arr = json.loads(max_position_list)
                except Exception as e:
                    logging.error(f"解析max_position_list失败: {e}")
                    return False

                updated = False
                position_cache = -1
                for item in max_position_arr:
                    if item.get("tactics") == tactics_name and item.get("value"):
                        try:
                            value = float(item["value"])
                            increase_ratio = float(item.get("increase_ratio", 5))
                            decrease_ratio = float(item.get("decrease_ratio", 5))
                            loss_number = int(item.get("loss_number", 0))
                            if increase:
                                value = round(value * (1 - increase_ratio / 100), 8)
                                loss_number = 0
                            else:
                                value = round(value * (1 + decrease_ratio / 100), 8)
                                loss_number += 1
                            value = min(max(value, min_position), max_position)
                            item["value"] = str(value)
                            item["loss_number"] = loss_number
                            position_cache = value
                            updated = True
                        except Exception as e:
                            logging.error(f"更新value失败: {e}")

                if updated:
                    new_max_position_list = json.dumps(
                        max_position_arr, ensure_ascii=False
                    )
                    cursor.execute(
                        f"UPDATE {table('config')} SET max_position_list=%s WHERE account_id=%s",
                        (new_max_position_list, account_id),
                    )
                    if position_cache != -1:
                        await self.record_account_position_change(
                            account_id, position_cache, sign_id
                        )
                    conn.commit()
                    return True
                else:
                    logging.info(
                        f"未找到匹配策略或未更新: {account_id}, {tactics_name}"
                    )
                    return False
        except Exception as e:
            print(f"更新max_position_list失败: {e}")
            logging.error(f"更新max_position_list失败: {e}")
            return False
        finally:
            if conn:
                conn.close()

    async def get_strategy_info(self, strategy_name: str) -> Optional[Dict]:
        """
        根据策略名称获取策略信息
        :param strategy_name: 策略名称
        :return: 返回策略信息（dict），如果没有则返回None
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"""
                    SELECT * FROM {table('strategy')}
                    WHERE name = %s
                """,
                    (strategy_name,),
                )
                result = cursor.fetchone()
                return result
        except Exception as e:
            print(f"查询策略信息失败: {e}")
            logging.error(f"查询策略信息失败: {e}")
            return None
        finally:
            if conn:
                conn.close()

    # 修改指定策略亏损记录
    async def update_strategy_loss_number(
        self, strategy_name: str, count_profit_loss: float, stage_profit_loss: float
    ) -> bool:
        """
        根据策略名称更新策略亏损次数
        :param strategy_name: 策略名称
        :return: 更新是否成功
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"UPDATE {table('strategy')} SET count_profit_loss=%s, stage_profit_loss=%s, updated_at=%s WHERE name=%s",
                    (
                        count_profit_loss,
                        stage_profit_loss,
                        datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                        strategy_name,
                    ),
                )
                conn.commit()
                return True
        except Exception as e:
            print(f"更新策略亏损次数失败: {e}")
            logging.error(f"更新策略亏损次数失败: {e}")
            return False
        finally:
            if conn:
                conn.close()

    # 记录每个账户仓位变更记录数据到 g_account_histor_position 表，字段包括 account_id、amount、sign_id、datetime
    async def record_account_position_change(
        self, account_id: int, amount: float, sign_id: int
    ) -> bool:
        """
        记录每个账户仓位变更记录数据到 g_account_histor_position 表，字段包括 account_id、amount、sign_id
        :param account_id: 账户ID
        :param amount: 仓位变更金额
        :param sign_id: 信号ID
        :return: 记录是否成功
        """
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    f"INSERT INTO {table('account_histor_position')} (account_id, amount, sign_id, datetime) VALUES (%s, %s, %s, %s)",
                    (
                        account_id,
                        amount,
                        sign_id,
                        datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    ),
                )
                conn.commit()
                return True
        except Exception as e:
            print(f"记录账户仓位变更记录失败: {e}")
            logging.error(f"记录账户仓位变更记录失败: {e}")
            return False
        finally:
            if conn:
                conn.close()
