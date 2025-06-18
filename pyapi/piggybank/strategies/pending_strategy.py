from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple
from zoneinfo import ZoneInfo

from pyapi.piggybank.db.models import Piggybank
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id


class PendingStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol: str) -> bool:
        """
        执行平衡仓位策略：
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
        exchange = self.get_exchange_name()
        change_ratio = self.config.CHANGE_RATIO #涨跌比例
        btc_balance = Decimal(valuation['btc_balance'])
        usdt_balance = Decimal(valuation['usdt_balance'])
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])
        btc_price = Decimal(valuation['btc_price'])
        print("btc_valuation", btc_valuation, "usdt_valuation", usdt_valuation)
        # 2. 获取上次平衡状态下的估值（这里要求开发者自行实现该方法，若无则采用 usdt_valuation 作为基准）
        last_balanced_valuation = self.crud.get_last_balanced_valuation(exchange, symbol)  # 获取上次平衡状态下的估值
        print("last_balanced_valuation", last_balanced_valuation)
        if last_balanced_valuation > 0:
            # 如果有历史平衡估值，计算涨跌幅比例（%）
            change_ratio_num = abs(btc_valuation - usdt_valuation) / Decimal(last_balanced_valuation) * Decimal('100')
        else:
            # 如果没有历史平衡估值，计算涨跌幅比例（%）
            change_ratio_num = abs(btc_valuation / usdt_valuation)
        
        VALUATION_THRESHOLD = Decimal(str(self.config.VALUATION_THRESHOLD))
        if change_ratio_num <= VALUATION_THRESHOLD:
            print(f"[{exchange}] 涨跌幅度 {change_ratio_num}% 未超过阈值 {VALUATION_THRESHOLD}%，停止下单")
            return False

        print(f"[{exchange}] 涨跌幅度 {change_ratio_num}% 超过阈值，开始下单")

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
        order_amount = 0
        if btc_valuation > usdt_valuation:
            # 卖单：按比例计算卖出额（与 PHP 中 $btcSellNum 类似）
            diff = btc_valuation - usdt_valuation
            sell_value = ratio_parts[0] * (diff / (ratio_parts[0] + ratio_parts[1]))
            sell_amount_number = Decimal(sell_value) / Decimal(btc_price)

            # 格式化为 8 位小数字符串
            sell_amount = format(sell_amount_number, '.8f')
            order_side = OrderSide.SELL.value
            order_type_num = 2
            print(f"[{self.get_exchange_name()}] 准备卖出 BTC, 数量: {sell_amount}, 最小下单量: {min_size}")
            if Decimal(sell_amount) < min_size:
                print(f"[{self.get_exchange_name()}] 卖单数量 {sell_amount} 小于最小下单量 {min_size}，停止下单")
                return False
            # 下单：市场卖单
            result = self.exchange.create_order(
                symbol=symbol,
                order_type=OrderType.MARKET.value,
                side=OrderSide.SELL.value,
                amount=float(sell_amount),
                price=None,
                params={'clOrdId': client_order_id, 'tdMode': 'cross'}
            )
            order_amount = Decimal(sell_amount)
        else:
            # 买单：按比例计算买入金额（与 PHP 中 $usdtBuyNum 类似）
            diff = usdt_valuation - btc_valuation
            buy_value = ratio_parts[1] * (diff / (ratio_parts[0] + ratio_parts[1]))
            # buy_amount_number = Decimal(buy_value) / Decimal(btc_price)
            buy_amount = Decimal(buy_value)
            # buy_amount = buy_amount_number, '.8f')
            print(f"[{exchange}] 计算买入金额: {buy_amount}, BTC 价格: {btc_price}")
            order_side = OrderSide.BUY.value
            order_type_num = 1
            print(f"[{exchange}] 准备买入 BTC, 金额: {buy_amount}, 最小下单量: {min_size}")
            if Decimal(buy_amount) < min_size:
                print(f"[{exchange}] 买单金额 {buy_amount} 小于最小下单量 {min_size}，停止下单")
                return False
            # 下单：市场买单
            result = self.exchange.create_order(
                symbol=symbol,
                order_type=OrderType.MARKET.value,
                side=OrderSide.BUY.value,
                amount=float(buy_amount),
                price=None,
                params={'clOrdId': client_order_id, 'tdMode': 'cross'}
            )
            order_amount = Decimal(buy_amount)
        # 7. 判断下单结果
        sCode = int(result.get('info', {}).get('sCode', -1))
        print(f"[{exchange}] 下单响应 sCode: {sCode}")
        if sCode != 0:
            print(f"[{exchange}] 下单失败, 响应111: {result}")
            return False

        order_id = result.get('info', {}).get('ordId')
        print(f"[{exchange}] 下单成功, 订单号: {order_id}")

        # 8. 获取订单详情，参照 PHP 用 fetch_trade_order 获取成交信息
        order_details = self.exchange.fetch_order(order_id, symbol)
        # 解析成交详情：累计成交数量、成交均价、最新成交价格
        filled_amount = Decimal(order_details.get('accFillSz', 0))
        avg_price = Decimal(order_details.get('avgPx', btc_price))
        # 若 fillPx 存在则作为最新成交价，否则使用成交均价
        deal_price = Decimal(order_details.get('fillPx', avg_price))
        # 9. 配对信息（调用 BalanceStrategy 中已有方法）

        pair_arr = self.crud.get_pair_and_calculate_profit(
            exchange=exchange,
            type=1,  # 卖单类型
            deal_price=float(deal_price)
        )

        # 10. 获取下单后新的估值
        new_valuation = self._get_valuation(symbol)

        # 11. 构建订单数据，写入数据库（字段参考 PHP 代码，包含余额、成交均价、配对等信息）
        order_data = {
            'exchange': exchange,
            'product_name': symbol,
            'order_id': order_id,
            'order_number': client_order_id,
            'td_mode': 'cross',
            'base_ccy': base_ccy,
            'quote_ccy': quote_ccy,
            'type': order_type_num,  # 1 为买单，2 为卖单
            'order_type': 'market',
            'amount': order_amount,
            'clinch_number': filled_amount,
            'price': deal_price,
            'profit': pair_arr['profit'] if pair_arr and pair_arr['profit'] else 0,
            'pair': pair_arr['pair_id'] if pair_arr and pair_arr['pair_id'] else 0,
            'currency1': new_valuation['btc_balance'],
            'currency2': new_valuation['usdt_balance'],
            'balanced_valuation': new_valuation['usdt_valuation'],
            'make_deal_price': float(avg_price),
            'time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'up_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        }
        self.crud.create_piggybank(order_data)
        print(f"[{exchange}] 写入订单数据成功")

        # 12. 若找到配对订单，则更新其状态
        if pair_arr:
            is_pair = self.crud.update_pair_and_profit(pair_arr['pair_id'], float(pair_arr['profit']))
            if is_pair:
                self.crud.db.commit()
            print(f"[{exchange}] 配对更新成功")
        return True
