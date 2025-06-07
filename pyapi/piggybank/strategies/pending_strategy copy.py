from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple
from zoneinfo import ZoneInfo

from pyapi.piggybank.db.models import Piggybank
from pyapi.piggybank.strategies.balance_strategy import BalanceStrategy
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide, OrderStatus
from utils.helpers import generate_client_order_id

class PendingStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol: str) -> bool:
        """
        执行下单策略：
         1. 获取估值信息
         2. 计算涨跌幅比率，若低于阈值，则停止下单
         3. 根据 BTC 与 USDT 估值差异判断下单方向（卖出或买入市场单）
         4. 下单后取回成交数据、计算配对信息及利润，写入数据库
        """
        try:
            normalized_symbol = self.exchange.normalize_symbol(symbol)
            return self._place_market_order(normalized_symbol)
        except Exception as e:
            print(f"[{self.get_exchange_name()}] 市场下单策略执行错误: {str(e)}")
            return False

    def _place_market_order(self, symbol: str) -> bool:
        # 1. 获取账户估值（btc_balance, usdt_balance, btc_valuation, usdt_valuation, btc_price）
        valuation = self._get_valuation(symbol)
        btc_balance = Decimal(valuation['btc_balance'])
        usdt_balance = Decimal(valuation['usdt_balance'])
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])
        btc_price = Decimal(valuation['btc_price'])

        # 2. 获取上次平衡状态下的估值（这里要求开发者自行实现该方法，若无则采用 usdt_valuation 作为基准）
        last_balanced = self._get_last_balanced_valuation(symbol)
        if last_balanced is None or Decimal(last_balanced) <= 0:
            # 如果没有历史平衡估值，则使用当前 USDT 估值作为基准
            last_balanced = usdt_valuation

        # 计算涨跌幅比例（%）
        change_ratio = (abs(btc_valuation - usdt_valuation) / Decimal(last_balanced)) * 100

        if change_ratio <= Decimal(self.config.CHANGE_RATIO_THRESHOLD):
            print(f"[{self.get_exchange_name()}] 涨跌幅度 {change_ratio:.2f}% 未超过阈值 {self.config.CHANGE_RATIO_THRESHOLD}%，停止下单")
            return False

        print(f"[{self.get_exchange_name()}] 涨跌幅度 {change_ratio:.2f}% 超过阈值，开始下单")

        # 3. 生成客户订单号（格式参考 PHP：Zx + 日期 + 随机数）
        client_order_id = generate_client_order_id('Zx')

        # 4. 获取市场信息（含最小下单量、货币对信息等）
        market_info = self.exchange.get_market_info(symbol)
        min_size = Decimal(market_info['info'].get('minSz', 0))
        base_ccy = market_info['info'].get('baseCcy', symbol.split('-')[0])
        quote_ccy = market_info['info'].get('quoteCcy', symbol.split('-')[1])

        # 5. 根据配置中平衡比例 "1:1" 拆分（如有其它比例可按配置调整）
        ratio_parts = [Decimal(x) for x in self.config.BALANCE_RATIO.split(':')]

        # 6. 根据 BTC 与 USDT 估值差异确定下单方向
        # 若 BTC 估值 > USDT，则进行卖出 BTC ；否则买入 BTC
        if btc_valuation > usdt_valuation:
            # 卖单：按比例计算卖出额（与 PHP 中 $btcSellNum 类似）
            diff = btc_valuation - usdt_valuation
            sell_value = ratio_parts[0] * (diff / (ratio_parts[0] + ratio_parts[1]))
            sell_amount = sell_value / btc_price  # 卖出数量（以 BTC 计）
            order_side = OrderSide.SELL.value
            order_type_num = 2
            print(f"[{self.get_exchange_name()}] 准备卖出 BTC, 数量: {sell_amount}, 最小下单量: {min_size}")
            if sell_amount < min_size:
                print(f"[{self.get_exchange_name()}] 卖单数量 {sell_amount} 小于最小下单量 {min_size}，停止下单")
                return False
            # 下单：市场卖单
            result = self.exchange.create_trade_order(
                symbol=symbol,
                clOrdId=client_order_id,
                order_type='market',
                side='sell',
                amount=float(sell_amount),
                price=None,
                params={}
            )
            order_amount = sell_amount
        else:
            # 买单：按比例计算买入金额（与 PHP 中 $usdtBuyNum 类似）
            diff = usdt_valuation - btc_valuation
            buy_value = ratio_parts[1] * (diff / (ratio_parts[0] + ratio_parts[1]))
            buy_amount = buy_value  # 买入时金额直接为 USDT 数量
            order_side = OrderSide.BUY.value
            order_type_num = 1
            print(f"[{self.get_exchange_name()}] 准备买入 BTC, 金额: {buy_amount}, 最小下单量: {min_size}")
            if buy_amount < min_size:
                print(f"[{self.get_exchange_name()}] 买单金额 {buy_amount} 小于最小下单量 {min_size}，停止下单")
                return False
            # 下单：市场买单
            result = self.exchange.create_trade_order(
                symbol=symbol,
                clOrdId=client_order_id,
                order_type='market',
                side='buy',
                amount=float(buy_amount),
                price=None,
                params={}
            )
            order_amount = buy_amount

        # 7. 判断下单结果
        if result.get('sCode', -1) != 0:
            print(f"[{self.get_exchange_name()}] 下单失败, 响应: {result}")
            return False

        order_id = result.get('ordId')
        print(f"[{self.get_exchange_name()}] 下单成功, 订单号: {order_id}")

        # 8. 获取订单详情，参照 PHP 用 fetch_trade_order 获取成交信息
        order_details = self.exchange.fetch_trade_order(symbol, client_order_id, None)
        # 解析成交详情：累计成交数量、成交均价、最新成交价格
        filled_amount = Decimal(order_details.get('accFillSz', 0))
        avg_price = Decimal(order_details.get('avgPx', btc_price))
        # 若 fillPx 存在则作为最新成交价，否则使用成交均价
        deal_price = Decimal(order_details.get('fillPx', avg_price))

        # 9. 配对信息（调用 BalanceStrategy 中已有方法）
        pair_id, profit = self._get_pair_info(order_side, avg_price, filled_amount, symbol)

        # 10. 获取下单后新的估值
        new_valuation = self._get_valuation(symbol)

        # 11. 构建订单数据，写入数据库（字段参考 PHP 代码，包含余额、成交均价、配对等信息）
        order_data = {
            'exchange': self.get_exchange_name(),
            'product_name': symbol,
            'order_id': order_id,
            'order_number': client_order_id,
            'td_mode': 'cross',
            'base_ccy': base_ccy,
            'quote_ccy': quote_ccy,
            'type': order_type_num,  # 1 为买单，2 为卖单
            'order_type': 'market',
            'amount': float(order_amount),
            'clinch_number': float(filled_amount),
            'price': float(deal_price),
            'profit': float(profit),
            'pair': pair_id,
            'currency1': new_valuation['btc_balance'],
            'currency2': new_valuation['usdt_balance'],
            'balanced_valuation': new_valuation['usdt_valuation'],
            'make_deal_price': float(avg_price),
            'time': datetime.now()
        }
        self.crud.create_piggybank(order_data)
        print(f"[{self.get_exchange_name()}] 写入订单数据成功")

        # 12. 若找到配对订单，则更新其状态
        if pair_id:
            self.crud.db.query(Piggybank).filter(Piggybank.id == pair_id).update({
                'pair': order_id,
                'profit': profit
            })
            self.crud.db.commit()
            print(f"[{self.get_exchange_name()}] 配对更新成功")
        return True

    def _get_last_balanced_valuation(self, symbol: str) -> Optional[Decimal]:
        """
        获取上一次平衡状态下的估值，具体实现依赖业务逻辑。
        可查询数据库中上一次记录的 balanced_valuation 字段。
        若没有历史记录，返回 None
        """
        last_record = self.crud.get_last_balanced_valuation(self.get_exchange_name(), symbol)
        if last_record:
            return Decimal(last_record['balanced_valuation'])
        return None

    # 复用 BalanceStrategy 中的 _get_valuation 和 _get_pair_info 方法
    _get_valuation = BalanceStrategy._get_valuation
    _get_pair_info = BalanceStrategy._get_pair_info
