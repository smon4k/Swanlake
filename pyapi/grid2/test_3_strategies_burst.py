#!/usr/bin/env python3
"""
三策略并发开仓信号压测脚本
用途：
1) 一次性插入3条 pending 信号（3个策略）
2) 只发布一次 Redis 唤醒消息
3) 模拟“多策略同时进来”的场景
"""

import asyncio
import argparse
import logging
import os
from datetime import datetime
from decimal import Decimal
from zoneinfo import ZoneInfo
from pathlib import Path

import pymysql
import redis
from dotenv import load_dotenv

# 固定读取脚本同目录下的 .env，避免从其他目录执行时读取失败
BASE_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=BASE_DIR / ".env")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
)

DB_CONFIG = {
    "host": os.getenv("DB_HOST", "localhost"),
    "port": int(os.getenv("DB_PORT", 3306)),
    "user": os.getenv("DB_USER", "root"),
    "password": os.getenv("DB_PASSWORD", "123456"),
    # 与主程序保持一致：优先 DB_DATABASE，兼容 DB_NAME
    "database": os.getenv("DB_DATABASE") or os.getenv("DB_NAME", "trading_bot"),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
}

REDIS_CLIENT = redis.Redis(
    host=os.getenv("REDIS_HOST", "localhost"),
    port=int(os.getenv("REDIS_PORT", 6379)),
    decode_responses=True,
)


def get_conn():
    return pymysql.connect(**DB_CONFIG)


def insert_signal(
    name, direction="long", size=1, price=90000.0, symbol="BTC-USDT-SWAP"
):
    conn = get_conn()
    try:
        ts = datetime.now(ZoneInfo("Asia/Shanghai")).strftime("%Y-%m-%d %H:%M:%S")
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO g_signals
                (name, timestamp, symbol, direction, price, size, status)
                VALUES (%s, %s, %s, %s, %s, %s, 'pending')
                """,
                (name, ts, symbol, direction, Decimal(str(price)), size),
            )
            conn.commit()
            return cur.lastrowid
    finally:
        conn.close()


def validate_strategy_exists(strategy_name: str) -> bool:
    conn = get_conn()
    try:
        with conn.cursor() as cur:
            # 用 g_signals 做轻量存在性检查（如果你有策略配置表，可改成查配置表）
            cur.execute(
                "SELECT 1 FROM g_signals WHERE name=%s LIMIT 1",
                (strategy_name,),
            )
            return cur.fetchone() is not None
    finally:
        conn.close()


def parse_args():
    parser = argparse.ArgumentParser(description="三策略并发信号注入脚本")
    parser.add_argument(
        "--strategies",
        nargs="+",
        default=["T1.0", "T1.1", "T2.0"],
        help="策略名列表，默认: T1.0 T1.1 T2.0",
    )
    parser.add_argument(
        "--side",
        choices=["buy", "sell"],
        default="buy",
        help="交易方向：buy=做多，sell=做空（仅用于推导direction默认值）",
    )
    parser.add_argument(
        "--size",
        type=int,
        choices=[1, -1, 0],
        default=1,
        help="信号 size：1=开多，-1=开空，0=平仓",
    )
    parser.add_argument(
        "--direction",
        choices=["long", "short"],
        default=None,
        help="可显式指定 direction；不传时自动根据 side 推导",
    )
    parser.add_argument(
        "--price",
        type=float,
        default=90000.0,
        help="信号价格，默认 90000.0",
    )
    parser.add_argument(
        "--symbol",
        default="BTC-USDT-SWAP",
        help="交易对，默认 BTC-USDT-SWAP",
    )
    return parser.parse_args()


async def main():
    args = parse_args()
    strategies = args.strategies
    direction = args.direction if args.direction else ("long" if args.side == "buy" else "short")

    # 可选：策略存在性提示（不强制）
    for s in strategies:
        if not validate_strategy_exists(s):
            logging.warning(f"⚠️ 策略名可能不存在历史记录: {s}（请确认已配置账号映射）")

    signal_ids = []
    for s in strategies:
        sid = insert_signal(
            name=s,
            direction=direction,
            size=args.size,
            price=args.price,
            symbol=args.symbol,
        )
        signal_ids.append(sid)
        logging.info(
            f"✅ 插入信号: id={sid}, strategy={s}, side={args.side}, "
            f"direction={direction}, size={args.size}, price={args.price}, symbol={args.symbol}"
        )

    # 关键：只 publish 一次，触发一次批量拉取并发处理
    REDIS_CLIENT.publish("signal_channel", "new_signal")
    logging.info("📢 已发布 Redis 唤醒: signal_channel -> new_signal")
    logging.info(f"🎯 本次并发信号ID: {signal_ids}")


if __name__ == "__main__":
    asyncio.run(main())
