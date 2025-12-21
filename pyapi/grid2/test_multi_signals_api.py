"""
多信号并发处理测试脚本
通过本地 API 模拟多个不同策略的信号同时进来
适配现有的 OKX 模拟账户和策略配置
"""

import asyncio
import aiohttp
import logging
from datetime import datetime
import json

# 配置日志
logging.basicConfig(
    level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger(__name__)

# API 基础 URL
API_BASE_URL = "http://localhost:8083"
INSERT_SIGNAL_URL = f"{API_BASE_URL}/insert_signal"

# ========== 核心配置 ==========
# 根据你的 OKX 模拟账户和配置
TEST_SCENARIOS = [
    # 场景1：策略 T1.0 - 账户 1,2 (假设配置的两个账户)
    {
        "strategy_name": "T1.0",
        "requests": [
            {
                "name": "T1.0",
                "symbol": "BTC-USDT-SWAP",
                "price": 88910,
                "side": "buy",
                "size": "1",
            },
        ],
    },
    # 场景2：策略 T1.1 - 账户 3,4
    {
        "strategy_name": "T1.1",
        "requests": [
            {
                "name": "T1.1",
                "symbol": "BTC-USDT-SWAP",
                "price": 88910,
                "side": "buy",
                "size": "1",
            },
        ],
    },
]


async def send_signal(session, signal_data):
    """发送单个信号到 API"""
    try:
        async with session.post(INSERT_SIGNAL_URL, json=signal_data) as resp:
            result = await resp.json()
            return {
                "status": "success" if resp.status == 200 else "error",
                "data": signal_data,
                "response": result,
            }
    except Exception as e:
        return {"status": "error", "data": signal_data, "error": str(e)}


async def test_concurrent_signals():
    """
    测试1：并发发送多个信号（最快模拟多信号进来）
    """
    logger.info("=" * 80)
    logger.info("🚀 测试1：并发发送多个信号")
    logger.info("=" * 80)

    async with aiohttp.ClientSession() as session:
        tasks = []

        logger.info("\n📤 准备发送信号...\n")

        # 准备所有待发送的请求
        all_signals = []
        for scenario in TEST_SCENARIOS:
            logger.info(f"策略: {scenario['strategy_name']}")
            for idx, signal in enumerate(scenario["requests"], 1):
                logger.info(
                    f"  请求 {idx}: {signal['name']} {signal['symbol']} {signal['side']} x{signal['size']}"
                )
                all_signals.append(signal)

        logger.info(f"\n🔄 总共 {len(all_signals)} 个信号，开始并发发送...")
        logger.info("=" * 80)

        # 并发发送所有信号
        start_time = datetime.now()

        tasks = [send_signal(session, signal) for signal in all_signals]
        results = await asyncio.gather(*tasks)

        end_time = datetime.now()
        elapsed = (end_time - start_time).total_seconds()

        # 统计结果
        success_count = sum(1 for r in results if r["status"] == "success")
        error_count = sum(1 for r in results if r["status"] == "error")

        logger.info("\n📊 发送结果汇总:")
        logger.info(f"  ✅ 成功: {success_count}/{len(all_signals)}")
        logger.info(f"  ❌ 失败: {error_count}/{len(all_signals)}")
        logger.info(f"  ⏱️  耗时: {elapsed:.2f} 秒")
        logger.info("=" * 80)

        # 详细结果
        logger.info("\n📋 详细结果:\n")
        for idx, result in enumerate(results, 1):
            status_emoji = "✅" if result["status"] == "success" else "❌"
            logger.info(
                f"{status_emoji} 信号 {idx}: {result['data']['name']} - {result['data']['symbol']}"
            )
            if result["status"] == "error":
                logger.error(f"   错误: {result.get('error', 'Unknown error')}")
            else:
                logger.info(f"   响应: {result.get('response', {})}")

        logger.info("\n" + "=" * 80)
        logger.info("✅ 测试1 完成")
        logger.info("=" * 80)

        # 等待处理完成
        logger.info("\n⏱️  等待信号处理 (15秒)...")
        logger.info("预期结果:")
        logger.info("  - 日志中应该看到: 📊 收到 X 个信号，开始并发处理")
        logger.info("  - 两个策略应该同时执行")
        logger.info("  - 不应该出现 'attached to a different loop' 错误")

        await asyncio.sleep(15)


async def test_sequential_signals():
    """
    测试2：快速顺序发送多个信号（间隔短，模拟同时进来）
    """
    logger.info("=" * 80)
    logger.info("🚀 测试2：快速顺序发送多个信号 (100ms间隔)")
    logger.info("=" * 80)

    async with aiohttp.ClientSession() as session:
        logger.info("\n📤 准备发送信号...\n")

        all_signals = []
        for scenario in TEST_SCENARIOS:
            logger.info(f"策略: {scenario['strategy_name']}")
            for signal in scenario["requests"]:
                logger.info(f"  {signal['name']} {signal['symbol']}")
                all_signals.append(signal)

        logger.info(f"\n🔄 开始快速顺序发送 {len(all_signals)} 个信号 (100ms间隔)...")
        logger.info("=" * 80)

        results = []
        start_time = datetime.now()

        for idx, signal in enumerate(all_signals, 1):
            logger.info(f"📤 发送信号 {idx}/{len(all_signals)}: {signal['name']}")
            result = await send_signal(session, signal)
            results.append(result)

            # 短延迟，让多个信号几乎同时到达数据库
            if idx < len(all_signals):
                await asyncio.sleep(0.1)

        end_time = datetime.now()
        elapsed = (end_time - start_time).total_seconds()

        success_count = sum(1 for r in results if r["status"] == "success")

        logger.info(f"\n✅ 全部发送完成")
        logger.info(f"  成功: {success_count}/{len(all_signals)}")
        logger.info(f"  耗时: {elapsed:.2f} 秒")
        logger.info("=" * 80)

        logger.info("\n⏱️  等待信号处理 (15秒)...")
        await asyncio.sleep(15)


async def test_rapid_fire_signals():
    """
    测试3：极速发送信号（无延迟，真正的并发）
    """
    logger.info("=" * 80)
    logger.info("🚀 测试3：极速并发发送 (无延迟)")
    logger.info("=" * 80)

    # 增加更多变种以增加压力
    signals_variants = [
        {
            "name": "T1.0",
            "symbol": "BTC-USDT-SWAP",
            "price": 90747,
            "side": "buy",
            "size": "1",
        },
        {
            "name": "T1.1",
            "symbol": "BTC-USDT-SWAP",
            "price": 90750,
            "side": "buy",
            "size": "1",
        },
        {
            "name": "T1.0",
            "symbol": "BTC-USDT-SWAP",
            "price": 90748,
            "side": "buy",
            "size": "1",
        },
        {
            "name": "T1.1",
            "symbol": "BTC-USDT-SWAP",
            "price": 90751,
            "side": "buy",
            "size": "1",
        },
    ]

    async with aiohttp.ClientSession() as session:
        logger.info(f"\n🔄 并发发送 {len(signals_variants)} 个信号...")
        logger.info("=" * 80)

        start_time = datetime.now()

        # 全部并发，最快速度
        tasks = [send_signal(session, signal) for signal in signals_variants]
        results = await asyncio.gather(*tasks)

        end_time = datetime.now()
        elapsed = (end_time - start_time).total_seconds()

        success_count = sum(1 for r in results if r["status"] == "success")

        logger.info(f"\n✅ 全部发送完成")
        logger.info(f"  成功: {success_count}/{len(signals_variants)}")
        logger.info(
            f"  耗时: {elapsed:.2f} 秒 (平均 {elapsed/len(signals_variants):.3f}s/信号)"
        )
        logger.info("=" * 80)

        logger.info("\n⏱️  等待信号处理 (15秒)...")
        await asyncio.sleep(15)


async def main():
    """主测试函数"""
    import sys

    logger.info("\n")
    logger.info("╔" + "=" * 78 + "╗")
    logger.info("║" + " " * 20 + "多信号并发处理测试" + " " * 38 + "║")
    logger.info("║" + " " * 18 + "用于验证新的 asyncio.gather() 方案" + " " * 26 + "║")
    logger.info("╚" + "=" * 78 + "╝")
    logger.info("")

    if len(sys.argv) > 1:
        test_type = sys.argv[1]
    else:
        test_type = "concurrent"

    try:
        if test_type == "sequential":
            await test_sequential_signals()
        elif test_type == "rapid":
            await test_rapid_fire_signals()
        else:  # concurrent (default)
            await test_concurrent_signals()

        logger.info("\n✅ 所有测试完成！")
        logger.info("\n📋 验证清单:")
        logger.info("  [ ] 是否看到 '📊 收到 X 个信号，开始并发处理'？")
        logger.info("  [ ] 是否所有信号都成功写入数据库？")
        logger.info("  [ ] 是否所有账户都成功执行信号？")
        logger.info("  [ ] 是否没有 'attached to a different loop' 错误？")
        logger.info("  [ ] 日志是否显示并发处理（而不是串行）？")

    except Exception as e:
        logger.error(f"❌ 测试失败: {e}", exc_info=True)


if __name__ == "__main__":
    asyncio.run(main())
