#!/usr/bin/env python3
import argparse
import re
from datetime import datetime, timedelta
from statistics import mean


TIME_FMT = "%Y-%m-%d %H:%M:%S,%f"


def parse_time(line: str):
    m = re.match(r"^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2},\d{3})", line)
    if not m:
        return None
    return datetime.strptime(m.group(1), TIME_FMT)


def percentile(values, p):
    if not values:
        return None
    if len(values) == 1:
        return values[0]
    values = sorted(values)
    k = (len(values) - 1) * p
    f = int(k)
    c = min(f + 1, len(values) - 1)
    if f == c:
        return values[f]
    d0 = values[f] * (c - k)
    d1 = values[c] * (k - f)
    return d0 + d1


def main():
    parser = argparse.ArgumentParser(description="Analyze Swanlake signal log latency and errors.")
    parser.add_argument("log_path", help="Path to info.log")
    parser.add_argument(
        "--last-minutes",
        type=int,
        default=None,
        help="Only analyze logs within the last N minutes (based on max timestamp in file)",
    )
    parser.add_argument(
        "--max-submit-p95",
        type=float,
        default=15.0,
        help="Pass threshold: recv_to_submit_success p95 must be <= this value (seconds)",
    )
    parser.add_argument(
        "--max-processed-p95",
        type=float,
        default=20.0,
        help="Pass threshold: recv_to_signal_processed p95 must be <= this value (seconds)",
    )
    parser.add_argument(
        "--max-rate-limit",
        type=int,
        default=0,
        help="Pass threshold: rate_limit_50011 count must be <= this value",
    )
    parser.add_argument(
        "--max-duplicate",
        type=int,
        default=0,
        help="Pass threshold: duplicate_51016 count must be <= this value",
    )
    parser.add_argument(
        "--max-timeout",
        type=int,
        default=0,
        help="Pass threshold: order_timeout count must be <= this value",
    )
    parser.add_argument(
        "--max-open-fail",
        type=int,
        default=0,
        help="Pass threshold: open_fail count must be <= this value",
    )
    parser.add_argument(
        "--min-signals",
        type=int,
        default=1,
        help="Pass threshold: signal_received_events must be >= this value",
    )
    parser.add_argument(
        "--max-cleanup-p95",
        type=float,
        default=8.0,
        help="Pass threshold: pre-cleanup stage p95 <= this value (seconds)",
    )
    parser.add_argument(
        "--max-cancel-p95",
        type=float,
        default=10.0,
        help="Pass threshold: cancel stage cumulative p95 <= this value (seconds)",
    )
    parser.add_argument(
        "--max-financing-p95",
        type=float,
        default=10.0,
        help="Pass threshold: financing stage cumulative p95 <= this value (seconds)",
    )
    parser.add_argument(
        "--max-open-total-p95",
        type=float,
        default=18.0,
        help="Pass threshold: account open total p95 <= this value (seconds)",
    )
    args = parser.parse_args()

    recv_events = []
    submit_success_events = []
    signal_done_events = []

    counts = {
        "rate_limit_50011": 0,
        "duplicate_51016": 0,
        "order_not_exist_51603": 0,
        "order_timeout": 0,
        "lock_timeout": 0,
        "open_fail": 0,
        "processed": 0,
        "api_warning_zone": 0,
        "api_danger_zone": 0,
        "signal_pause_count": 0,
    }

    stage_cleanup = []
    stage_cancel_cum = []
    stage_financing_cum = []
    stage_open_total = []

    log_rows = []
    with open(args.log_path, "r", encoding="utf-8") as f:
        for raw in f:
            line = raw.strip()
            t = parse_time(line)
            if not t:
                continue
            log_rows.append((t, line))

    if args.last_minutes is not None and log_rows:
        max_ts = max(t for t, _ in log_rows)
        cutoff = max_ts - timedelta(minutes=args.last_minutes)
        log_rows = [(t, line) for t, line in log_rows if t >= cutoff]

    for t, line in log_rows:
        if "📩 收到通知" in line:
            recv_events.append(t)
        if "✅ 下单成功:" in line:
            submit_success_events.append(t)
        if "✅ 信号 " in line and "状态改为 processed" in line:
            signal_done_events.append(t)

        if "50011" in line or "Too Many Requests" in line:
            counts["rate_limit_50011"] += 1
        if "51016" in line or "Client order ID already exists" in line:
            counts["duplicate_51016"] += 1
        if "51603" in line or "Order does not exist" in line:
            counts["order_not_exist_51603"] += 1
        if "下单超时" in line:
            counts["order_timeout"] += 1
        if "锁获取超时" in line:
            counts["lock_timeout"] += 1
        if "❌ 开仓失败" in line or "交易所开仓失败" in line:
            counts["open_fail"] += 1
        if "状态改为 processed" in line:
            counts["processed"] += 1
        if "API 警告区" in line:
            counts["api_warning_zone"] += 1
        if "API 危险区" in line:
            counts["api_danger_zone"] += 1
        if "信号处理优先级高于价格监控，暂停2秒" in line:
            counts["signal_pause_count"] += 1

        m = re.search(r"前置平反向仓耗时:\s*([0-9.]+)秒", line)
        if m:
            stage_cleanup.append(float(m.group(1)))
        m = re.search(r"撤单完成累计耗时:\s*([0-9.]+)秒", line)
        if m:
            stage_cancel_cum.append(float(m.group(1)))
        m = re.search(r"理财处理完成累计耗时:\s*([0-9.]+)秒", line)
        if m:
            stage_financing_cum.append(float(m.group(1)))
        m = re.search(r"开仓处理完成,\s*耗时\s*([0-9.]+)\s*秒", line)
        if m:
            stage_open_total.append(float(m.group(1)))

    # Pairing strategy:
    # For each receive event, find the next submit success / signal done event in chronological order.
    def pair_deltas(starts, ends):
        deltas = []
        j = 0
        for s in starts:
            while j < len(ends) and ends[j] < s:
                j += 1
            if j < len(ends):
                deltas.append((ends[j] - s).total_seconds())
                j += 1
        return deltas

    recv_to_submit = pair_deltas(recv_events, submit_success_events)
    recv_to_done = pair_deltas(recv_events, signal_done_events)

    print("=== Signal Log Analysis ===")
    print(f"log: {args.log_path}")
    if args.last_minutes is not None:
        print(f"window: last {args.last_minutes} minute(s)")
    print(f"signal_received_events: {len(recv_events)}")
    print(f"order_submit_success_events: {len(submit_success_events)}")
    print(f"signal_processed_events: {len(signal_done_events)}")
    print("")

    def print_latency(name, arr):
        if not arr:
            print(f"{name}: no data")
            return
        print(
            f"{name}: count={len(arr)}, avg={mean(arr):.2f}s, "
            f"p50={percentile(arr, 0.50):.2f}s, p95={percentile(arr, 0.95):.2f}s, max={max(arr):.2f}s"
        )

    print_latency("recv_to_submit_success", recv_to_submit)
    print_latency("recv_to_signal_processed", recv_to_done)
    print("")
    print_latency("stage_cleanup_pre_close", stage_cleanup)
    print_latency("stage_cancel_cumulative", stage_cancel_cum)
    print_latency("stage_financing_cumulative", stage_financing_cum)
    print_latency("stage_account_open_total", stage_open_total)
    print("")
    print("errors:")
    for k, v in counts.items():
        print(f"  {k}: {v}")

    # --------------------------
    # PASS / FAIL gate
    # --------------------------
    failures = []
    submit_p95 = percentile(recv_to_submit, 0.95) if recv_to_submit else None
    processed_p95 = percentile(recv_to_done, 0.95) if recv_to_done else None

    if len(recv_events) < args.min_signals:
        failures.append(
            f"signal_received_events={len(recv_events)} < min_signals={args.min_signals}"
        )
    if submit_p95 is None:
        failures.append("recv_to_submit_success 没有可用数据")
    elif submit_p95 > args.max_submit_p95:
        failures.append(
            f"recv_to_submit_success p95={submit_p95:.2f}s > {args.max_submit_p95:.2f}s"
        )

    if processed_p95 is None:
        failures.append("recv_to_signal_processed 没有可用数据")
    elif processed_p95 > args.max_processed_p95:
        failures.append(
            f"recv_to_signal_processed p95={processed_p95:.2f}s > {args.max_processed_p95:.2f}s"
        )

    if counts["rate_limit_50011"] > args.max_rate_limit:
        failures.append(
            f"rate_limit_50011={counts['rate_limit_50011']} > {args.max_rate_limit}"
        )
    if counts["duplicate_51016"] > args.max_duplicate:
        failures.append(
            f"duplicate_51016={counts['duplicate_51016']} > {args.max_duplicate}"
        )
    if counts["order_timeout"] > args.max_timeout:
        failures.append(
            f"order_timeout={counts['order_timeout']} > {args.max_timeout}"
        )
    if counts["open_fail"] > args.max_open_fail:
        failures.append(
            f"open_fail={counts['open_fail']} > {args.max_open_fail}"
        )
    cleanup_p95 = percentile(stage_cleanup, 0.95) if stage_cleanup else None
    cancel_p95 = percentile(stage_cancel_cum, 0.95) if stage_cancel_cum else None
    financing_p95 = (
        percentile(stage_financing_cum, 0.95) if stage_financing_cum else None
    )
    open_total_p95 = percentile(stage_open_total, 0.95) if stage_open_total else None

    if cleanup_p95 is not None and cleanup_p95 > args.max_cleanup_p95:
        failures.append(
            f"stage_cleanup_pre_close p95={cleanup_p95:.2f}s > {args.max_cleanup_p95:.2f}s"
        )
    if cancel_p95 is not None and cancel_p95 > args.max_cancel_p95:
        failures.append(
            f"stage_cancel_cumulative p95={cancel_p95:.2f}s > {args.max_cancel_p95:.2f}s"
        )
    if financing_p95 is not None and financing_p95 > args.max_financing_p95:
        failures.append(
            f"stage_financing_cumulative p95={financing_p95:.2f}s > {args.max_financing_p95:.2f}s"
        )
    if open_total_p95 is not None and open_total_p95 > args.max_open_total_p95:
        failures.append(
            f"stage_account_open_total p95={open_total_p95:.2f}s > {args.max_open_total_p95:.2f}s"
        )

    print("")
    print("gate:")
    print(
        f"  thresholds: min_signals>={args.min_signals}, submit_p95<={args.max_submit_p95:.2f}s, "
        f"processed_p95<={args.max_processed_p95:.2f}s, 50011<={args.max_rate_limit}, "
        f"51016<={args.max_duplicate}, timeout<={args.max_timeout}, open_fail<={args.max_open_fail}, "
        f"cleanup_p95<={args.max_cleanup_p95:.2f}s, cancel_p95<={args.max_cancel_p95:.2f}s, "
        f"financing_p95<={args.max_financing_p95:.2f}s, open_total_p95<={args.max_open_total_p95:.2f}s"
    )
    if failures:
        print("  result: FAIL")
        for item in failures:
            print(f"  - {item}")
    else:
        print("  result: PASS")

    print("")
    print("triage:")
    hints = []
    if counts["rate_limit_50011"] > 0 or counts["api_danger_zone"] > 0:
        hints.append("疑似交易所/API限流：优先看 50011 与 API 危险区日志。")
    if counts["duplicate_51016"] > 0:
        hints.append("疑似幂等冲突：检查 clOrdId 重试与查单确认链路。")
    if counts["order_timeout"] > 0 and counts["rate_limit_50011"] == 0:
        hints.append("疑似交易所响应慢或网络抖动：重点看下单超时前后的请求耗时。")
    if cancel_p95 is not None and cancel_p95 > args.max_cancel_p95:
        hints.append("瓶颈在撤单阶段：检查历史挂单数量、撤单批次与并发。")
    if financing_p95 is not None and financing_p95 > args.max_financing_p95:
        hints.append("瓶颈在理财/划转阶段：检查余额判定与赎回划转接口时延。")
    if cleanup_p95 is not None and cleanup_p95 > args.max_cleanup_p95:
        hints.append("瓶颈在反向仓清理：检查持仓查询与平反向仓重试。")
    if open_total_p95 is not None and open_total_p95 > args.max_open_total_p95:
        hints.append("瓶颈在开仓总链路：优先对照阶段耗时与下单确认延迟。")
    if counts["order_not_exist_51603"] > 0:
        hints.append("存在订单状态滞后（51603）：检查本地订单同步与清理策略。")
    if counts["signal_pause_count"] > 0:
        hints.append("监控让路信号处理中：若处理慢且暂停次数高，优先排查信号链路而非监控。")

    if hints:
        for h in hints:
            print(f"  - {h}")
    else:
        print("  - 未发现明显瓶颈特征。")


if __name__ == "__main__":
    main()
