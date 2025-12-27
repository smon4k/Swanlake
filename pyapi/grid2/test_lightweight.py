#!/usr/bin/env python3
"""
轻量级并发信号测试 - 适用于4个真实账户
只测试核心流程，不大量开仓
"""

import asyncio
import pymysql
import redis
import logging
import os
from datetime import datetime
from decimal import Decimal
from dotenv import load_dotenv
from zoneinfo import ZoneInfo

load_dotenv()

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[
        logging.FileHandler("test_lightweight.log", encoding="utf-8"),
        logging.StreamHandler(),
    ],
)


class LightweightTester:
    def __init__(self):
        self.db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "port": int(os.getenv("DB_PORT", 3306)),
            "user": os.getenv("DB_USER", "root"),
            "password": os.getenv("DB_PASSWORD", "123456"),
            "database": os.getenv("DB_NAME", "trading_bot"),
            "charset": "utf8mb4",
            "cursorclass": pymysql.cursors.DictCursor,
        }
        self.redis_client = redis.Redis(
            host=os.getenv("REDIS_HOST", "localhost"),
            port=int(os.getenv("REDIS_PORT", 6379)),
            decode_responses=True,
        )
        self.test_signal_ids = []

    def get_db_connection(self):
        return pymysql.connect(**self.db_config)

    async def get_available_accounts(self):
        """获取可用账户（最多4个）"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute("SELECT id FROM g_accounts WHERE status = 1 LIMIT 10")
                accounts = cursor.fetchall()
                account_ids = [acc["id"] for acc in accounts]
                logging.info(f"📋 找到 {len(account_ids)} 个可用账户: {account_ids}")
                return account_ids
        finally:
            if conn:
                conn.close()

    async def insert_test_signal(self, name, direction, size, price=95000.00):
        """插入测试信号"""
        conn = None
        try:
            conn = self.get_db_connection()
            timestamp = datetime.now(ZoneInfo("Asia/Shanghai")).strftime(
                "%Y-%m-%d %H:%M:%S"
            )
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    INSERT INTO g_signals 
                    (name, timestamp, symbol, direction, price, size, status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    """,
                    (
                        name,
                        timestamp,
                        "BTC-USDT-SWAP",
                        direction,
                        Decimal(str(price)),
                        size,
                        "pending",
                    ),
                )
                signal_id = cursor.lastrowid
                conn.commit()
                logging.info(f"✅ 插入信号 {signal_id}: {name} {direction} size={size}")
                self.test_signal_ids.append(signal_id)
                return signal_id
        finally:
            if conn:
                conn.close()

    async def trigger_redis(self):
        """触发Redis消息"""
        self.redis_client.publish("signal_channel", "new_signal")
        logging.info("📢 已触发信号处理")

    async def check_signal_result(self, signal_id):
        """检查信号处理结果"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT id, name, status, success_accounts, failed_accounts "
                    "FROM g_signals WHERE id = %s",
                    (signal_id,),
                )
                return cursor.fetchone()
        finally:
            if conn:
                conn.close()

    async def check_stop_loss_orders(self, account_id, symbol):
        """检查止损单"""
        conn = None
        try:
            conn = self.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, account_id, symbol, order_type, status, timestamp
                    FROM g_orders
                    WHERE account_id = %s AND symbol = %s 
                    AND order_type = 'stop_loss'
                    AND status IN ('effective', 'pending')
                    AND timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    ORDER BY timestamp DESC
                    LIMIT 1
                    """,
                    (account_id, symbol),
                )
                return cursor.fetchone()
        finally:
            if conn:
                conn.close()

    async def test_concurrent_signals(self):
        """测试并发信号"""
        logging.info("\n" + "=" * 60)
        logging.info("【测试】并发信号处理（4账户版本）")
        logging.info("=" * 60)

        accounts = await self.get_available_accounts()
        if len(accounts) < 2:
            logging.error("❌ 至少需要2个账户才能测试")
            return

        logging.info(f"\n📊 将使用 {len(accounts)} 个账户进行测试")
        logging.info(f"  账户列表: {accounts}")

        # 插入2个并发信号（开仓）
        logging.info("\n" + "=" * 60)
        logging.info("▶️ 步骤1: 插入开仓信号")
        logging.info("=" * 60)
        # 使用线上实际策略（从数据库查询现有策略）
        signal1 = await self.insert_test_signal("T1.1", "long", 1, 88500)
        signal2 = await self.insert_test_signal("T1.0", "long", 1, 88500)

        await self.trigger_redis()

        # 等待处理
        logging.info("\n⏳ 等待40秒，观察开仓和止损单创建...")
        await asyncio.sleep(40)

        # 检查开仓结果
        logging.info("\n" + "=" * 60)
        logging.info("▶️ 步骤2: 检查开仓结果")
        logging.info("=" * 60)
        for sid in [signal1, signal2]:
            result = await self.check_signal_result(sid)
            if result:
                logging.info(
                    f"  信号 {sid} ({result['name']}): "
                    f"状态={result['status']}, "
                    f"成功账户={result.get('success_accounts', 'N/A')}, "
                    f"失败账户={result.get('failed_accounts', 'N/A')}"
                )

        # 检查止损单
        logging.info("\n" + "=" * 60)
        logging.info("▶️ 步骤3: 检查止损单创建")
        logging.info("=" * 60)
        for account_id in accounts[:2]:  # 检查前2个账户
            stop_loss = await self.check_stop_loss_orders(account_id, "BTC-USDT-SWAP")
            if stop_loss:
                logging.info(
                    f"  ✅ 账户 {account_id}: 止损单已创建 "
                    f"(ID={stop_loss['id']}, 状态={stop_loss['status']}, "
                    f"时间={stop_loss['timestamp']})"
                )
            else:
                logging.warning(f"  ⚠️ 账户 {account_id}: 未找到止损单")

        # 等待一段时间
        logging.info("\n⏳ 等待60秒后执行平仓...")
        await asyncio.sleep(60)

        # 插入平仓信号
        logging.info("\n" + "=" * 60)
        logging.info("▶️ 步骤4: 插入平仓信号")
        logging.info("=" * 60)
        signal3 = await self.insert_test_signal("T1.1", "short", 0, 88500)
        signal4 = await self.insert_test_signal("T1.0", "short", 0, 88500)

        await self.trigger_redis()

        # 等待平仓
        logging.info("\n⏳ 等待40秒，观察平仓...")
        await asyncio.sleep(40)

        # 最终结果
        logging.info("\n" + "=" * 60)
        logging.info("▶️ 步骤5: 最终结果汇总")
        logging.info("=" * 60)

        success_count = 0
        failed_count = 0

        for sid in self.test_signal_ids:
            result = await self.check_signal_result(sid)
            if result:
                status = result["status"]
                name = result["name"]
                success_accs = result.get("success_accounts", "[]")
                failed_accs = result.get("failed_accounts", "[]")

                if status == "processed":
                    success_count += 1
                    logging.info(f"  ✅ 信号 {sid} ({name}): 状态={status}")
                else:
                    failed_count += 1
                    logging.warning(
                        f"  ⚠️ 信号 {sid} ({name}): 状态={status}, "
                        f"失败账户={failed_accs}"
                    )

        logging.info("\n" + "=" * 60)
        logging.info("【测试总结】")
        logging.info("=" * 60)
        logging.info(f"  总信号数: {len(self.test_signal_ids)}")
        logging.info(f"  成功(processed): {success_count}")
        logging.info(f"  失败/处理中: {failed_count}")
        logging.info("\n📄 详细日志文件:")
        logging.info("  - test_lightweight.log (本测试日志)")
        logging.info("  - log/info.log (主程序日志)")
        logging.info("\n✅ 测试完成！")


async def main():
    tester = LightweightTester()
    await tester.test_concurrent_signals()


if __name__ == "__main__":
    asyncio.run(main())
