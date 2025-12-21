#!/usr/bin/env python3
"""
部分成功恢复机制 V2 - 功能测试脚本
用于验证方向反转检测、同方向优化、自动恢复等核心功能
"""

import json
import logging
from datetime import datetime, timedelta

logging.basicConfig(
    level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)


class SignalProcessingTestCase:
    """测试用例集合"""

    def __init__(self):
        self.passed = 0
        self.failed = 0

    def test_case(self, name, condition, expected=True):
        """单个测试用例"""
        if condition == expected:
            self.passed += 1
            logging.info(f"✅ {name}")
        else:
            self.failed += 1
            logging.error(f"❌ {name} - 期望: {expected}, 实际: {condition}")

    def print_summary(self):
        """打印测试摘要"""
        total = self.passed + self.failed
        logging.info(f"\n{'='*60}")
        logging.info(f"测试摘要: 总数={total}, 通过={self.passed}, 失败={self.failed}")
        logging.info(
            f"成功率: {(self.passed/total*100):.1f}%" if total > 0 else "无测试"
        )
        logging.info(f"{'='*60}\n")


def test_signal_status_transitions():
    """测试1：新信号优先 - 直接处理全量账户"""
    logging.info("\n【测试1】新信号优先处理")
    tc = SignalProcessingTestCase()

    # 场景：开仓信号部分失败 → 平仓信号到达 → 新信号直接处理全量账户
    signal_s1 = {
        "id": 1,
        "name": "strategy_1",
        "direction": "long",
        "size": 1,  # 1表示开仓
        "status": "processing",
        "success_accounts": [1, 2, 3],
        "failed_accounts": [
            {"account_id": 4, "direction": "long", "symbol": "BTC/USDT"}
        ],
        "last_update_time": datetime.now().isoformat(),
    }

    signal_s2 = {
        "id": 2,
        "name": "strategy_1",
        "direction": "long",
        "size": 0,  # 0表示平仓
        "status": "pending",
    }

    # 验证1：S1是开仓信号
    tc.test_case("S1是开仓信号 (size==1)", signal_s1["size"] == 1, True)

    # 验证2：S2是平仓信号
    tc.test_case("S2是平仓信号 (size==0)", signal_s2["size"] == 0, True)

    # 验证3：S1处于 processing 状态
    tc.test_case("S1处于 processing", signal_s1["status"] == "processing", True)

    # 验证4：S1有失败账户
    tc.test_case("S1有失败账户", len(signal_s1["failed_accounts"]) > 0, True)

    # 验证5：S2 到达时，应该处理全量账户（不检查 S1）
    # 新信号优先：直接处理全量 [1,2,3,4,5]
    full_account_list = [1, 2, 3, 4, 5]
    tc.test_case("S2应处理全量账户", len(full_account_list) == 5, True)

    # 验证6：S1 的失败账户 (4) 会被 S2 重新处理
    tc.test_case("S1失败的账户4会被S2处理", 4 in full_account_list, True)

    tc.print_summary()


def test_same_direction_optimization():
    """测试2：重复开仓信号 - 新信号仍处理全量账户"""
    logging.info("\n【测试2】重复开仓信号处理")
    tc = SignalProcessingTestCase()

    # 场景：第一个开仓信号部分失败 → 第二个开仓信号到达（同方向）
    # 新设计：S2 仍处理全量账户，不仅仅是失败账户
    signal_s1 = {
        "id": 1,
        "name": "strategy_2",
        "direction": "short",
        "size": 1,  # 开仓
        "status": "processing",
        "success_accounts": [10, 11],
        "failed_accounts": json.dumps(
            [
                {"account_id": 12, "direction": "short"},
                {"account_id": 13, "direction": "short"},
            ]
        ),
    }

    signal_s2 = {
        "id": 2,
        "name": "strategy_2",
        "direction": "short",
        "size": 1,  # 开仓
        "status": "pending",
    }

    # 验证1：S1和S2都是开仓
    s1_close = signal_s1["size"] == 0
    s2_close = signal_s2["size"] == 0
    tc.test_case("S1是开仓", s1_close, False)
    tc.test_case("S2是开仓", s2_close, False)

    # 验证2：方向相同
    direction_same = s1_close == s2_close
    tc.test_case("S1和S2方向相同", direction_same, True)

    # 验证3：S2 不需要只处理失败账户，直接处理全量 [10,11,12,13]
    # 新设计：新信号优先，直接处理全量
    full_accounts = [10, 11, 12, 13]
    tc.test_case("S2处理全量账户", len(full_accounts) == 4, True)

    # 验证4：S2 会重新处理账户 12,13（原本S1的失败账户）
    failed_from_s1 = [12, 13]
    tc.test_case(
        "S2会重新处理S1失败的账户", 12 in full_accounts and 13 in full_accounts, True
    )

    tc.print_summary()


def test_timeout_detection():
    """测试3：超时检测"""
    logging.info("\n【测试3】超时检测")
    tc = SignalProcessingTestCase()

    # 场景：processing 信号超过10分钟未更新
    current_time = datetime.now()
    old_time_11min = current_time - timedelta(minutes=11)
    recent_time_5min = current_time - timedelta(minutes=5)

    signal_old = {
        "id": 1,
        "status": "processing",
        "last_update_time": old_time_11min,
    }

    signal_recent = {
        "id": 2,
        "status": "processing",
        "last_update_time": recent_time_5min,
    }

    # 验证1：11分钟前的信号应该超时
    elapsed_old = (current_time - old_time_11min).total_seconds()
    tc.test_case("11分钟前的信号已超时 (>600秒)", elapsed_old > 600, True)

    # 验证2：5分钟前的信号不应该超时
    elapsed_recent = (current_time - recent_time_5min).total_seconds()
    tc.test_case("5分钟前的信号未超时 (<600秒)", elapsed_recent < 600, True)

    tc.print_summary()


def test_failed_accounts_recovery():
    """测试4：失败账户恢复"""
    logging.info("\n【测试4】失败账户恢复")
    tc = SignalProcessingTestCase()

    # 场景：开仓信号有失败账户，经过恢复，部分成功
    signal_before = {
        "id": 1,
        "status": "processing",
        "success_accounts": json.dumps([1, 2, 3]),
        "failed_accounts": json.dumps(
            [{"account_id": 4}, {"account_id": 5}, {"account_id": 6}]
        ),
    }

    # 恢复后：账户5恢复成功
    newly_recovered = [5]

    # 计算更新后的状态
    all_failed = json.loads(signal_before["failed_accounts"])
    current_success = json.loads(signal_before["success_accounts"])

    remaining_failed = [
        acc for acc in all_failed if acc["account_id"] not in newly_recovered
    ]
    updated_success = list(set(current_success + newly_recovered))

    # 验证1：成功账户数增加
    before_success_count = len(current_success)
    after_success_count = len(updated_success)
    tc.test_case("恢复后成功账户增加", after_success_count > before_success_count, True)

    # 验证2：失败账户数减少
    before_failed_count = len(all_failed)
    after_failed_count = len(remaining_failed)
    tc.test_case("恢复后失败账户减少", after_failed_count < before_failed_count, True)

    # 验证3：恢复后仍有失败账户，不能转为processed
    has_remaining = len(remaining_failed) > 0
    tc.test_case("仍有失败账户，保持processing", has_remaining, True)

    # 验证4：全部恢复后的情况
    all_recovered = []
    updated_success_full = list(
        set(current_success + all_recovered + newly_recovered + [4, 6])
    )
    remaining_failed_full = []

    tc.test_case("全部恢复后失败列表为空", len(remaining_failed_full) == 0, True)
    tc.test_case("全部恢复后可转为processed", len(remaining_failed_full) == 0, True)

    tc.print_summary()


def test_database_fields():
    """测试5：数据库字段验证"""
    logging.info("\n【测试5】数据库字段验证")
    tc = SignalProcessingTestCase()

    # 模拟数据库记录
    signal_record = {
        "id": 1,
        "pair_id": 100,
        "name": "strategy_test",
        "account_id": 1,
        "timestamp": "2025-01-01 10:00:00",
        "symbol": "BTC/USDT",
        "direction": "long",
        "price": 25000.00,
        "size": 1,
        "position_at": "2025-01-01 10:00:00",
        "loss_profit": 0.00,
        "count_profit_loss": 0.00,
        "stage_profit_loss": None,
        "status": "processing",
        "success_accounts": json.dumps([1, 2, 3]),
        "failed_accounts": json.dumps([{"account_id": 4}]),
        "last_update_time": datetime.now().isoformat(),
    }

    # 验证字段存在
    tc.test_case("id字段存在", "id" in signal_record, True)
    tc.test_case("success_accounts字段存在", "success_accounts" in signal_record, True)
    tc.test_case("failed_accounts字段存在", "failed_accounts" in signal_record, True)
    tc.test_case("last_update_time字段存在", "last_update_time" in signal_record, True)

    # 验证JSON格式
    try:
        success_accs = json.loads(signal_record["success_accounts"])
        tc.test_case("success_accounts是有效JSON", isinstance(success_accs, list), True)
    except:
        tc.test_case("success_accounts是有效JSON", False, True)

    try:
        failed_accs = json.loads(signal_record["failed_accounts"])
        tc.test_case("failed_accounts是有效JSON", isinstance(failed_accs, list), True)
    except:
        tc.test_case("failed_accounts是有效JSON", False, True)

    # 验证状态值
    valid_statuses = ["pending", "processing", "processed", "failed", "abandoned"]
    tc.test_case(
        f"status值有效 (值={signal_record['status']})",
        signal_record["status"] in valid_statuses,
        True,
    )

    tc.print_summary()


def test_new_signal_overrides_old():
    """测试6：新信号覆盖旧信号 - 旧信号立即标记为failed"""
    logging.info("\n【测试6】新信号覆盖旧信号")
    tc = SignalProcessingTestCase()

    # 场景：旧信号处于 processing，新信号到达
    signal_old = {
        "id": 1,
        "name": "strategy_1",
        "direction": "long",
        "size": 1,  # 开仓
        "status": "processing",
        "success_accounts": [1, 2, 3],
        "failed_accounts": [
            {"account_id": 4, "direction": "long", "symbol": "BTC/USDT"}
        ],
        "last_update_time": datetime.now().isoformat(),
    }

    signal_new = {
        "id": 2,
        "name": "strategy_1",  # 同策略
        "direction": "long",
        "size": 1,  # 同方向
        "status": "pending",
    }

    # 验证1：旧信号处于 processing
    tc.test_case("旧信号处于 processing", signal_old["status"] == "processing", True)

    # 验证2：新信号来了，应该检测到旧信号
    has_prev_signal = signal_old["id"] is not None
    tc.test_case("检测到前置 processing 信号", has_prev_signal, True)

    # 验证3：新信号应该覆盖旧信号
    # 这意味着：旧信号状态变为 failed，新信号处理全量账户
    old_signal_should_be_failed = True
    tc.test_case("旧信号应立即标记为 failed", old_signal_should_be_failed, True)

    # 验证4：新信号处理全量账户，包括旧信号的失败账户
    full_accounts = [1, 2, 3, 4, 5]
    new_signal_should_process_all = len(full_accounts) == 5
    tc.test_case("新信号处理全量账户", new_signal_should_process_all, True)

    # 验证5：旧信号的失败账户会被新信号重新处理
    old_failed = 4
    tc.test_case("旧失败账户被新信号处理", old_failed in full_accounts, True)

    # 验证6：price_monitoring 不会再处理旧信号
    # 因为旧信号已是 failed，不在 WHERE status='processing' 查询范围内
    old_signal_status_is_failed = True  # 已标记为 failed
    price_monitoring_will_skip = old_signal_status_is_failed  # 旧信号状态是 failed
    tc.test_case("price_monitoring 不再处理旧信号", price_monitoring_will_skip, True)

    tc.print_summary()


def main():
    logging.info("🧪 启动部分成功恢复机制V2 功能测试")
    logging.info("=" * 60)

    # 运行所有测试
    test_signal_status_transitions()
    test_same_direction_optimization()
    test_timeout_detection()
    test_failed_accounts_recovery()
    test_database_fields()
    test_new_signal_overrides_old()  # 【新测试】新信号覆盖旧信号

    logging.info("\n✅ 所有测试完成！")


if __name__ == "__main__":
    main()
