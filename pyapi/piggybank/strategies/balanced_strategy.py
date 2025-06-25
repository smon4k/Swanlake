# 吃单策略 平衡策略
from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple
from pyapi.piggybank.db.models import Config
from pyapi.piggybank.strategies.base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id


class BalancedStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol: str) -> bool:
        try:
            normalized_symbol = self.exchange.normalize_symbol(symbol)
            return self._place_market_order(normalized_symbol)
        except Exception as e:
            print(f"[{self.get_exchange_name()}] 市场下单策略执行错误: {str(e)}")
            return False

    def _place_market_order(self, symbol: str) -> bool:
        """
        下单主流程：拆分为估值检查 -> 参数构造 -> 下单 -> 结果解析 -> 入库处理。
        """
        valuation = self._get_valuation(symbol)
        config = self.crud.get_config()
        if not self._should_place_order(symbol, valuation, config):
            return False

        # 2. 获取市场信息（最小下单量、币对基础/计价币种）
        market_info, base_ccy, quote_ccy, min_size = self._get_market_info(symbol)

        # 3. 构建订单参数（方向、数量、价格）
        order_params = self._build_order_parameters(symbol, valuation, min_size, config)
        if not order_params:
            return False

        # 4. 提交订单
        result = self._submit_order(symbol, order_params)
        if not result:
            return False

        # 5. 获取成交详情
        order_id = result.get('info', {}).get('ordId')
        order_details = self.exchange.fetch_order(order_id, symbol)
        filled_amount, avg_price, deal_price = self._parse_order_details(order_details, valuation)

        # 6. 最终处理：写入数据库 + 利润配对处理
        self._finalize_order(symbol, valuation, market_info, order_params, order_id, filled_amount, avg_price, deal_price)
        return True

    def _should_place_order(self, symbol, valuation: Dict[str, Decimal], config: Config) -> bool:
        """
        判断当前 BTC 与 USDT 估值差异是否超过阈值，决定是否下单。
        """
        exchange = self.get_exchange_name()
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])

        # 计算估值总额（用于归一化）
        total_valuation = btc_valuation + usdt_valuation
        if total_valuation == 0:
            print(f"[{exchange}] 当前估值为 0，跳过下单判断")
            return False

        # 计算估值差异占总估值的百分比
        delta = abs(btc_valuation - usdt_valuation)
        change_ratio = (delta / min(usdt_valuation, btc_valuation)) * Decimal('100')

        # 获取配置中的阈值
        threshold = Decimal(str(config.change_ratio))

        if change_ratio <= threshold:
            print(f"[{exchange}] 涨跌幅 {change_ratio:.2f}% 未超过阈值 {threshold}%，不下单")
            return False

        print(f"[{exchange}] 涨跌幅 {change_ratio:.2f}% 超过阈值 {threshold}%，准备下单")
        return True


    def _get_market_info(self, symbol: str):
        """
        获取币对的市场信息，如最小下单量、基础币种等。
        """
        market_info = self.exchange.get_market_info(symbol)
        base_ccy = market_info['info'].get('baseCcy', symbol.split('-')[0])
        quote_ccy = market_info['info'].get('quoteCcy', symbol.split('-')[1])
        min_size = Decimal(market_info['info'].get('minSz', 0))
        return market_info, base_ccy, quote_ccy, min_size

    def _build_order_parameters(self, symbol: str, valuation: Dict[str, Decimal], min_size: Decimal, config: Config):

        """
        构建下单请求的参数，包括方向、下单数量、类型等。
        """
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])
        btc_price = Decimal(valuation['btc_price'])
        ratio = [Decimal(x) for x in config.balance_ratio.split(':')]
        client_order_id = generate_client_order_id('Zx')

        if btc_valuation > usdt_valuation:
            diff = btc_valuation - usdt_valuation
            sell_value = ratio[0] * diff / sum(ratio)
            amount = sell_value / btc_price
            if amount < min_size:
                print(f"[{self.get_exchange_name()}] 卖单数量 {amount} < 最小下单量 {min_size}")
                return None
            return {
                'side': OrderSide.SELL,
                'order_type': OrderType.MARKET,
                'amount': amount,
                'price': None,
                'client_order_id': client_order_id,
                'order_type_num': 2
            }
        else:
            diff = usdt_valuation - btc_valuation
            buy_value = ratio[1] * diff / sum(ratio)
            amount = buy_value / btc_price
            if amount < min_size:
                print(f"[{self.get_exchange_name()}] 买单金额 {amount} < 最小下单量 {min_size}")
                return None
            return {
                'side': OrderSide.BUY,
                'order_type': OrderType.MARKET,
                'amount': amount,
                'price': None,
                'client_order_id': client_order_id,
                'order_type_num': 1
            }

    def _submit_order(self, symbol: str, params: Dict) -> Optional[Dict]:
        """
        提交订单并判断下单是否成功。
        根据市场类型现货模式。
        """
        
        order_params = {
            'clOrdId': params['client_order_id'],
        }

        # 调用交易所下单接口
        result = self.exchange.create_order(
            symbol=symbol,
            order_type=params['order_type'].value,
            side=params['side'].value,
            amount=float(params['amount']),
            price=float(params['price']) if params['price'] else None,
            params=order_params
        )

        if int(result.get('info', {}).get('sCode', -1)) != 0:
            print(f"[{self.get_exchange_name()}] 下单失败: {result}")
            return None

        return result


    def _parse_order_details(self, order_details, valuation) -> Tuple[Decimal, Decimal, Decimal]:
        """
        获取订单成交详情，包括成交数量、均价、最新成交价。
        """
        filled_amount = Decimal(order_details.get('accFillSz', 0))
        avg_price = Decimal(order_details.get('avgPx', valuation['btc_price']))
        deal_price = Decimal(order_details.get('fillPx', avg_price))
        return filled_amount, avg_price, deal_price

    def _finalize_order(self, symbol, valuation, market_info, order_params, order_id, filled_amount, avg_price, deal_price):
        """
        处理订单入库、配对匹配与利润计算逻辑。
        """
        exchange = self.get_exchange_name()

        # 获取下单后的估值
        new_valuation = self._get_valuation(symbol)

        # 获取配对信息（对冲、盈利）
        pair = self.crud.get_pair_and_calculate_profit(
            exchange=exchange,
            type=order_params['order_type_num'],
            deal_price=float(deal_price)
        )
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        order_data = {
            'exchange': exchange,
            'product_name': symbol,
            'order_id': order_id,
            'order_number': order_params['client_order_id'],
            'td_mode': 'cross',
            'base_ccy': market_info['info'].get('baseCcy'),
            'quote_ccy': market_info['info'].get('quoteCcy'),
            'type': order_params['order_type_num'],
            'order_type': order_params['order_type'].value,
            'amount': order_params['amount'],
            'clinch_number': filled_amount,
            'price': deal_price,
            'profit': pair['profit'] if pair else 0,
            'pair': pair['pair_id'] if pair else 0,
            'currency1': new_valuation['btc_balance'],
            'currency2': new_valuation['usdt_balance'],
            'balanced_valuation': new_valuation['usdt_valuation'],
            'make_deal_price': float(avg_price),
            'time': now,
            'up_time': now
        }

        # 保存订单
        self.crud.create_piggybank(order_data)
        print(f"[{exchange}] 写入订单数据成功")
        
        # 如果有配对记录，更新配对状态
        if pair:
            is_pair = self.crud.update_pair_and_profit(pair['pair_id'], float(pair['profit']))
            if is_pair:
                self.crud.db.commit()
                print(f"[{exchange}] 配对记录已更新")
