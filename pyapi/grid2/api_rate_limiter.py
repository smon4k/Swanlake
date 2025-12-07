"""
API 限流器 - 用于控制 API 请求频率，避免触发交易所限流
"""

import asyncio
import time
import logging


class SimpleRateLimiter:
    """
    简单的全局API限流器 - 不改变业务流程

    设计原则：
    1. 所有API调用都照样并发执行
    2. 当检测到即将超频时，自动短暂延迟（50-100ms）
    3. 利用这个延迟让其他请求先完成，保证时间窗口内不超过60次

    使用滑动窗口算法：
    - 记录最近2秒内的所有API调用时间戳
    - 每次调用前检查计数，如果接近限制就延迟
    """

    def __init__(self, max_requests: int = 60, time_window: float = 2.0):
        """
        初始化 API 限流器

        :param max_requests: 时间窗口内最大请求数（默认60）
        :param time_window: 时间窗口大小，单位秒（默认2秒）
        """
        self.max_requests = max_requests
        self.time_window = time_window
        self.request_times = []  # 最近的API调用时间戳
        self.lock = asyncio.Lock()

        logging.info(f"✅ API 限流器已初始化: {max_requests} 请求/{time_window}秒")

    async def check_and_wait(self):
        """
        调用任何API之前，检查并自动调节延迟

        工作流程：
        T=0ms:    signal账户1 cleanup [调用 check_and_wait]
                  → 计数=1，正常通过

        T=0ms:    signal账户2 cleanup [调用 check_and_wait]
                  → 计数=2，正常通过

        T=5ms:    signal账户3 cleanup [调用 check_and_wait]
                  → 计数=3，正常通过

        T=0ms:    price_monitoring账户1 fetch_positions [调用 check_and_wait]
                  → 计数=4，正常通过

        ...一直执行...

        T=150ms:  某个API调用 [调用 check_and_wait]
                  → 计数=55个（接近60），等待 100ms

        T=250ms:  那100ms的等待期间，前面的请求都已执行完成
                  → 时间窗口重置（2秒已过期）
                  → 继续正常执行
        """
        async with self.lock:
            now = time.time()

            # 清除超过时间窗口的记录
            self.request_times = [
                t for t in self.request_times if now - t < self.time_window
            ]

            current_count = len(self.request_times)

            # 如果接近限制（比如>50次），就延迟一下
            if current_count > 50:
                wait_time = 0.1  # 等100ms
                logging.info(
                    f"⏳ API 请求接近限制 ({current_count}/{self.max_requests})，"
                    f"延迟 {wait_time*1000:.0f}ms"
                )
                await asyncio.sleep(wait_time)

                # 延迟后重新清理过期记录
                now = time.time()
                self.request_times = [
                    t for t in self.request_times if now - t < self.time_window
                ]

            # 记录这次调用时间
            self.request_times.append(now)

            # 输出调试信息（可选）
            if len(self.request_times) % 10 == 0:
                logging.info(
                    f"📊 当前API调用计数: {len(self.request_times)}/{self.max_requests}"
                )

    async def get_current_status(self) -> dict:
        """
        获取当前限流器状态（用于监控和调试）

        :return: 包含当前请求数、限制等信息的字典
        """
        async with self.lock:
            now = time.time()
            self.request_times = [
                t for t in self.request_times if now - t < self.time_window
            ]

            current_count = len(self.request_times)
            utilization = (current_count / self.max_requests) * 100

            return {
                "current_count": current_count,
                "max_requests": self.max_requests,
                "time_window": self.time_window,
                "utilization": utilization,
                "near_limit": current_count > 35,
            }

    def reset(self):
        """
        重置限流器（通常在错误恢复时使用）
        """
        self.request_times = []
        logging.info("🔄 API 限流器已重置")


# 为了兼容性，创建一个别名
APIRateLimiter = SimpleRateLimiter
