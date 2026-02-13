import asyncio
import logging
from decimal import Decimal
from database import Database
from common_functions import get_account_balance, get_exchange


class BalanceSyncTask:
    """账户余额同步任务

    每小时自动同步一次所有账户的总余额到账户表
    总余额 = 交易账户余额 + 资金账户余额 + 余币宝余额
    """

    def __init__(self, db: Database):
        self.db = db
        self.sync_interval = 3600  # 同步间隔：3600秒 = 1小时

    async def balance_sync_task(self):
        """后台定时同步任务

        持续运行，每小时同步一次所有账户的总余额
        """
        logging.info("启动账户余额同步任务...")

        while True:
            try:
                await self._sync_all_balances()
            except Exception as e:
                logging.error(f"余额同步任务异常: {e}", exc_info=True)

            # 等待下一个同步周期
            await asyncio.sleep(self.sync_interval)

    async def _sync_all_balances(self):
        """同步所有账户的总余额

        并发获取所有账户的余额数据，失败的账户不会阻止其他账户的同步
        """
        try:
            # 获取所有启用的账户
            account_ids = await self.db.get_all_active_accounts()

            if not account_ids:
                logging.warning("没有找到启用的账户")
                return

            logging.info(f"开始同步 {len(account_ids)} 个账户的余额...")

            # 并发同步所有账户
            tasks = [
                self._sync_single_account_balance(account_id)
                for account_id in account_ids
            ]

            results = await asyncio.gather(*tasks, return_exceptions=True)

            # 统计同步结果
            success_count = sum(1 for r in results if r is True)
            error_count = sum(1 for r in results if isinstance(r, Exception))

            logging.info(
                f"余额同步完成: 成功={success_count}, 失败={error_count}, 总数={len(account_ids)}"
            )

        except Exception as e:
            logging.error(f"同步所有账户余额失败: {e}", exc_info=True)

    async def _sync_single_account_balance(self, account_id: int) -> bool:
        """同步单个账户的总余额

        计算三类余额的总和后保存到账户表

        Args:
            account_id: 账户ID

        Returns:
            同步是否成功
        """
        try:
            logging.debug(f"正在同步账户 {account_id} 的余额...")

            # 获取交易所实例
            exchange = await get_exchange(self, account_id)
            if not exchange:
                logging.warning(f"无法获取账户 {account_id} 的交易所实例")
                return False

            # 获取各类余额
            trading_balance = await get_account_balance(exchange, None, "trading")
            funding_balance = await get_account_balance(exchange, None, "funding")

            # 获取余币宝余额
            from savings_task import SavingsTask

            savings_task = SavingsTask(self.db, account_id)
            yubibao_balance = await savings_task.get_saving_balance("USDT")

            # ✨ 计算总余额（三类余额相加）
            total_balance = (
                Decimal(str(trading_balance))
                + Decimal(str(funding_balance))
                + Decimal(str(yubibao_balance))
            )

            # ✨ 直接保存到账户表的 total_balance 字段
            success = await self.db.update_account_total_balance(
                account_id=account_id,
                total_balance=total_balance,
            )

            if success:
                logging.info(
                    f"账户 {account_id} 总余额同步成功: {total_balance} "
                    f"(交易={trading_balance} + 资金={funding_balance} + 余币宝={yubibao_balance})"
                )
                return True
            else:
                logging.warning(f"账户 {account_id} 余额保存失败")
                return False

        except Exception as e:
            logging.error(f"同步账户 {account_id} 余额失败: {e}", exc_info=True)
            return False
