import asyncio
from datetime import datetime
from decimal import Decimal
import logging
import os
import time
from typing import Any, Dict
import redis
import json
from database import Database
from stop_loss_task import StopLossTask
from trading_bot_config import TradingBotConfig
from savings_task import SavingsTask
from collections import defaultdict
from common_functions import (
    cancel_all_orders,
    get_account_balance,
    get_exchange,
    get_market_price,
    get_market_precision,
    get_max_position_value,
    get_total_positions,
    open_position,
    get_client_order_id,
    fetch_current_positions,
)


class SignalProcessingTask:
    """交易信号处理类"""

    def __init__(
        self,
        config: TradingBotConfig,
        db: Database,
        signal_lock: asyncio.Lock,
        stop_loss_task: StopLossTask,
        account_locks: defaultdict,
        busy_accounts: set,
        api_limiter=None,
        signal_processing_active: asyncio.Event = None,  # ✅ 新增参数
    ):
        self.db = db
        self.config = config
        self.running = True
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task  # 保存引用
        # self.account_locks = defaultdict(asyncio.Lock)  # 每个 account_id 一个锁
        self.redis = redis.Redis(host="localhost", port=6379, decode_responses=True)
        self.pubsub = self.redis.pubsub()
        self.account_locks = account_locks  # 外部传入的账户锁
        self.busy_accounts = busy_accounts  # 外部传入的忙碌账户集合
        self.active_tasks: set[asyncio.Task] = set()  # 用于跟踪正在运行的任务
        self.market_precision_cache = {}  # 市场精度缓存
        self.api_limiter = api_limiter  # 全局API限流器

        # ✅ 【新增】任务协调标志
        self.signal_processing_active = signal_processing_active

    async def signal_processing_task(self):
        """信号调度任务，支持多个信号并发"""
        # 订阅频道
        self.pubsub.subscribe("signal_channel")
        # print("✅ 已订阅 signal_channel 等待唤醒...")
        logging.info("✅ 已订阅 signal_channel 等待唤醒...")
        while getattr(self, "running", True):
            try:
                # print("🔍 信号调度任务运行中...")
                # ✅ 把阻塞的 get_message 放到线程池
                message = await asyncio.to_thread(
                    self.pubsub.get_message,
                    True,  # ignore_subscribe_messages
                    1,  # timeout
                )
                if message:
                    # print("📩 收到通知:", message)
                    logging.info(f"📩 收到通知: {message}")
                    asyncio.create_task(self.dispatch_signals())

                await asyncio.sleep(self.config.signal_check_interval)

            except Exception as e:
                print(f"信号调度异常: {e}")
                logging.error(f"信号调度异常: {e}")
                await asyncio.sleep(5)

    async def dispatch_signals(self):
        # 从数据库取信号并处理
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                # ✅ 使用事务，查询并立即更新状态
                cursor.execute("START TRANSACTION")

                cursor.execute(
                    "SELECT * FROM g_signals WHERE status='pending' LIMIT 10"  # 一次取多条
                )
                signals = cursor.fetchall()
                # ✅ 立即标记为 processing，防止重复获取
                if signals:
                    signal_ids = [s["id"] for s in signals]
                    placeholders = ",".join(["%s"] * len(signal_ids))
                    cursor.execute(
                        f"UPDATE g_signals SET status='processing' WHERE id IN ({placeholders})",
                        signal_ids,
                    )
                conn.commit()  # ✅ 提交事务
            conn.close()

            if signals:
                # ✅ 【新增】设置信号处理活跃标志
                if self.signal_processing_active:
                    self.signal_processing_active.set()
                    logging.info(
                        f"🚨 信号处理开始 ({len(signals)}个)，其他任务降低优先级"
                    )

                try:
                    # ✅ 关键改动：直接并发处理多个信号
                    logging.info(f"📊 收到 {len(signals)} 个信号，开始并发处理")

                    tasks = [self.handle_single_signal(signal) for signal in signals]
                    await asyncio.gather(*tasks, return_exceptions=True)

                    logging.info(f"✅ {len(signals)} 个信号处理完成")

                finally:
                    # ✅ 【新增】清除信号处理活跃标志
                    if self.signal_processing_active:
                        self.signal_processing_active.clear()
                        logging.info("✅ 信号处理完成，恢复其他任务正常优先级")
            else:
                await asyncio.sleep(self.config.signal_check_interval)
        except Exception as e:
            if conn:
                conn.rollback()  # 回滚事务
            logging.error(f"处理信号异常: {e}")

            # ✅ 异常时也要清除标志
            if self.signal_processing_active:
                self.signal_processing_active.clear()

    async def _run_single_account_signal(self, signal: dict, account_id: int):
        """单账户信号处理：完成后立即释放 busy 状态"""
        lock = self.account_locks[account_id]

        # ✅ 【新增】锁获取超时（最多等待15秒）
        lock_timeout = 15.0
        lock_acquired = False

        try:
            # ✅ 尝试获取锁，带超时
            try:
                await asyncio.wait_for(lock.acquire(), timeout=lock_timeout)
                lock_acquired = True
            except asyncio.TimeoutError:
                logging.error(
                    f"⏰ 账户 {account_id} 锁获取超时({lock_timeout}秒)，"
                    f"可能被其他任务长时间占用"
                )
                return {
                    "success": False,
                    "msg": "lock_timeout",
                    "account_id": account_id,
                    "data": None,
                }

            # ✅ 成功获取锁后的处理
            self.busy_accounts.add(account_id)

            try:
                logging.info(f"🎯 账户 {account_id} 开始执行信号 {signal['id']}")

                await self.process_signal(signal, account_id)

                return {
                    "success": True,
                    "msg": "ok",
                    "account_id": account_id,
                    "data": None,
                }

            except Exception as e:
                logging.error(f"❌ 账户 {account_id} 信号处理失败: {e}")
                return {"success": False, "msg": str(e), "account_id": account_id}

            finally:
                self.busy_accounts.discard(account_id)
                logging.info(f"🔓 账户 {account_id} 已释放")

        finally:
            # ✅ 确保释放锁
            if lock_acquired:
                lock.release()

    async def _process_accounts_with_retry(
        self, signal, account_list, batch_size=8, max_retries=3
    ):
        """
        处理所有账户，失败的账户会自动重试

        ✅ 使用 asyncio.gather 替代 Future + callback，避免事件循环错误
        ✅ 确保每个账户的 OKX 异步调用不延迟

        :param signal: 交易信号
        :param account_list: 账户列表
        :param batch_size: 每批处理的账户数（控制并发数）
        :param max_retries: 最大重试次数
        :return: dict {account_id: result or exception}
        """
        results = {}
        remaining_accounts = list(account_list)
        retry_count = 0

        while remaining_accounts and retry_count < max_retries:
            logging.info(
                f"📊 第 {retry_count + 1} 轮处理，"
                f"策略={signal['name']}, 账户数={len(remaining_accounts)}, "
                f"批大小={batch_size}"
            )

            next_remaining = []

            # ✅ 分批并发处理（确保 OKX 调用不延迟）
            for i in range(0, len(remaining_accounts), batch_size):
                batch = remaining_accounts[i : i + batch_size]
                logging.info(f"  ├─ 处理第 {i//batch_size + 1} 批: {len(batch)} 个账户")

                # ✅ 这一批账户并发调用 OKX（零延迟）
                tasks = [
                    self._run_single_account_signal(signal, acc_id) for acc_id in batch
                ]
                batch_results = await asyncio.gather(*tasks, return_exceptions=True)

                # ✅ 分离成功和失败
                for acc_id, result in zip(batch, batch_results):
                    if isinstance(result, Exception):
                        next_remaining.append(acc_id)
                        logging.warning(f"    ⚠️ 账户 {acc_id} 异常: {result}")

                    # ✅ 【新增】识别锁超时错误，特殊处理
                    elif (
                        isinstance(result, dict) and result.get("msg") == "lock_timeout"
                    ):
                        next_remaining.append(acc_id)
                        logging.warning(
                            f"    ⚠️ 账户 {acc_id} 锁获取超时，将在下一轮重试"
                        )

                    elif result.get("success", False):
                        results[acc_id] = result
                        logging.info(f"    ✅ 账户 {acc_id} 成功")
                    else:
                        next_remaining.append(acc_id)
                        logging.warning(
                            f"    ⚠️ 账户 {acc_id} 失败: {result.get('msg', 'unknown')}"
                        )

                # ✅ 批与批之间加小延迟（给 OKX API 恢复时间，不影响首次请求）
                if i + batch_size < len(remaining_accounts):
                    await asyncio.sleep(0.3)

            remaining_accounts = next_remaining
            retry_count += 1

            # ✅ 如果还有失败的账户且还有重试次数
            if remaining_accounts and retry_count < max_retries:
                # ✅ 【修改】增加重试延时：2s, 4s, 8s（原来是1s, 2s, 4s）
                wait_time = 2.0 * (2 ** (retry_count - 1))
                logging.warning(
                    f"⏳ {len(remaining_accounts)} 个账户需要重试，"
                    f"等待 {wait_time:.1f}秒后进行第 {retry_count + 1} 轮..."
                )
                await asyncio.sleep(wait_time)

        # ✅ 最后一轮仍然失败的账户
        for acc_id in remaining_accounts:
            results[acc_id] = Exception(f"账户 {acc_id} 重试 {max_retries} 次后仍失败")
            logging.error(f"❌ 账户 {acc_id} 重试 {max_retries} 次后仍然失败")

        # ✅ 关键日志：处理完毕，清除busy_accounts
        logging.info(
            f"📊 信号 {signal.get('name')} (ID={signal.get('id')}) 处理完成，清除busy_accounts"
        )
        for acc_id in account_list:
            if self.busy_accounts and acc_id in self.busy_accounts:
                self.busy_accounts.discard(acc_id)
                logging.info(
                    f"✅ 账户 {acc_id} 从busy_accounts中移除 (当前busy_accounts={self.busy_accounts})"
                )

        return results

    def _is_close_signal(self, signal):
        """判断是否是平仓信号（size=0表示平仓）"""
        return signal.get("size", 1) == 0

    def _check_previous_processing_signal(self, strategy_name, current_signal_id=None):
        """检查是否有该策略未完成的 processing 信号（排除当前信号）"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                if current_signal_id:
                    # ✅ 排除当前信号，只查找更旧的前置信号
                    cursor.execute(
                        """SELECT id, direction, size 
                           FROM g_signals 
                           WHERE status='processing' 
                           AND name=%s 
                           AND id < %s
                           ORDER BY id DESC 
                           LIMIT 1""",
                        (strategy_name, current_signal_id),
                    )
                else:
                    # ⚠️ 兼容旧代码（不传 current_signal_id）
                    cursor.execute(
                        """SELECT id, direction, size 
                           FROM g_signals 
                           WHERE status='processing' 
                           AND name=%s 
                           ORDER BY id DESC 
                           LIMIT 1""",
                        (strategy_name,),
                    )
                result = cursor.fetchone()
            conn.close()
            return result
        except Exception as e:
            logging.error(f"❌ 检查 processing 信号失败: {e}")
            return None

    def _mark_signal_failed(self, signal_id):
        """立即标记信号为 failed（被新信号覆盖放弃处理）"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    """UPDATE g_signals 
                       SET status='failed', last_update_time=NOW() 
                       WHERE id=%s""",
                    (signal_id,),
                )
            conn.commit()
            conn.close()
            logging.warning(f"⚠️ 信号 {signal_id} 被新信号覆盖，标记为 failed")
        except Exception as e:
            logging.error(f"❌ 标记信号为failed失败: {e}")

    async def _verify_positions_and_collect_failures(
        self, signal, account_tactics_list, results
    ):
        """
        验证每个账户是否真的完成了信号要求
        - 开仓信号：检查实际仓位是否存在（应该有仓位）
        - 平仓信号：检查实际仓位是否已清空（应该无仓位）
        返回失败的账户列表
        """
        failed_accounts_list = []
        is_close_signal = self._is_close_signal(signal)

        for acc_id in account_tactics_list:
            try:
                signal_result = results.get(acc_id)

                # 只验证信号处理返回成功的账户
                if not signal_result or not signal_result.get("success", False):
                    logging.debug(f"⏭️ 账户 {acc_id} 信号处理未成功，跳过仓位验证")
                    continue

                # 检查实际仓位
                actual_positions = await get_total_positions(
                    self, acc_id, signal["symbol"], "SWAP"
                )

                if is_close_signal:
                    # ✅ 平仓信号：应该无仓位，如果还有仓位则平仓失败
                    if actual_positions is not None and actual_positions > 0:
                        logging.warning(
                            f"⚠️ 账户 {acc_id} 平仓失败（仍有仓位）- 信号={signal['id']}, "
                            f"币种={signal['symbol']}, 仓位={actual_positions}"
                        )

                        failed_accounts_list.append(
                            {
                                "account_id": acc_id,
                                "direction": signal["direction"],
                                "symbol": signal["symbol"],
                                "price": float(signal["price"]),
                                "size": signal["size"],
                            }
                        )
                    else:
                        logging.info(
                            f"✅ 账户 {acc_id} 平仓验证通过 - 币种={signal['symbol']}, 无仓位"
                        )
                else:
                    # ✅ 开仓信号：应该有仓位，如果无仓位则开仓失败
                    if actual_positions is None or actual_positions == 0:
                        logging.warning(
                            f"⚠️ 账户 {acc_id} 开仓失败（无仓位）- 信号={signal['id']}, "
                            f"币种={signal['symbol']}, 方向={signal['direction']}"
                        )

                        failed_accounts_list.append(
                            {
                                "account_id": acc_id,
                                "direction": signal["direction"],
                                "symbol": signal["symbol"],
                                "price": float(signal["price"]),
                                "size": signal["size"],
                            }
                        )
                    else:
                        logging.info(
                            f"✅ 账户 {acc_id} 开仓验证通过 - "
                            f"币种={signal['symbol']}, 仓位={actual_positions}"
                        )

            except Exception as e:
                logging.error(f"❌ 验证账户 {acc_id} 仓位异常: {e}", exc_info=True)

        return failed_accounts_list

    async def handle_single_signal(self, signal):
        """单条信号的处理逻辑 - 新信号优先，覆盖前置信号"""
        try:
            signal_id = signal["id"]
            logging.info(f"🚦 开始处理信号 {signal_id} ...")

            if signal["name"] not in self.db.tactics_accounts_cache:
                logging.info("🚫 无对应账户策略信号")
                self._update_signal_status(signal_id, "processed")
                return

            # ✅ 【关键】检查并处理前置 processing 信号（排除当前信号）
            prev_signal = self._check_previous_processing_signal(
                signal["name"], signal_id
            )
            if prev_signal:
                prev_signal_id = prev_signal["id"]
                logging.warning(
                    f"⚠️ 检测到前置信号 {prev_signal_id} 处于 processing 状态"
                )
                # 新信号来了，前置信号立即标记为 failed（不再被 price_monitoring 处理）
                self._mark_signal_failed(prev_signal_id)
                logging.warning(
                    f"🔄 新信号 {signal_id} 覆盖旧信号 {prev_signal_id}，"
                    f"旧信号标记为 failed，不再恢复处理"
                )

            # ✅ 获取全量账户列表（新信号优先，不考虑前置信号）
            account_list = self.db.tactics_accounts_cache[signal["name"]]
            is_close_signal = self._is_close_signal(signal)

            logging.info(
                f"📢 信号 {signal.get('name')} (ID={signal_id}) "
                f"{'平仓' if is_close_signal else '开仓'} "
                f"账户: {account_list}"
            )

            # ✅ 处理账户（全量处理，新信号为主导）
            results = await self._process_accounts_with_retry(
                signal, account_list, batch_size=8, max_retries=3
            )

            # ✅ 分类成功和失败
            success_accounts = []
            failed_accounts = []

            for acc_id, res in results.items():
                if isinstance(res, Exception):
                    logging.error(f"❌ 账户 {acc_id} 执行异常: {res}")
                    failed_accounts.append(acc_id)
                elif not res.get("success", False):
                    logging.warning(
                        f"⚠️ 账户 {acc_id} 执行失败: {res.get('msg', 'unknown')}"
                    )
                    failed_accounts.append(acc_id)
                else:
                    success_accounts.append(acc_id)

            # ✅ 执行平仓逻辑
            if is_close_signal:
                if not failed_accounts or len(success_accounts) > 0:
                    logging.info(f"✅ 平仓信号 {signal_id} 处理完成，执行后续处理")
                    await self.handle_close_position_update(signal)
                else:
                    logging.error(f"❌ 平仓信号 {signal_id} 全部失败，跳过后续处理")

            # ✅ 验证仓位并收集失败账户
            logging.info(f"📊 开始验证信号 {signal_id} 中账户的执行情况...")
            position_failed_accounts = (
                await self._verify_positions_and_collect_failures(
                    signal, account_list, results
                )
            )

            # ✅ 更新信号状态
            if not position_failed_accounts:
                # 全部成功
                self._update_signal_full_success(signal_id, success_accounts)
                logging.info(f"✅ 信号 {signal_id} 全部成功，状态改为 processed")
            else:
                # 部分失败：保持 processing，记录成功和失败账户
                self._update_signal_partial_success(
                    signal_id,
                    success_accounts,
                    position_failed_accounts,
                    pair_id=signal.get("id"),
                )
                logging.warning(
                    f"⚠️ 信号 {signal_id} 部分失败({len(position_failed_accounts)}个)，"
                    f"状态保持processing，等待恢复"
                )

        except Exception as e:
            logging.error(f"❌ 信号 {signal_id} 处理异常: {e}", exc_info=True)
            self._update_signal_status(signal_id, "failed")

    def _update_signal_status(self, signal_id, status):
        """更新信号状态（简单更新）"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    """UPDATE g_signals 
                       SET status=%s, last_update_time=NOW() 
                       WHERE id=%s""",
                    (status, signal_id),
                )
            conn.commit()
            conn.close()
        except Exception as e:
            logging.error(f"❌ 更新信号 {signal_id} 状态为 {status} 失败: {e}")

    def _update_signal_full_success(self, signal_id, success_accounts):
        """信号全部成功：清除失败记录，状态改为processed"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    """UPDATE g_signals 
                       SET status='processed',
                           success_accounts=%s,
                           failed_accounts=NULL,
                           last_update_time=NOW()
                       WHERE id=%s""",
                    (json.dumps(success_accounts), signal_id),
                )
            conn.commit()
            conn.close()
            logging.info(
                f"✅ 信号 {signal_id} 全部成功，已处理 {len(success_accounts)} 个账户"
            )
        except Exception as e:
            logging.error(f"❌ 更新信号 {signal_id} 全部成功状态失败: {e}")

    def _update_signal_partial_success(
        self, signal_id, success_accounts, failed_accounts, pair_id=None
    ):
        """信号部分失败：记录成功和失败账户，状态保持processing"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    """UPDATE g_signals 
                       SET status='processing',
                           success_accounts=%s,
                           failed_accounts=%s,
                           pair_id=%s,
                           last_update_time=NOW()
                       WHERE id=%s""",
                    (
                        json.dumps(success_accounts),
                        json.dumps(failed_accounts),
                        pair_id or signal_id,
                        signal_id,
                    ),
                )
            conn.commit()
            conn.close()
            logging.info(
                f"⚠️ 信号 {signal_id} 部分成功: "
                f"成功={len(success_accounts)}, 失败={len(failed_accounts)}, "
                f"状态=processing（等待恢复）"
            )
        except Exception as e:
            logging.error(f"❌ 更新信号 {signal_id} 部分成功状态失败: {e}")

    async def process_signal(
        self, signal: Dict[str, Any], account_id: str
    ) -> Dict[str, Any]:
        """
        处理单个账户的信号
        :param signal: 信号 dict
        :param account_id: 账户 ID
        :return: { "account_id": xx, "success": bool, "msg": str }
        """
        start_time = time.time()
        try:
            logging.info(f"➡️ 账户 {account_id} 开始处理信号 {signal['id']} ...")

            # Step 1: 校验账户是否可用
            account_info = self.db.account_cache[account_id]
            if not account_info:
                msg = "账户信息不存在"
                logging.warning(f"⚠️ {msg} (account_id={account_id})")
                return {"account_id": account_id, "success": False, "msg": msg}
            side = "buy" if signal["direction"] == "long" else "sell"  # 'buy' 或 'sell'
            # Step 2: 根据信号执行动作
            if (side == "buy" and signal["size"] == 1) or (
                side == "sell" and signal["size"] == -1
            ):  # 开仓
                await self._open_position(account_id, signal, account_info)
            elif (side == "buy" and signal["size"] == 0) or (
                side == "sell" and signal["size"] == 0
            ):  # 平仓
                await self._close_position(account_id, signal, account_info)
            else:
                msg = "未识别的信号类型"
                logging.error(msg)
                return {"account_id": account_id, "success": False, "msg": msg}

            logging.info(f"✅ 账户 {account_id} 完成信号 {signal['id']} 处理")
            return {"account_id": account_id, "success": True, "msg": "OK"}

        except asyncio.TimeoutError:
            msg = "处理超时"
            logging.error(f"⏱️ {msg} (account_id={account_id})")
            return {"account_id": account_id, "success": False, "msg": msg}

        except Exception as e:
            msg = f"异常: {e}"
            logging.error(f"❌ 信号 {signal['id']} 账户 {account_id} 处理失败: {e}")
            return {"account_id": account_id, "success": False, "msg": msg}

    # ----------------- 具体交易逻辑拆分 -----------------
    async def _open_position(self, account_id, signal, account_info):
        """
        开仓
        :param account_id: 账户 ID
        :param signal: 信号 dict
        :param account_info: 账户信息 dict
        :return: None
        """
        try:
            start_time = time.time()
            logging.info(
                f"🟢 [开仓] {account_id} {signal['symbol']} size={signal['size']}"
            )
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            # TODO: 调用交易 API 下单
            strategy_info = await self.db.get_strategy_info(signal["name"])
            # 1.1 开仓前先平掉反向仓位
            await self.cleanup_opposite_positions(
                account_id, signal["symbol"], signal["direction"]
            )

            # 1.2 取消所有未成交的订单
            await cancel_all_orders(
                self, exchange, account_id, signal["symbol"]
            )  # 取消所有未成交的订单

            if os.getenv("IS_LOCAL", "0") == "2":  # 本地调试不执行理财
                # 1.3 处理理财数据进行赎回操作
                await self.handle_financing_redeem(
                    signal, account_id, account_info, exchange
                )

            # 理财状态为2时不开仓
            if account_info.get("financ_state") == 2:
                return
            end_time = time.time()
            # print(f"🟢 账户 {account_id} 信号 {signal['id']} {end_time - start_time:.2f} 秒")
            side = "buy" if signal["direction"] == "long" else "sell"  # 'buy' 或 'sell'
            # 1.3 开仓
            open_position = await self.handle_open_position(
                account_id,
                signal["symbol"],
                signal["direction"],
                side,
                signal["price"],
                strategy_info["open_coefficient"],
            )

            if not open_position:
                logging.error(f"❌ 开仓失败: {signal['id']}, 账户: {account_id}")
                return
            # 1.4 处理记录开仓方向数据
            # has_open_position = await self.db.has_open_position(name, side)
            # if has_open_position:
            logging.info(f"开始记录开仓方向数据: {signal['id']}, 账户: {account_id}")
            await self.db.update_signals_trade_by_id(
                signal["id"],
                {
                    "pair_id": signal["id"],
                    "position_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "count_profit_loss": strategy_info["count_profit_loss"],
                    "stage_profit_loss": strategy_info["stage_profit_loss"],
                },
            )
            end_time = time.time()
            # print(f"🟢 账户 {account_id} 信号 {signal['id']} 开仓处理完成, 耗时 {end_time - start_time:.2f} 秒")
            logging.info(
                f"🟢 账户 {account_id} 信号 {signal['id']} 开仓处理完成, 耗时 {end_time - start_time:.2f} 秒"
            )

            # ✅ 【新增】延迟触发止损检查（避免API拥堵）
            asyncio.create_task(self._delayed_trigger_stop_loss(account_id))

        except Exception as e:
            logging.error(f"❌ 开仓异常: {e}", exc_info=True)

    async def _delayed_trigger_stop_loss(self, account_id: int):
        """
        ✅ 【新增方法】延迟随机触发止损检查

        开仓后不立即触发止损检查，而是随机延迟5-15秒，
        避免多个账户同时开仓后立即触发止损导致API拥堵

        :param account_id: 账户ID
        """
        try:
            import random

            # 随机延迟5-15秒
            delay = random.uniform(5.0, 15.0)
            logging.info(f"⏳ 账户 {account_id} 将在 {delay:.1f}秒后触发止损检查")
            await asyncio.sleep(delay)

            # 触发止损检查
            await self.stop_loss_task.accounts_stop_loss_task(
                account_id, immediate=True
            )

        except Exception as e:
            logging.error(f"❌ 账户 {account_id} 延迟触发止损失败: {e}", exc_info=True)

    async def _close_position(self, account_id, signal, account_info):
        """
        平仓
        :param account_id: 账户 ID
        :param signal: 信号 dict
        :param account_info: 账户信息
        :return: { "account_id": xx, "success": bool, "msg": str }
        """
        try:
            logging.info(f"🔄 [平仓] {account_id} {signal['symbol']}")
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            # TODO: 调用交易 API 平仓
            await cancel_all_orders(
                self, exchange, account_id, signal["symbol"], True
            )  # 取消所有未成交的订单

            # 1.6 平掉反向仓位
            await self.cleanup_opposite_positions(
                account_id, signal["symbol"], signal["direction"]
            )

            if os.getenv("IS_LOCAL", "0") == "2":  # 本地调试不执行购买理财
                # 1.7 进行余币宝理财
                await self.handle_financing_purchase(
                    exchange, account_id, account_info, signal
                )
            await asyncio.sleep(0.1)
        except Exception as e:
            logging.error(f"❌ 平仓异常: {e}", exc_info=True)

    async def handle_financing_redeem(self, signal, account_id, account_info, exchange):
        """
        根据信号和账户的理财状态开仓之前进行处理余币宝赎回 / 资金划转 / 自动借币
        :param signal: 信号 dict
        :param account_id: 账户 ID
        :param account_info: 账户信息 dict
        :param exchange: 交易所对象
        """
        savings_task = SavingsTask(self.db, account_id)

        try:
            financ_state = account_info.get("financ_state")

            # 1️⃣ 理财模式（1: 开启理财, 2: 只做理财）
            if financ_state in (1, 2):
                yubibao_balance = await savings_task.get_saving_balance("USDT")
                market_precision = await get_market_precision(
                    self, exchange, signal["symbol"]
                )

                logging.info(f"余币宝余额: {account_id} {yubibao_balance}")
                if yubibao_balance > 0:
                    await savings_task.redeem_savings("USDT", yubibao_balance)
                else:
                    funding_balance = await get_account_balance(
                        exchange, signal["symbol"], "funding", self.api_limiter
                    )
                    funding_balance_size = funding_balance.quantize(
                        Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
                    )
                    if funding_balance_size > 0:
                        logging.info(
                            f"开始赎回资金账户余额到交易账户: {account_id} {funding_balance_size}"
                        )
                        await savings_task.transfer(
                            "USDT", funding_balance_size, from_acct="6", to_acct="18"
                        )
                    else:
                        logging.info(
                            f"无法赎回资金账户余额到交易账户: {account_id} {funding_balance_size}"
                        )

            # 2️⃣ 借币开仓模式（3: 借币开仓）
            elif financ_state == 3:
                logging.info(f"开始借贷: {account_id} {account_info.get('auto_loan')}")
                if account_info.get("auto_loan") == 0:  # 如果未开启自动借币
                    is_auto_borrow = await savings_task.set_auto_borrow(True)
                    logging.info(f"设置自动借币结果: {is_auto_borrow}")
                    if is_auto_borrow:
                        await self.db.update_account_info(account_id, {"auto_loan": 1})

        except Exception as e:
            logging.error(f"处理理财逻辑失败: account_id={account_id}, error={e}")

    async def handle_financing_purchase(
        self, exchange, account_id, account_info, signal
    ):
        """
        根据信号和账户的理财状态，平仓以后进行购买理财 处理理财购买
        :param exchange: 交易所对象
        :param account_id: 账户 ID
        :param account_info: 账户信息 dict
        :param signal: 信号 dict
        """
        try:
            if account_info.get("financ_state") == 1:  # 理财状态开启
                trading_balance = await get_account_balance(
                    exchange, signal["symbol"], "trading", self.api_limiter
                )
                market_precision = await get_market_precision(
                    self, exchange, signal["symbol"]
                )
                trading_balance_size = trading_balance.quantize(
                    Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
                )

                logging.info(f"交易账户余额: {account_id} {trading_balance_size}")
                if trading_balance_size > 0:
                    logging.info(f"购买理财: {account_id} {trading_balance_size}")
                    savings_task = SavingsTask(self.db, account_id)
                    await savings_task.purchase_savings("USDT", trading_balance_size)
                else:
                    logging.error(
                        f"❌ 无法购买理财: {account_id} {trading_balance_size}"
                    )
        except Exception as e:
            logging.error(f"❌ 购买理财异常: {e}", exc_info=True)

    async def handle_close_position_update(self, signal: dict):
        """处理平仓后数据更新"""
        try:
            sign_id = signal["id"]
            symbol = signal["symbol"]
            name = signal["name"]
            pos_side = signal["direction"]  # 'long' 或 'short'
            side = "buy" if pos_side == "long" else "sell"  # 'buy' 或 'sell'
            size = signal["size"]  # 1, 0, -1
            price = signal["price"]  # 0.00001
            direction = "long" if side == "sell" else "short"
            has_open_position = await self.db.get_latest_signal_by_name_and_direction(
                name, direction
            )
            if has_open_position:
                open_price = Decimal(str(has_open_position["price"]))
                close_price = Decimal(str(price))
                open_side = "buy" if side == "sell" else "sell"
                if open_side == "buy":
                    loss_profit = close_price - open_price
                else:
                    loss_profit = open_price - close_price
                loss_profit_normal = format(loss_profit, "f")
                is_profit = float(loss_profit_normal) > 0

                print(
                    f"处理平仓后数据: {name} {symbol} {side} {size} at {price}, Profit: {loss_profit_normal}, Is Profit: {is_profit}"
                )
                logging.info(
                    f"处理平仓后数据: {name} {symbol} {side} {size} at {price}, Profit: {loss_profit_normal}, Is Profit: {is_profit}"
                )

                # 获取策略表连续几次亏损
                strategy_info = await self.db.get_strategy_info(name)
                logging.info(f"策略信息: {strategy_info}")
                # 计算总盈亏
                count_profit_loss = strategy_info.get("count_profit_loss", 0)  # 总盈亏
                stage_profit_loss = strategy_info.get(
                    "stage_profit_loss", 0
                )  # 阶段性盈亏

                stage_profit_loss_num = float(stage_profit_loss) + float(
                    loss_profit_normal
                )  # 阶段性盈亏累加
                logging.info(f"阶段性盈亏累加: {stage_profit_loss_num}")
                if stage_profit_loss_num > 0:
                    stage_profit_loss_num = 0  # 如果阶段性盈亏大于0才清0

                if float(loss_profit_normal) > 0:  # 盈利
                    logging.info(f"盈利: {loss_profit_normal}")
                    profit_loss = float(count_profit_loss) + float(loss_profit_normal)
                    if profit_loss > 0:
                        logging.info(f"盈利累加大于0: {profit_loss}")
                        count_profit_loss = profit_loss
                    else:
                        count_profit_loss = float(loss_profit_normal)
                        logging.info(f"盈利累加小于0: {count_profit_loss}")
                else:
                    logging.info(f"亏损: {loss_profit_normal}")
                    profit_loss = float(count_profit_loss) + float(loss_profit_normal)
                    logging.info(f"亏损累加: {profit_loss}")
                    count_profit_loss = profit_loss
                    logging.info(f"亏损累加小于0: {count_profit_loss}")

                await self.db.update_max_position_by_tactics(
                    name,
                    is_profit,
                    sign_id,
                    loss_profit_normal,
                    open_price,
                    stage_profit_loss_num,
                )  # 批量更新指定策略所有账户最大仓位数据

                # 更新盈亏策略记录
                await self.db.update_strategy_loss_number(
                    name, count_profit_loss, stage_profit_loss_num
                )
                print(
                    f"策略 {name} 更新总盈亏: {count_profit_loss}, 阶段盈亏: {stage_profit_loss_num}"
                )
                logging.info(
                    f"策略 {name} 更新总盈亏: {count_profit_loss}, 阶段盈亏: {stage_profit_loss_num}"
                )

                strategy_info = await self.db.get_strategy_info(name)
                await self.db.update_signals_trade_by_id(
                    sign_id,
                    {
                        "pair_id": has_open_position["pair_id"],
                        "position_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                        "loss_profit": loss_profit_normal,
                        "count_profit_loss": strategy_info["count_profit_loss"],
                        "stage_profit_loss": strategy_info["stage_profit_loss"],
                    },
                )
        except Exception as e:
            print(f"处理平仓后数据异常: {e}")
            logging.error(f"处理平仓后数据异常: {e}")

    # ---------- 核心子方法 ----------
    def parse_operation(self, action: str, size: int) -> dict:
        """解析信号类型"""
        if action == "buy":
            if size == 1:  # 买入开多
                return {"type": "open", "side": "buy", "direction": "long"}
            elif size == 0:  # 买入平空
                return {"type": "close", "side": "buy", "direction": "short"}
        else:  # sell
            if size == -1:  # 卖出开空
                return {"type": "open", "side": "sell", "direction": "short"}
            elif size == 0:  # 卖出平多
                return {"type": "close", "side": "sell", "direction": "long"}
        raise ValueError(f"无效信号组合: action={action}, size={size}")

    # 平掉一个方向的仓位（双向持仓），并更新数据库订单为已平仓
    async def cleanup_opposite_positions(
        self, account_id: int, symbol: str, direction: str
    ):
        """
        平掉一个方向的仓位（双向持仓），并更新数据库订单为已平仓

        ✅ 修复 P1：增加容错和Event Loop错误重试
        """
        max_retries = 3
        retry_delay = 1.0  # 初始延迟1秒
        exchange = None  # ✅ 提前初始化，防止finally块崩溃

        for attempt in range(max_retries):
            try:
                exchange = await get_exchange(self, account_id)
                if not exchange:
                    logging.warning(f"⚠️ 账户 {account_id} 无法获取交易所对象")
                    return

                positions = await fetch_current_positions(
                    self, account_id, symbol, "SWAP"
                )
                if not positions:
                    logging.warning(f"✓ 无持仓信息 用户 {account_id}")
                    return

                opposite_direction = "short" if direction == "long" else "long"
                total_size = Decimal("0")

                for pos in positions:
                    pos_side = pos.get("side") or pos.get("posSide") or ""
                    pos_size = Decimal(
                        str(pos.get("contracts") or pos.get("positionAmt") or 0)
                    )
                    if pos_size == 0 or pos_side.lower() != opposite_direction:
                        continue
                    total_size += abs(pos_size)

                if total_size == 0:
                    logging.info(f"✓ 无反向持仓需要平仓：{opposite_direction}")
                    return

                close_side = "sell" if opposite_direction == "long" else "buy"
                market_price = await get_market_price(
                    exchange, symbol, self.api_limiter, close_exchange=False
                )
                client_order_id = await get_client_order_id()

                close_order = await open_position(
                    self,
                    account_id,
                    symbol,
                    close_side,
                    opposite_direction,
                    float(total_size),
                    None,
                    "market",
                    client_order_id,
                    True,  # reduceOnly=True
                )

                if close_order:
                    await self.db.add_order(
                        {
                            "account_id": account_id,
                            "symbol": symbol,
                            "order_id": close_order["id"],
                            "clorder_id": client_order_id,
                            "price": float(market_price),
                            "executed_price": None,
                            "quantity": float(total_size),
                            "pos_side": opposite_direction,
                            "order_type": "market",
                            "side": close_side,
                            "status": "filled",
                            "is_clopos": 1,
                            "position_group_id": "",
                        }
                    )

                    await self.db.mark_orders_as_closed(
                        account_id, symbol, opposite_direction
                    )
                    logging.info(
                        f"✓ 用户 {account_id} 成功平掉{opposite_direction}方向总持仓：{total_size}"
                    )
                    return  # 成功后退出重试循环

                else:
                    logging.warning(
                        f"✗ 用户 {account_id} 平仓失败，方向: {opposite_direction}，数量: {total_size}"
                    )
                    return  # 平仓失败，退出重试

            except Exception as e:
                error_msg = str(e)

                # ✅ 特殊处理 Event Loop 错误（可重试）
                if (
                    "attached to a different loop" in error_msg
                    and attempt < max_retries - 1
                ):
                    wait_time = retry_delay * (2**attempt)
                    logging.warning(
                        f"⚠️ 账户 {account_id} Event Loop错误，{wait_time:.1f}秒后重试 ({attempt+1}/{max_retries})"
                    )
                    await asyncio.sleep(wait_time)
                    continue

                # 检查是否是频率限制错误
                elif (
                    "50011" in error_msg or "Too Many Requests" in error_msg
                ) and attempt < max_retries - 1:
                    wait_time = retry_delay * (2**attempt)  # 指数退避：1s, 2s, 4s
                    logging.warning(
                        f"⚠️ 账户 {account_id} 平仓触发频率限制，{wait_time:.1f}秒后重试 ({attempt+1}/{max_retries})"
                    )
                    await asyncio.sleep(wait_time)
                    continue  # 继续下一次重试
                else:
                    # 非重试类错误或已达到最大重试次数
                    logging.error(
                        f"✗ 账户 {account_id} 清理反向持仓出错: {e}", exc_info=True
                    )
                    return

            finally:
                # ✅ 只在exchange存在时才关闭
                if exchange:
                    try:
                        await exchange.close()
                    except Exception as close_error:
                        logging.debug(f"⚠️ 关闭交易所连接出错: {close_error}")

    async def handle_open_position(
        self,
        account_id: int,
        symbol: str,
        pos_side: str,
        side: str,
        price: Decimal,
        open_coefficient: Decimal,
    ):
        try:
            """处理开仓"""
            # print(f"⚡ 开仓操作: {account_id} {pos_side} {side} {price} {symbol}")
            logging.info(
                f"⚡ 开仓操作: {account_id} {pos_side} {side} {price} {symbol}"
            )
            exchange = await get_exchange(self, account_id)
            # 1. 平掉反向仓位
            # await self.cleanup_opposite_positions(account_id, symbol, pos_side)
            total_position_value = await get_total_positions(
                self, account_id, symbol, "SWAP"
            )  # 获取总持仓价值
            # print("总持仓数", total_position_value)
            logging.info(f"用户 {account_id} 总持仓数：{total_position_value}")
            if total_position_value is None:
                # print(f"总持仓数获取失败")
                logging.error(f"用户 {account_id} 总持仓数获取失败")
                return
            market_precision = await get_market_precision(
                self, exchange, symbol
            )  # 获取市场精度

            total_position_quantity = 0
            if total_position_value > 0:
                total_position_quantity = (
                    Decimal(total_position_value)
                    * Decimal(market_precision["amount"])
                    * price
                )  # 计算总持仓价值
                # print("总持仓价值", total_position_quantity)
                logging.info(f"用户 {account_id} 总持仓价值：{total_position_quantity}")

            # 2. 计算开仓量
            # price = await get_market_price(exchange, symbol)
            commission_price_difference = Decimal(
                self.db.account_config_cache[account_id].get(
                    "commission_price_difference"
                )
            )
            price_float = price * (
                commission_price_difference / 100
            )  # 计算价格浮动比例
            # print("价格浮动比例", price_float, commission_price_difference)
            if pos_side == "short":  # 做空
                price = price - price_float  # 信号价 - 价格浮动比例
            elif pos_side == "long":  # 做多
                price = price + price_float  # 信号价 + 价格浮动比例

            balance = await get_account_balance(
                exchange, symbol, "trading", self.api_limiter
            )
            # print(f"账户余额: {balance}")
            logging.info(f"用户 {account_id} 账户余额: {balance}")
            if balance is None:
                print(f"用户 {account_id} 账户余额获取失败")
                logging.error(f"用户 {account_id} 账户余额获取失败")
                return

            max_position = await get_max_position_value(
                self, account_id, symbol
            )  # 获取配置文件对应币种最大持仓
            position_percent = Decimal(
                self.db.account_config_cache[account_id].get("position_percent")
            )
            # max_balance = max_position * position_percent #  最大仓位数 * 开仓比例
            # if balance >= max_balance: # 超过最大仓位限制
            #     balance = max_position
            # print(f"最大开仓数量: {max_balance}")
            logging.info(
                f"用户 {account_id} 最大开仓数量: {max_position} 开仓系数: {open_coefficient}"
            )
            size = await self.calculate_position_size(
                market_precision,
                max_position,
                position_percent,
                price,
                account_id,
                pos_side,
                open_coefficient,
            )
            # print(f"开仓价: {price}")
            logging.info(
                f"用户 {account_id} 开仓价: {price} 开仓比例: {position_percent}"
            )
            # print(f"开仓量: {size}")
            print(f"用户 {account_id} 开仓量: {size} {market_precision['amount']}")
            logging.info(
                f"用户 {account_id} 开仓量: {size} {market_precision['amount']}"
            )
            # logging.info(f"开仓量: {size}")
            size_total_quantity = (
                Decimal(size) * Decimal(market_precision["amount"]) * price
            )
            # print(f"开仓价值: {size_total_quantity}")
            logging.info(f"用户 {account_id} 开仓价值: {size_total_quantity}")
            if size <= 0:
                # print(f"开仓量为0，不执行开仓")
                logging.info(f"用户 {account_id} 开仓量为0，不执行开仓")
                return

            # 3. 判断当前币种是否超过最大持仓
            # if size_total_quantity >= max_position:
            #     print(f"开仓量超过最大仓位限制，不执行开仓")
            #     logging.info(f"开仓量超过最大仓位限制，不执行开仓")
            #     return

            # 4. 判断所有仓位是否超过最大持仓量
            total_size_position_quantity = 0
            if total_position_quantity > 0:
                total_size_position_quantity = Decimal(
                    total_position_quantity
                ) + Decimal(size_total_quantity)

            # print("开仓以及总持仓价值", total_size_position_quantity)
            logging.info(
                f"用户 {account_id} 开仓以及总持仓价值：{total_size_position_quantity}"
            )
            if (
                total_size_position_quantity >= max_position
            ):  # 总持仓价值大于等于最大持仓
                logging.info(f"用户 {account_id} 最大持仓数：{max_position}")
                # print(f"最大持仓数：{max_position}")
                logging.info(f"用户 {account_id} 总持仓数大于等于最大持仓，不执行挂单")
                # print(f"总持仓数大于等于最大持仓，不执行挂单")
                return

            # 3. 获取市场价格
            client_order_id = await get_client_order_id()
            # 4. 下单并记录
            order = await open_position(
                self,
                account_id,
                symbol,
                side,
                pos_side,
                float(size),
                float(price),
                "limit",
                client_order_id,
            )
            # print("order", order)
            if order:
                logging.info(f"用户 {account_id} 交易所开仓成功，开始记录订单")
                await self.db.add_order(
                    {
                        "account_id": account_id,
                        "symbol": symbol,
                        "order_id": order["id"],
                        "clorder_id": client_order_id,
                        "price": float(price),
                        "executed_price": None,
                        "quantity": float(size),
                        "pos_side": pos_side,
                        "order_type": "limit",
                        "side": side,
                        "status": "live",
                        "position_group_id": "",
                    }
                )
                logging.info(f"用户 {account_id} 开仓成功，订单记录成功")
                return True
            else:
                # print(f"用户 {account_id} 开仓失败")
                logging.error(
                    f"❌ 用户 {account_id} 交易所开仓失败: open_position 返回 None，"
                    f"可能原因: 余额不足、持仓限制或API错误，"
                    f"symbol={symbol}, side={side}, pos_side={pos_side}, price={price}, size={size}"
                )
                return False
        except Exception as e:
            # print(f"用户 {account_id} 开仓异常: {e}")
            logging.error(
                f"❌ 用户 {account_id} 开仓异常: {e}，"
                f"symbol={symbol}, side={side}, pos_side={pos_side}, price={price}, size={size}",
                exc_info=True,
            )
            return False
        finally:
            await exchange.close()

    # 计算仓位大小
    async def calculate_position_size(
        self,
        market_precision: object,
        balance: Decimal,
        position_percent: Decimal,
        price: float,
        account_id: int,
        pos_side: str,
        open_coefficient: Decimal,
    ) -> Decimal:
        """
        计算仓位大小
        :param market_precision: 市场精度
        :param balance: 账户余额
        :param position_percent: 开仓比例
        :param price: 开仓价
        :param account_id: 账户 ID
        :param pos_side: 仓位方向
        :param open_coefficient: 开仓系数
        :return: Decimal 类型的仓位大小
        """
        try:
            # market_precision = await get_market_precision(exchange, symbol, 'SWAP')
            # print("market_precision", market_precision, price)
            open_coefficient_num = 1
            if pos_side == "short":  # 做空
                open_coefficient_num = open_coefficient  # 空单系数
            position_size = (balance * position_percent * open_coefficient_num) / (
                price * Decimal(market_precision["contract_size"])
            )
            position_size = position_size.quantize(
                Decimal(market_precision["amount"]), rounding="ROUND_DOWN"
            )

            # total_position = Decimal(self.db.account_config_cache[account_id].get('total_position', 0)) # 获取配置文件对应币种最大持仓
            # return min(position_size, total_position)
            return position_size
        except Exception as e:
            print(f"用户 {account_id} 计算仓位失败: {e}")
            logging.error(f"用户 {account_id} 计算仓位失败: {e}")
            return Decimal("0")
