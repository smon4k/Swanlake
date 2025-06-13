from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple

from pyapi.piggybank.db.models import Piggybank
from pyapi.piggybank.strategies.init import safe_float
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id
from pyapi.piggybank.strategies.pending_strategy import PendingStrategy

class BalanceStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config
        self.balances = {}

    def execute(self, symbol):
        # symbol = self.config.symbol
        base_token, quote_token = symbol.split('-')
        balance_ratio = self.config.BALANCE_RATIO #平衡比例
        exchange = self.get_exchange_name()
        # 获取挂单数据
        pening_order_list = self.crud.get_open_pending_orders(exchange)
        if pening_order_list['buy'] or pening_order_list['sell']:
            # Step 1: 获取估值
            valuation = self._get_valuation(symbol)
            btc_valuation = Decimal(valuation['btc_valuation'])
            usdt_valuation = Decimal(valuation['usdt_valuation'])
            print(f"[估值] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
            if btc_valuation == 0 or usdt_valuation == 0:
                print("估值数据异常，跳过本轮")
                return
            
            # 比较两个估值的大小（相当于PHP的bccomp）
            min_max_res = 1 if usdt_valuation > btc_valuation else -1 if usdt_valuation < btc_valuation else 0            
            # 计算百分比差异（保持与PHP相同的逻辑）
            if min_max_res == 1:  # usdt估值更大
                valuation_ratio = (usdt_valuation - btc_valuation) / btc_valuation * 100
            else:  # btc估值更大或相等
                valuation_ratio = (btc_valuation - usdt_valuation) / usdt_valuation * 100      
            # 如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
            if valuation_ratio > balance_ratio:
                print(f"[估值不平衡] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}, 差异比: {valuation_ratio}")
                self._cancel_open_orders(symbol) # 撤单
                strategy = PendingStrategy(self.exchange, self.db_session, self.config)
                success = strategy.execute('BTC-USDT')
                print("吃单结果", success)
                if success:
                    self.execute(symbol)  # 重新挂单
                else:
                    print("吃单失败，跳过本轮")
                    return
                return  
            buy_order_data = pening_order_list['buy']
            sell_order_data = pening_order_list['sell']
            make_array = [] # 成交数据
            order_amount = Decimal(0)
            deal_amount = Decimal(0)
            side_type = ''
            min_order_amount = Decimal(0)
            make_side = 0  # 成交状态 1：buy 2：sell
            deal_price = Decimal(0)  # 成交均价

            # 首先获取挂买信息
            buy_clinch_info = self.exchange.fetch_trade_order(
                buy_order_data['order_id'], buy_order_data['order_number'], symbol
            )  # 获取挂买数据
            if buy_clinch_info:
                order_amount = Decimal(buy_clinch_info['sz'])  # 订单数量
                deal_amount = Decimal(buy_clinch_info['accFillSz'])  # 成交数量
                side_type = buy_clinch_info['side']  # 订单方向
                min_order_amount = order_amount * Decimal(0.5)  # 最小成交数量
                print(f"{side_type}订单数量【{order_amount}】成交数量【{deal_amount}】")
                if deal_amount >= min_order_amount:  # 如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                    make_side = 1
                    make_array = buy_order_data

            # 然后获取挂卖信息
            sell_clinch_info = self.exchange.fetch_trade_order(
                sell_order_data['order_id'], sell_order_data['order_number'], symbol
            )  # 获取挂卖数据
            if sell_clinch_info:
                order_amount = Decimal(sell_clinch_info['sz'])  # 订单数量
                deal_amount = Decimal(sell_clinch_info['accFillSz'])  # 成交数量
                side_type = sell_clinch_info['side']  # 订单方向
                min_order_amount = order_amount * Decimal(0.5)  # 最小成交数量
                print(f"{side_type}订单数量【{order_amount}】成交数量【{deal_amount}】")
                if deal_amount >= min_order_amount:  # 如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                    make_side = 2
                    make_array = sell_order_data
            if make_array:
                # 如果有成交数据
                print(f"[成交] 成交方向: {make_side}, 成交数据: {make_array}")
                deal_price = Decimal(make_array['price'])  # 成交价格
                deal_amount = Decimal(make_array['clinch_amount'])  # 成交数量
                order_id = make_array['order_id']  # 挂单ID
                order_status = 2 if make_side == 1 else 3  # 买单已成交为2，卖单撤销为3
                if make_side == 1:
                    # 如果是买单成交
                    update_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

                    # 更新数据库挂单状态
                    set_buy_clinch_res = self.crud.update_clinch_amount(
                        exchange=exchange,
                        order_id=order_id,
                        deal_amount=float(deal_amount),
                        status=2  # 买单已成交状态
                    )
                    if not set_buy_clinch_res:
                        print(f"更新买单成交数量失败: {order_id}, 成交数量: {deal_amount}")
                        return
                    set_sell_clinch_res = self.crud.update_pendord_status(
                        exchange=exchange,
                        order_id=sell_order_data['order_id'],
                        deal_amount=float(0),
                        status=3  # 卖单撤销状态
                    )
                    if not set_sell_clinch_res:
                        print(f"更新卖单撤销状态失败: {sell_order_data['order_id']}")
                        return
                    
                    # 撤销所有订单
                    revoke_order = self._cancel_open_orders(symbol)
                    if not revoke_order:
                        print(f"撤销所有挂单失败: {symbol}")
                        return
                    pair_arr = self.crud.get_pair_and_calculate_profit(
                        exchange=exchange,
                        type=2,  # 买单类型
                        deal_price=float(deal_price)
                    )
                elif make_side == 2:
                    # 如果是卖单成交
                    update_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

                    # 更新数据库挂单状态
                    set_sell_clinch_res = self.crud.update_clinch_amount(
                        exchange=exchange,
                        order_id=order_id,
                        deal_amount=float(deal_amount),
                        status=2  # 卖单已成交状态
                    )
                    if not set_sell_clinch_res:
                        print(f"更新卖单成交数量失败: {order_id}, 成交数量: {deal_amount}")
                        return
                    set_buy_clinch_res = self.crud.update_pendord_status(
                        exchange=exchange,
                        order_id=buy_order_data['order_id'],
                        deal_amount=float(0),
                        status=3  # 买单撤销状态
                    )
                    if not set_buy_clinch_res:
                        print(f"更新买单撤销状态失败: {buy_order_data['order_id']}")
                        return
                    
                    # 撤销所有订单
                    revoke_order = self._cancel_open_orders(symbol)
                    if not revoke_order:
                        print(f"撤销所有挂单失败: {symbol}")
                        return
                    pair_arr = self.crud.get_pair_and_calculate_profit(
                        exchange=exchange,
                        type=1,  # 卖单类型
                        deal_price=float(deal_price)
                    )
                else: # 如果两笔挂单都没有成交
                    print("[无成交] 无有效成交数据")
                    # 获取最新交易估值及余额
                    trade_valuation_new = self._get_valuation(symbol)
                    btc_balance_new = Decimal(trade_valuation_new['btc_balance'])  # 最新BTC余额
                    usdt_balance_new = Decimal(trade_valuation_new['usdt_balance'])  # 最新USDT余额

                    # 检查余额是否有变化
                    if Decimal(buy_order_data['currency1']) != btc_balance_new or Decimal(buy_order_data['currency2']) != usdt_balance_new:
                        print("余额有变化，撤单重新挂单")
                        print(f"变化前 BTC余额: {buy_order_data['currency1']}, USDT余额: {buy_order_data['currency2']}")
                        print(f"最新 BTC余额: {btc_balance_new}, USDT余额: {usdt_balance_new}")

                        # 撤销所有挂单
                        order_cancel_res = self._cancel_open_orders(symbol)
                        if order_cancel_res:
                            print("开始重新吃单模式")
                            strategy = PendingStrategy(self.exchange, self.db_session, self.config)
                            is_position_order = strategy.execute(symbol)  # 开始吃单平衡
                            if is_position_order:
                                print("已重新吃单")
                                return True
                if make_side == 1 or make_side == 2:
                    # 获取最小下单数量
                    market_info = self.exchange.get_market_info(symbol)
                    base_ccy = market_info['info'].get('baseCcy', symbol.split('-')[0])
                    quote_ccy = market_info['info'].get('quoteCcy', symbol.split('-')[1])

                    # 开始下单 写入下单表
                    trade_valuation_new = self._get_valuation(symbol)
                    insert_order_data = {
                        'product_name': make_array['product_name'],
                        'order_id': make_array['order_id'],
                        'order_number': make_array['order_number'],
                        'td_mode': 'cross',
                        'base_ccy': base_ccy,
                        'quote_ccy': quote_ccy,
                        'type': make_side,
                        'order_type': 'LIMIT',
                        'amount': float(order_amount),
                        'clinch_number': float(deal_amount),
                        'price': float(deal_price),
                        'make_deal_price': float(deal_price),
                        'profit': float(pair_arr.get('profit', 0)),
                        'currency1': trade_valuation_new['btc_balance'],
                        'currency2': trade_valuation_new['usdt_balance'],
                        'balanced_valuation': trade_valuation_new['usdt_balance'],
                        'pair': pair_arr.get('pair_id', 0),
                        'time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                    }
                    insert_id = self.crud.create_piggybank(insert_order_data)
                    if insert_id:
                        print("记录订单成交数据成功")
                        pair_id = pair_arr.get('pair_id', 0)
                        if pair_id > 0:
                            is_pair = self.crud.update_pair_and_profit(pair_id, float(pair_arr.get('profit', 0)))
                            if is_pair:
                                is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)  # 重新limit挂单
                                if is_pending_order:
                                    print("已重新挂单")
                                    return True
                        else:
                            is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)  # 重新limit挂单
                            if is_pending_order:
                                print("已重新挂单")
                                return True

    # 撤销所有挂单
    def _cancel_open_orders(self, symbol):
        """
        Cancel all open orders for the given symbol.

        Args:
            symbol (str): The trading pair symbol.

        Returns:
            bool: True if all orders are successfully canceled, False otherwise.
        """
        try:
            print(f"[撤单] 撤销 {symbol} 所有挂单")
            open_orders = self.exchange.fetch_open_orders(symbol)
            if not open_orders:
                print(f"[撤单] 无挂单需要撤销: {symbol}")
                return True

            for order in open_orders:
                client_order_id = order.get('clientOrderId')
                if not client_order_id:
                    print(f"[撤单异常] 订单缺少 clientOrderId: {order}")
                    continue

                print(f"[撤单] 撤销订单 {client_order_id}")
                self.exchange.cancel_order(client_order_id, symbol)
            print(f"[撤单完成] 所有挂单已撤销: {symbol}")
            return True
        except Exception as e:
            print(f"[撤单失败] 撤销 {symbol} 挂单时发生异常: {e}")
            return False
    
    # 开始挂单
    # 根据估值重挂买卖单
    def _place_balancing_orders(self, market_info, valuation, symbol):
        """根据估值重挂买卖单"""
        print("valuation", valuation)
        base_token, quote_token = symbol.split('-')
        btc_valuation = Decimal(valuation['btc_valuation'])
        usdt_valuation = Decimal(valuation['usdt_valuation'])
        print(f"[估值] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
        if usdt_valuation > btc_valuation:
            self._place_buy_order(market_info, valuation, symbol)
            self._place_sell_order(market_info, valuation, symbol)
        else:
            self._place_sell_order(market_info, valuation, symbol)
            self._place_buy_order(market_info, valuation, symbol)

    def _place_sell_order(self, market_info, valuation, symbol):
        """下限价卖单"""
        usdt_balance = Decimal(valuation['usdt_balance'])
        price = market_info.get('best_bid', 0)
        if usdt_balance <= 0 or price <= 0:
            print("[限价卖单跳过] 无有效数量或价格")
            return

        print(f"[挂限价卖单] 数量: {usdt_balance}, 价格: {price}")
        self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            price=float(price),
            amount=float(usdt_balance),
            params={'tdMode': 'cross'}
        )

    def _place_buy_order(self, market_info, valuation, symbol):
        """下限价买单"""
        btc_balance = Decimal(valuation['btc_balance'])
        btc_price = Decimal(valuation['btc_price'])
        min_size = Decimal(market_info['info'].get('minSz', 0))
        if btc_balance <= 0 or btc_price <= 0:
            print("[限价买单跳过] 无有效余额或价格")
            return

        amount = btc_balance / btc_price
        print(f"[挂限价买单] 数量: {amount}, 价格: {btc_price}")
        if amount < min_size:
            self._cancel_open_orders(symbol)
            # strategy = PendingStrategy(self.exchange, self.db_session, self.config)
            # success = strategy.execute('BTC-USDT')
            # print("吃单结果", success)
            # if not success:
            #     print("吃单失败，跳过本轮")
            #     return

            # # 重新挂单
            # market_info = self.exchange.get_market_info(symbol)
            # print("market_info", market_info)
            # self._place_balancing_orders(market_info, valuation, symbol)
            return
        self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            price=float(btc_price),
            amount=float(amount),
            params={'tdMode': 'cross'}
        )

    def _check_balance_changed(self):
        """检测是否有余额变动"""
        new_balance = self.exchange.fetch_balance()
        changed = False
        for token, info in new_balance.items():
            old = self.balances.get(token, {}).get('total', 0)
            new = info.get('total', 0)
            if abs(new - old) > 1e-8:
                changed = True
                break
        self.balances = new_balance
        return changed

    def _process_filled_orders(self, orders):
        """盈亏配对逻辑（你自己已有实现）"""
        # 请在此处填入你已有的盈亏配对逻辑
        pass