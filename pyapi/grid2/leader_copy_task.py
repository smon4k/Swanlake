"""
Leader 账户 OKX 成交监听：检测到新 SWAP 成交后写入 g_signals 并发布 Redis。

环境变量（LEADER_COPY_ENABLED=1 时生效）：
  LEADER_COPY_ACCOUNT_ID       — g_accounts 中跟单账户 id（必填，且该账户跳过自动止盈止损）
  LEADER_COPY_SIGNAL_NAME      — 写入信号的 name（策略 tactics，需在跟单账户的 max_position_list 中）
  LEADER_COPY_POLL_INTERVAL_SEC — 轮询间隔，默认 3
  LEADER_COPY_SYMBOLS          — 可选，逗号分隔 OKX instId，如 BTC-USDT-SWAP；留空表示所有 SWAP
  LEADER_COPY_DEDUPE_BY_ORD    — 默认 1：同一 OKX ordId 多笔撮合只发 1 条信号（大单拆单）
  LEADER_COPY_ORD_EMIT_TTL_SEC — Redis ord 去重键 TTL 秒，默认 604800（7 天）
  LOG_LEADER_COPY_PATH         — 独立日志文件，默认 leader_copy.log；每次进程启动会清空该主文件（轮转备份 *.2026-xx-xx 保留）
  REDIS_HOST / REDIS_PORT      — 与主程序一致，默认 localhost:6379
  LEADER_COPY_EXECUTOR_ACCOUNT_IDS — 可选，逗号分隔。实际跟单下单账户 id；若设置，这些账户在网格
      「碎仓清理」（持仓 < 最大仓位 5%）时不撤销条件单（止盈/止损）。可与 LEADER_COPY_ACCOUNT_ID
      分工（监听写信号 vs 执行账户）。
"""

from __future__ import annotations

import asyncio
import json
import logging
import os
from datetime import datetime
from decimal import Decimal
from typing import Any, Dict, List, Optional, Tuple
from zoneinfo import ZoneInfo

import redis
from logging.handlers import TimedRotatingFileHandler

from common_functions import get_exchange

log = logging.getLogger("leader_copy")

DEFAULT_PLATFORM_CLORD_PREFIX = "Zx"


def skip_stop_loss_grid_for_account(account_id: int) -> bool:
    """
    判断账户是否应跳过自动止盈止损。
    约定：LEADER_COPY_ENABLED=1 且 account_id == LEADER_COPY_ACCOUNT_ID 时跳过。
    """
    if os.getenv("LEADER_COPY_ENABLED", "0") != "1":
        return False
    try:
        target_account_id = int(os.getenv("LEADER_COPY_ACCOUNT_ID", "0"))
    except ValueError:
        return False
    return target_account_id > 0 and account_id == target_account_id


def leader_copy_preserve_conditional_on_grid_small_cleanup(account_id: int) -> bool:
    """
    网格 manage_grid_orders 中「持仓 < max_position*5%」清仓分支里，是否保留条件单（止盈/止损）。
    True：调用方应传 cancel_all_orders(..., cancel_conditional=False)。
    """
    if os.getenv("LEADER_COPY_ENABLED", "0") != "1":
        return False
    raw = (os.getenv("LEADER_COPY_EXECUTOR_ACCOUNT_IDS") or "").strip()
    if raw:
        for part in raw.split(","):
            p = part.strip()
            if p.isdigit() and int(p) == account_id:
                return True
        return False
    try:
        aid = int(os.getenv("LEADER_COPY_ACCOUNT_ID", "0"))
    except ValueError:
        return False
    return aid > 0 and account_id == aid


def setup_leader_copy_logging() -> None:
    """独立文件日志，不写入根 logger 的 bot.log。进程启动时清空当前主日志文件（与 main.py 的 bot.log 一致）。"""
    if log.handlers:
        return
    log.setLevel(logging.INFO)
    path = os.getenv("LOG_LEADER_COPY_PATH", "leader_copy.log")
    if os.path.exists(path):
        with open(path, "w", encoding="utf-8") as f:
            f.truncate(0)
    handler = TimedRotatingFileHandler(
        filename=path,
        when="midnight",
        interval=1,
        backupCount=7,
        encoding="utf-8",
    )
    handler.setFormatter(
        logging.Formatter("%(asctime)s - %(levelname)s - %(message)s")
    )
    log.addHandler(handler)
    log.propagate = False


def _parse_bill_id(bill_id: Optional[str]) -> int:
    if not bill_id:
        return 0
    try:
        return int(bill_id)
    except ValueError:
        return 0


def _trade_cursor_tuple(trade: Dict[str, Any]) -> Tuple[int, int]:
    info = trade.get("info") or {}
    ts = int(trade.get("timestamp") or 0)
    bid = _parse_bill_id(info.get("billId"))
    return ts, bid


def _cursor_after(
    a_ts: int, a_bid: int, b_ts: int, b_bid: int
) -> bool:
    """a 是否严格晚于 b（用于去重与顺序）。"""
    if a_ts > b_ts:
        return True
    if a_ts < b_ts:
        return False
    return a_bid > b_bid


def map_okx_swap_fill_to_signal(trade: Dict[str, Any]) -> Optional[Tuple[str, int]]:
    """
    双向持仓 hedge：posSide long/short + side 映射为 (direction, size)。
    size: 1 开多, -1 开空, 0 平仓；与 signal_processing_task.process_signal 一致。
    """
    info = trade.get("info") or {}
    if info.get("instType") != "SWAP":
        return None
    exec_type = info.get("execType") or ""
    if exec_type not in ("T", "M"):
        return None
    pos_side = (info.get("posSide") or "net").lower()
    side = (trade.get("side") or "").lower()
    if pos_side == "long":
        if side == "buy":
            return ("long", 1)
        if side == "sell":
            return ("long", 0)
    elif pos_side == "short":
        if side == "sell":
            return ("short", -1)
        if side == "buy":
            return ("short", 0)
    log.debug(
        "LeaderCopyTask.map_okx_swap_fill_to_signal skip: posSide=%s side=%s instId=%s",
        pos_side,
        side,
        info.get("instId"),
    )
    return None


class LeaderCopyTask:
    """监听 leader OKX 成交，写入信号表并唤醒 signal_channel。"""

    def __init__(self, bot: Any) -> None:
        self.bot = bot
        self.db = bot.db
        self.api_limiter = bot.api_limiter
        self.leader_account_id = int(os.getenv("LEADER_COPY_ACCOUNT_ID", "0"))
        self.signal_name = (os.getenv("LEADER_COPY_SIGNAL_NAME") or "").strip()
        self.poll_interval = float(os.getenv("LEADER_COPY_POLL_INTERVAL_SEC", "3"))
        raw_syms = (os.getenv("LEADER_COPY_SYMBOLS") or "").strip()
        self.symbol_filter: Optional[set] = None
        if raw_syms:
            self.symbol_filter = {s.strip() for s in raw_syms.split(",") if s.strip()}
        self.redis = redis.Redis(
            host=os.getenv("REDIS_HOST", "localhost"),
            port=int(os.getenv("REDIS_PORT", "6379")),
            decode_responses=True,
        )
        self._cursor_key = f"grid2:leader_copy:last:{self.leader_account_id}"
        self._exchange = None
        self._clord_prefix = (
            os.getenv("LEADER_COPY_PLATFORM_CLORD_PREFIX", DEFAULT_PLATFORM_CLORD_PREFIX)
            or DEFAULT_PLATFORM_CLORD_PREFIX
        ).strip()
        self._skip_if_order_exists = os.getenv("LEADER_COPY_SKIP_IF_ORDER_EXISTS", "1") == "1"
        self._dedupe_by_ord = os.getenv("LEADER_COPY_DEDUPE_BY_ORD", "1") == "1"
        self._ord_emit_ttl = int(
            os.getenv("LEADER_COPY_ORD_EMIT_TTL_SEC", str(7 * 24 * 3600))
        )

    def _try_claim_ord_emit(self, ord_id: str) -> bool:
        """
        同一订单多笔成交共享 ordId：仅第一次返回 True。无 ordId 时返回 True，仍靠 bill 唯一键防重。
        """
        if not self._dedupe_by_ord or not ord_id:
            return True
        key = f"grid2:leader_copy:ord_emit:{self.leader_account_id}:{ord_id}"
        return bool(self.redis.set(key, "1", nx=True, ex=self._ord_emit_ttl))

    async def _is_platform_order(self, trade: Dict[str, Any]) -> bool:
        """
        判断该成交是否为“平台自身下单”产生的成交：
        - 优先按 clOrdId 前缀（默认 Zx）判断（我们下单会生成该前缀）
        - 其次按 ordId 是否已存在于 g_orders(account_id, order_id)
        """
        info = trade.get("info") or {}
        cl_ord_id = str(info.get("clOrdId") or "").strip()
        if cl_ord_id and self._clord_prefix and cl_ord_id.startswith(self._clord_prefix):
            return True

        if not self._skip_if_order_exists:
            return False

        ord_id = str(trade.get("order") or info.get("ordId") or "").strip()
        if not ord_id:
            return False

        try:
            existed = await self.db.get_order_by_id(self.leader_account_id, ord_id)
            return existed is not None
        except Exception as e:
            log.error(
                "LeaderCopyTask._is_platform_order query g_orders failed: account_id=%s ord_id=%s err=%s",
                self.leader_account_id,
                ord_id,
                e,
                exc_info=True,
            )
            return False

    def _load_cursor(self) -> Optional[Dict[str, Any]]:
        raw = self.redis.get(self._cursor_key)
        if not raw:
            return None
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            log.error("LeaderCopyTask._load_cursor invalid json: %s", raw)
            return None

    def _save_cursor(self, ts: int, bill_id: str) -> None:
        self.redis.set(self._cursor_key, json.dumps({"ts": ts, "bill_id": bill_id}))

    async def _get_exchange(self):
        if self._exchange is not None:
            return self._exchange
        ex = await get_exchange(self.bot, self.leader_account_id)
        self._exchange = ex
        return ex

    async def _reset_exchange(self) -> None:
        if self._exchange is not None:
            try:
                await self._exchange.close()
            except Exception as e:
                log.warning("LeaderCopyTask._reset_exchange close err: %s", e)
        self._exchange = None

    def _filter_trade(self, trade: Dict[str, Any]) -> bool:
        info = trade.get("info") or {}
        inst_id = info.get("instId") or ""
        if self.symbol_filter is not None and inst_id not in self.symbol_filter:
            return False
        return True

    async def _fetch_trades(self, exchange, since_ms: Optional[int]) -> List[Dict[str, Any]]:
        if self.api_limiter:
            await self.api_limiter.check_and_wait()
        params: Dict[str, Any] = {}
        if since_ms is not None:
            params["paginate"] = False
        trades = await exchange.fetch_my_trades(
            symbol=None,
            since=since_ms,
            limit=100,
            params=params,
        )
        return trades or []

    async def _emit_signal(
        self,
        inst_id: str,
        direction: str,
        size: int,
        price: Decimal,
        *,
        leader_bill_id: str,
        leader_ord_id: Optional[str],
    ) -> None:
        timestamp = datetime.now(ZoneInfo("Asia/Shanghai")).strftime(
            "%Y-%m-%d %H:%M:%S"
        )
        result = await self.db.insert_signal(
            {
                "name": self.signal_name,
                "timestamp": timestamp,
                "symbol": inst_id,
                "direction": direction,
                "price": price,
                "size": size,
                "status": "pending",
                "signal_source": "leader_copy",
                "leader_account_id": self.leader_account_id,
                "leader_bill_id": leader_bill_id,
                "leader_ord_id": leader_ord_id,
            }
        )
        if result.get("status") != "success":
            log.error(
                "LeaderCopyTask._emit_signal insert_signal failed: %s", result
            )
            return
        if result.get("duplicate"):
            log.info(
                "LeaderCopyTask 重复 bill 已存在，跳过 Redis: bill_id=%s",
                leader_bill_id,
            )
            return
        self.redis.publish("signal_channel", "new_signal")
        log.info(
            "LeaderCopyTask 已写信号 name=%s symbol=%s direction=%s size=%s price=%s bill=%s",
            self.signal_name,
            inst_id,
            direction,
            size,
            price,
            leader_bill_id,
        )

    async def _poll_once(self) -> None:
        cursor = self._load_cursor()
        since_ms: Optional[int] = None
        if cursor and cursor.get("ts"):
            since_ms = max(0, int(cursor["ts"]) - 2000)

        exchange = await self._get_exchange()
        if not exchange:
            log.warning(
                "LeaderCopyTask 无法创建交易所实例 account_id=%s",
                self.leader_account_id,
            )
            return

        try:
            trades = await self._fetch_trades(exchange, since_ms)
        except Exception as e:
            log.error(
                "LeaderCopyTask fetch_my_trades failed: %s", e, exc_info=True
            )
            await self._reset_exchange()
            return

        trades = [t for t in trades if self._filter_trade(t)]
        trades.sort(key=_trade_cursor_tuple)

        if not trades:
            return

        cur_ts = int(cursor["ts"]) if cursor and cursor.get("ts") else None
        cur_bid_str = str(cursor.get("bill_id") or "0") if cursor else "0"
        cur_bid = _parse_bill_id(cur_bid_str)

        if cursor is None:
            last = trades[-1]
            ts, bid = _trade_cursor_tuple(last)
            info = last.get("info") or {}
            bill = str(info.get("billId") or "")
            self._save_cursor(ts, bill or str(bid))
            log.info(
                "LeaderCopyTask 首次启动：已对齐游标 ts=%s billId=%s，不回放历史信号",
                ts,
                bill,
            )
            return

        for trade in trades:
            t_ts, t_bid = _trade_cursor_tuple(trade)
            if not _cursor_after(t_ts, t_bid, cur_ts or 0, cur_bid):
                continue
            mapped = map_okx_swap_fill_to_signal(trade)
            info = trade.get("info") or {}
            if not mapped:
                cur_ts, cur_bid = t_ts, t_bid
                bill = str(info.get("billId") or t_bid)
                self._save_cursor(cur_ts, bill)
                continue
            direction, size = mapped
            inst_id = info.get("instId") or ""
            price_raw = trade.get("price")
            try:
                price = Decimal(str(price_raw or "0"))
            except Exception:
                price = Decimal("0")

            if await self._is_platform_order(trade):
                log.info(
                    "LeaderCopyTask 跳过平台自有成交: account_id=%s instId=%s ordId=%s billId=%s",
                    self.leader_account_id,
                    inst_id,
                    trade.get("order") or info.get("ordId"),
                    info.get("billId"),
                )
            else:
                bill_key = str(info.get("billId") or "").strip() or str(t_bid)
                ord_raw = trade.get("order") or info.get("ordId")
                ord_key = str(ord_raw).strip() if ord_raw is not None else ""
                if not self._try_claim_ord_emit(ord_key):
                    log.info(
                        "LeaderCopyTask 同订单多笔成交，按 ordId 去重跳过写信号: ordId=%s billId=%s price=%s",
                        ord_key or "(empty)",
                        bill_key,
                        price,
                    )
                else:
                    await self._emit_signal(
                        inst_id,
                        direction,
                        size,
                        price,
                        leader_bill_id=bill_key,
                        leader_ord_id=ord_key or None,
                    )
            cur_ts, cur_bid = t_ts, t_bid
            bill = str(info.get("billId") or t_bid)
            self._save_cursor(cur_ts, bill)

    async def leader_copy_loop(self) -> None:
        log.info(
            "LeaderCopyTask 启动 account_id=%s signal_name=%s interval=%ss",
            self.leader_account_id,
            self.signal_name,
            self.poll_interval,
        )
        while True:
            try:
                await self._poll_once()
            except asyncio.CancelledError:
                raise
            except Exception as e:
                log.error("LeaderCopyTask.leader_copy_loop err: %s", e, exc_info=True)
            await asyncio.sleep(self.poll_interval)


def validate_leader_copy_env() -> Optional[str]:
    """返回错误信息；None 表示校验通过。"""
    if os.getenv("LEADER_COPY_ENABLED", "0") != "1":
        return None
    aid = int(os.getenv("LEADER_COPY_ACCOUNT_ID", "0"))
    if aid <= 0:
        return "LEADER_COPY_ACCOUNT_ID 无效"
    name = (os.getenv("LEADER_COPY_SIGNAL_NAME") or "").strip()
    if not name:
        return "LEADER_COPY_SIGNAL_NAME 未设置"
    return None
