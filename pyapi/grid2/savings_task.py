import os
from okx import Funding
from okx.Finance import Savings
import logging


class SavingsTask:
    """理财任务类：购买和赎回储蓄"""

    def __init__(self, db, account_id: str):
        """
        初始化时只接收 account_id
        :param db: 数据库实例（必须实现 get_account_info 方法）
        :param account_id: 用户账户 ID
        """
        self.db = db
        self.account_id = account_id
        self.savingsAPI = None
        self.financeAPI = None

    async def init_api(self):
        """根据 account_id 初始化 OKX API 客户端"""
        account_info = await self.db.get_account_info(self.account_id)
        if not account_info:
            raise ValueError(f"未找到 account_id={self.account_id} 的账户信息")

        api_key = account_info["api_key"]
        secret_key = account_info["api_secret"]
        passphrase = account_info["api_passphrase"]

        proxy_conf = None
        if os.getenv("IS_LOCAL", "0") == "1":  # 本地调试启用代理
            proxy_conf = "http://127.0.0.1:7890"
            logging.info("本地调试模式：启用代理")

        is_simulation = os.getenv("IS_SIMULATION", '1')
        self.financeAPI = Funding.FundingAPI(api_key, secret_key, passphrase, False, is_simulation, proxy=proxy_conf)
        # print("self.financeAPI", self.financeAPI)
        self.savingsAPI = Savings.SavingsAPI(api_key, secret_key, passphrase, False, is_simulation, proxy=proxy_conf)
        # print("self.savingsAPI", self.savingsAPI)

    async def transfer(self, ccy: str, amt: float, from_acct: str, to_acct: str):
        """资金划转"""
        # print(f"资金划转: {amt} {ccy} {from_acct} → {to_acct}")
        logging.info(f"资金划转: {amt} {ccy} {from_acct} → {to_acct}")
        try:
            result = self.financeAPI.funds_transfer(
                ccy=ccy,
                amt=str(amt),
                from_=from_acct,
                to=to_acct,
                type="0"  # 内部划转
            )
            # print(f"划转结果: {result}")
            logging.info(f"划转结果: {result}")
            return result
        except Exception as e:
            # print(f"划转失败: {e}")
            logging.error(f"划转失败: {e}")
            raise e

    async def purchase_savings(self, ccy: str, amt: float):
        """购买理财：先转账再申购"""
        if not self.financeAPI:
            await self.init_api()

        # Step 1: 资金划转 (交易账户 → 理财账户)
        await self.transfer(ccy, amt, from_acct="18", to_acct="6")

        # saving_balance = await self.get_saving_balance(ccy)
        # print(f"当前余币宝余额: {saving_balance}")
        # logging.info(f"当前余币宝余额: {saving_balance}")

        # Step 2: 申购理财
        logging.info(f"购买理财: {amt} {ccy}")
        try:
            result = self.savingsAPI.savings_purchase_redemption(
                ccy=ccy,
                side="purchase",
                amt=str(amt),
                rate="0.01"  # 市价购买
            )
            # print(f"购买结果: {result}")
            logging.info(f"购买结果: {result}")
        except Exception as e:
            # print(f"购买理财失败: {e}")
            logging.error(f"购买理财失败: {e}")
            raise e
        return result

    async def redeem_savings(self, ccy: str, amt: float):
        """赎回理财：先赎回再转账"""
        if not self.savingsAPI:
            await self.init_api()

        saving_balance = await self.get_saving_balance(ccy)
        print(f"当前余币宝余额: {saving_balance}")
        logging.info(f"当前余币宝余额: {saving_balance}")

        # Step 1: 赎回
        logging.info(f"赎回理财: {amt} {ccy}")
        result = self.savingsAPI.savings_purchase_redemption(
            ccy=ccy,
            side="redempt",
            amt=str(amt),
            rate="0.01"  # 市价赎回
        )
        # print(f"赎回结果: {result}")
        logging.info(f"赎回结果: {result}")
        # return
        # Step 2: 资金划转 (理财账户 → 交易账户)
        await self.transfer(ccy, amt, from_acct="6", to_acct="18")

        return result
    
    #获取余币宝余额
    async def get_saving_balance(self, ccy: str):
        """获取余币宝余额"""
        if not self.savingsAPI:
            await self.init_api()
        try:
            result = self.savingsAPI.get_saving_balance(ccy=ccy)
            if 'data' in result and len(result['data']) > 0:
                for item in result['data']:
                    if item['ccy'] == ccy:
                        result = float(item['amt'])
                        break
                    else:
                        result = 0
            else:
                result = 0
            # print(f"余币宝余额: {result}")
            # logging.info(f"余币宝余额: {result}")
            return result
        except Exception as e:
            # print(f"获取余币宝余额失败: {e}")
            logging.error(f"获取余币宝余额失败: {e}")
            raise e