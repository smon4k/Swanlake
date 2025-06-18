from datetime import datetime
from decimal import Decimal
from typing import Dict, Optional, Tuple

from pyapi.piggybank.db.models import Piggybank
from pyapi.piggybank.strategies.init import safe_float
from .base_strategy import BaseStrategy
from config.constants import OrderStatus, OrderType, OrderSide
from utils.helpers import generate_client_order_id
from pyapi.piggybank.strategies.pending_strategy import PendingStrategy

class BalanceStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config
        self.balances = {}

    def execute(self, symbol):
        try:
            # symbol = self.config.symbol
            base_token, quote_token = symbol.split('-')
            exchange = self.get_exchange_name()
            # 获取挂单数据
            pening_order_list = self.crud.get_open_pending_orders(exchange)
            # print("pening_order_list", pening_order_list)
            market_info = self.exchange.get_market_info(symbol)
            valuation = self._get_valuation(symbol)
            if 'buy' in pening_order_list and pening_order_list['buy'] or 'sell' in pening_order_list and pening_order_list['sell']:
                # Step 1: 获取估值
                btc_valuation = Decimal(valuation['btc_valuation'])
                usdt_valuation = Decimal(valuation['usdt_valuation'])
                print(f"[估值1] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
                if btc_valuation == 0 or usdt_valuation == 0:
                    print("估值数据异常，跳过本轮")
                    return
                # 比较两个估值的大小（相当于PHP的bccomp）
                min_max_res = 1 if usdt_valuation > btc_valuation else -1 if usdt_valuation < btc_valuation else 0            
                # 计算百分比差异（保持与PHP相同的逻辑）
                if min_max_res == 1 and btc_valuation != 0:
                    valuation_ratio = (usdt_valuation - btc_valuation) / btc_valuation * Decimal('100')
                elif min_max_res == -1 and usdt_valuation != 0:
                    valuation_ratio = (btc_valuation - usdt_valuation) / usdt_valuation * Decimal('100')
                else:
                    valuation_ratio = Decimal('0')
                
                VALUATION_THRESHOLD = Decimal(str(self.config.VALUATION_THRESHOLD))

                # 如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
                if valuation_ratio > VALUATION_THRESHOLD:
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
                buy_order_data = pening_order_list['buy'] if 'buy' in pening_order_list else None
                sell_order_data = pening_order_list['sell'] if 'sell' in pening_order_list else None
                make_array = [] # 成交数据
                order_amount = Decimal(0)
                deal_amount = Decimal(0)
                side_type = ''
                min_order_amount = Decimal(0)
                make_side = 0  # 成交状态 1：buy 2：sell
                deal_price = Decimal(0)  # 成交均价

                # 首先获取挂买信息
                if buy_order_data:
                    buy_clinch_info = self.exchange.fetch_order(buy_order_data.order_id, symbol)  # 获取挂买数据
                    # print("挂买数据", buy_clinch_info)
                    if buy_clinch_info:
                        order_amount = Decimal(buy_clinch_info['info']['sz'])  # 订单数量
                        deal_amount = Decimal(buy_clinch_info['info']['accFillSz'])  # 成交数量
                        side_type = buy_clinch_info['info']['side']  # 订单方向
                        min_order_amount = order_amount * Decimal(0.5)  # 最小成交数量
                        buy_order_status = buy_clinch_info['info']['state']  # 订单状态
                        print(f"{side_type}订单数量【{order_amount}】成交数量【{deal_amount}】 状态【{buy_order_status}】")
                        if buy_order_status == 'filled':
                            if deal_amount >= min_order_amount:  # 如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                                make_side = 1
                                make_array = buy_order_data
                        if(buy_order_status == 'canceled'):
                            print(f"买单已撤销: {buy_order_data.order_id}")
                            self.crud.update_clinch_amount(
                                exchange=exchange,
                                order_id=buy_order_data.order_id,
                                deal_amount=0,
                                status=3  # 买单撤销状态  
                            )
                            return True  # 买单已撤销，直接返回
                        
                if sell_order_data:
                    # 然后获取挂卖信息
                    sell_clinch_info = self.exchange.fetch_order(sell_order_data.order_id, symbol)  # 获取挂卖数据
                    # print("挂卖数据", sell_clinch_info)
                    if sell_clinch_info:
                        order_amount = Decimal(sell_clinch_info['info']['sz'])  # 订单数量
                        deal_amount = Decimal(sell_clinch_info['info']['accFillSz'])  # 成交数量
                        side_type = sell_clinch_info['info']['side']  # 订单方向
                        min_order_amount = order_amount * Decimal(0.5)  # 最小成交数量
                        sell_order_status = sell_clinch_info['info']['state']  # 订单状态
                        print(f"{side_type}订单数量【{order_amount}】成交数量【{deal_amount}】 状态【{sell_order_status}】")
                        if sell_order_status == 'filled':
                            if deal_amount >= min_order_amount:  # 如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                                make_side = 2
                                make_array = sell_order_data
                        if(sell_order_status == 'canceled'):
                            print(f"卖单已撤销: {sell_order_data.order_id}")
                            self.crud.update_clinch_amount(
                                exchange=exchange,
                                order_id=sell_order_data.order_id,
                                deal_amount=0,
                                status=3  # 卖单撤销状态  
                            )
                            return True  # 卖单已撤销，直接返回
                
                print("是否成交", make_array, make_side)
                if make_side > 0: # 如果buy成交
                    # 如果有成交数据
                    print(f"[成交] 成交方向: {make_side}")
                    deal_price = make_array.price  # 成交价格
                    deal_amount = make_array.clinch_amount  # 成交数量
                    print("成交价格", deal_price, "成交数量", deal_amount)
                    order_id = make_array.order_id  # 挂单ID
                    order_status = 2 if make_side == 1 else 3  # 买单已成交为2，卖单撤销为3
                    is_pair = False
                    profit = Decimal(0)
                    pair_id = 0  # 配对ID
                    if make_side == 1:
                        # 如果是买单成交
                        update_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

                        # 更新数据库挂单状态
                        set_buy_clinch_res = self.crud.update_clinch_amount(
                            exchange=exchange,
                            order_id=order_id,
                            deal_amount=deal_amount,
                            status=2  # 买单已成交状态
                        )
                        if not set_buy_clinch_res:
                            print(f"更新买单成交数量失败: {order_id}, 成交数量: {deal_amount}")
                            return
                        set_sell_clinch_res = self.crud.update_pendord_status(
                            exchange=exchange,
                            order_id=sell_order_data.order_id,
                            deal_amount=0,
                            status=3  # 卖单撤销状态
                        )
                        if not set_sell_clinch_res:
                            print(f"更新卖单撤销状态失败: {sell_order_data.order_id}")
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
                        print("pair_arr", pair_arr)
                        if pair_arr:
                            pair_id = pair_arr['pair_id']
                            is_pair = True
                            profit = pair_arr['clinch_number'] * (pair_arr['price'] - float(deal_price))
                    elif make_side == 2:
                        # 如果是卖单成交
                        update_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

                        # 更新数据库挂单状态
                        set_sell_clinch_res = self.crud.update_clinch_amount(
                            exchange=exchange,
                            order_id=order_id,
                            deal_amount=deal_amount,
                            status=2  # 卖单已成交状态
                        )
                        if not set_sell_clinch_res:
                            print(f"更新卖单成交数量失败: {order_id}, 成交数量: {deal_amount}")
                            return
                        set_buy_clinch_res = self.crud.update_pendord_status(
                            exchange=exchange,
                            order_id=buy_order_data.order_id,
                            deal_amount=0,
                            status=3  # 买单撤销状态
                        )
                        if not set_buy_clinch_res:
                            print(f"更新买单撤销状态失败: {buy_order_data.order_id}")
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
                        if pair_arr:
                            pair_id = pair_arr['pair_id']
                            is_pair = True
                            profit = pair_arr['clinch_number'] * (pair_arr['price'] - float(deal_price))
                    else: # 如果两笔挂单都没有成交
                        print("[无成交] 无有效成交数据")
                        # 获取最新交易估值及余额
                        trade_valuation_new = self._get_valuation(symbol)
                        btc_balance_new = Decimal(trade_valuation_new['btc_balance'])  # 最新BTC余额
                        usdt_balance_new = Decimal(trade_valuation_new['usdt_balance'])  # 最新USDT余额

                        # 检查余额是否有变化
                        if Decimal(buy_order_data.currency1) != btc_balance_new or Decimal(buy_order_data.currency2) != usdt_balance_new:
                            print("余额有变化，撤单重新挂单")
                            print(f"变化前 BTC余额: {buy_order_data.currency1}, USDT余额: {buy_order_data.currency2}")
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
                        base_ccy = market_info['info'].get('baseCcy', symbol.split('-')[0])
                        quote_ccy = market_info['info'].get('quoteCcy', symbol.split('-')[1])

                        # 开始下单 写入下单表
                        trade_valuation_new = self._get_valuation(symbol)
                        insert_order_data = {
                            'exchange': exchange,
                            'product_name': make_array.product_name,
                            'order_id': make_array.order_id,
                            'order_number': make_array.order_number,
                            'td_mode': 'cross',
                            'base_ccy': base_ccy,
                            'quote_ccy': quote_ccy,
                            'type': make_side,
                            'order_type': 'LIMIT',
                            'amount': order_amount,
                            'clinch_number': deal_amount,
                            'price': deal_price,
                            'make_deal_price': deal_price,
                            'profit': profit,
                            'currency1': trade_valuation_new['btc_balance'],
                            'currency2': trade_valuation_new['usdt_balance'],
                            'balanced_valuation': trade_valuation_new['usdt_balance'],
                            'pair': pair_id,
                            'time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                            'up_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                        }
                        insert_id = self.crud.create_piggybank(insert_order_data)
                        if insert_id:
                            print("记录订单成交数据成功")
                            if pair_id > 0:
                                is_pair = self.crud.update_pair_and_profit(pair_id, float(profit))
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
                else:
                    print("[无成交] 挂单进行中")
                    return True
            else:
                print("[无成交] 无有效成交数据，开始重新挂单")
                is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)  # 开始挂单
                if is_pending_order:
                    print("已重新挂单")
                    return True
        except Exception as e:
            print(f"[{self.get_exchange_name()}] 市场下单策略执行错误: {str(e)}")
            return False
        
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
            cancel_num = 0
            for order in open_orders:
                order_id = order.get('id')
                if not order_id:
                    print(f"[撤单异常] 订单缺少 orderId: {order}")
                    continue
                print(f"[撤单] 撤销订单 {order_id}")
                cancel_order = self.exchange.cancel_order(order_id, symbol)
                print(f"[撤单] 撤销结果: {cancel_order}")
                if cancel_order:
                    cancel_num += 1
            if cancel_num == cancel_num:
                print(f"[撤单完成] 所有挂单已撤销: {symbol}")
                self.crud.revoke_all_pending_orders(exchange=self.get_exchange_name())
                return True
            else:
                print(f"[撤单失败] 无法撤销 {symbol} 的所有挂单")
                return False
        except Exception as e:
            print(f"[撤单失败] 撤销 {symbol} 挂单时发生异常: {e}")
            return False
    
    # 开始挂单
    # 根据估值重挂买卖单
    def _place_balancing_orders(self, market_info, valuation, symbol):
        """根据估值重挂买卖单"""
        print("valuation", valuation)
        base_token, quote_token = symbol.split('-')
        exchange = self.get_exchange_name()
        change_ratio = self.config.CHANGE_RATIO #涨跌比例
        balance_ratio = self.config.BALANCE_RATIO #平衡比例
        balance_ratio_arr = list(map(Decimal, balance_ratio.split(':')))
        print("balance_ratio_arr", balance_ratio_arr)
        last_res_price = Decimal(str(self.crud.get_last_deal_price(exchange, symbol) or '0'))  # 上次成交价格
        # last_res_price = Decimal("106546")  # 上次成交价格
        trading_price = Decimal(valuation['btc_price'])  # BTC价格
        print("last_res_price", last_res_price, "trading_price", trading_price)
        btc_valuation = Decimal(str(valuation.get('btc_valuation', '0')))
        usdt_valuation = Decimal(str(valuation.get('usdt_valuation', '0')))
        print(f"[估值2] {base_token}:{btc_valuation}, {quote_token}:{usdt_valuation}")
        buy_last_price = last_res_price
        sell_last_price = last_res_price
        if buy_last_price > trading_price:
            buy_last_price = trading_price
        if sell_last_price < trading_price:
            sell_last_price = trading_price
        sell_propr = (Decimal(change_ratio) / Decimal(change_ratio)) + (change_ratio / Decimal('100'))  # 出售比例
        buy_propr = (Decimal(change_ratio) / Decimal(change_ratio)) - (change_ratio / Decimal('100'))  # 购买比例
        selling_price = sell_last_price * sell_propr  # 出售价格
        buying_price = buy_last_price * buy_propr  # 购买价格
        btc_balance = Decimal(valuation['btc_balance'])  # BTC余额
        usdt_balance = Decimal(valuation['usdt_balance'])  # BUSD余额
        sell_valuation = selling_price * btc_balance  # GBTC 出售估值
        buy_valuation = buying_price * btc_balance  # BTC 购买估值

        buy_diff = 0
        if usdt_valuation > buy_valuation:
            buy_diff = usdt_valuation - buy_valuation
        else:
            buy_diff = buy_valuation - usdt_valuation
        
        sell_diff = 0
        if usdt_valuation > sell_valuation:
            sell_diff = usdt_valuation - sell_valuation
        else:
            sell_diff = sell_valuation - usdt_valuation

        buy_num = balance_ratio_arr[1] * (buy_diff / (balance_ratio_arr[0] + balance_ratio_arr[1]))
        print("buy_num", buy_num, balance_ratio_arr[1], usdt_valuation, buy_valuation)
        buy_orders_number = buy_num / buying_price
        print(f"[计算] 购买数量: {buy_orders_number}")

        sell_num = balance_ratio_arr[0] * (sell_diff / (balance_ratio_arr[0] + balance_ratio_arr[1]))
        print("sell_num", sell_num)
        sell_orders_number = sell_num / selling_price
        print(f"[计算] 出售数量: {sell_orders_number}")
        print(f"[计算] BTC 出售估值{sell_valuation}usdt估值{usdt_valuation}出售数量{sell_num}出售价格{selling_price}\n")
        print(f"{buy_orders_number}&&&{sell_orders_number}\n")

        market_info = self.exchange.get_market_info(symbol)
        min_size = Decimal(market_info['info'].get('minSz', 0))
        if buy_orders_number < min_size or sell_orders_number < min_size:
            print(f"[判断] 购买或出售出现负数或者小于最小下单量 {min_size},撤单 开始吃单")
            strategy = PendingStrategy(self.exchange, self.db_session, self.config)
            is_position_order = strategy.execute(symbol)  # 开始吃单平衡
            if is_position_order:
                print("吃单成功 开始重新挂单")
                is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)
                if is_pending_order:
                    print("已重新挂单")
                    return True
            return True
        
        per_diff_res = 0
        if usdt_valuation > btc_valuation:  # busd大
            print("""usdt大""")
            per_diff_res = (usdt_valuation - btc_valuation) / btc_valuation * 100
        else:
            print("""btc大""")
            per_diff_res = (btc_valuation - usdt_valuation) / usdt_valuation * 100
        print("per_diff_res", per_diff_res, change_ratio)
        if per_diff_res > change_ratio:  # 如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
            print("两个币种估值差大于2% 开始全部撤单")
            is_cancel_order = self._cancel_open_orders(symbol)
            if is_cancel_order:
                print("撤单成功 开始吃单")
                strategy = PendingStrategy(self.exchange, self.db_session, self.config)
                is_position_order = strategy.execute(symbol)  # 开始吃单平衡
                if is_position_order:
                    print("吃单成功 重新挂单")
                    is_pending_order = self._place_balancing_orders(market_info, valuation, symbol)
                    if is_pending_order:
                        print("已重新挂单")
                        return True
            return True
        
        buy_order_details_arr = []
        sell_order_details_arr = []
        buy_order_id = generate_client_order_id('Zx1')
        sell_order_id = generate_client_order_id('Zx2')
        if btc_valuation > usdt_valuation:
            print("btc大于usdt,开始出售")
            sell_orders_detais = self._place_sell_order(symbol, sell_orders_number, selling_price, sell_order_id)
            if sell_orders_detais:
                sell_order_details_arr = sell_orders_detais
                sell_order_details_arr['amount'] = sell_orders_number
                sell_order_details_arr['price'] = selling_price
                sell_order_details_arr['order_id'] = sell_orders_detais['id']
                sell_order_details_arr['client_order_id'] = sell_order_id
                buy_orders_detais = self._place_buy_order(symbol, buy_orders_number, buying_price, buy_order_id)
                if buy_orders_detais:
                    buy_order_details_arr = buy_orders_detais
                    buy_order_details_arr['amount'] = buy_orders_number
                    buy_order_details_arr['price'] = buying_price
                    buy_order_details_arr['order_id'] = buy_orders_detais['id']
                    buy_order_details_arr['client_order_id'] = buy_order_id

        if btc_valuation < usdt_valuation:
            print("btc小于usdt,开始购买")
            buy_orders_detais = self._place_buy_order(symbol, buy_orders_number, buying_price, buy_order_id)
            if buy_orders_detais:
                buy_order_details_arr = buy_orders_detais
                buy_order_details_arr['amount'] = buy_orders_number
                buy_order_details_arr['price'] = buying_price
                buy_order_details_arr['order_id'] = buy_orders_detais['id']
                buy_order_details_arr['client_order_id'] = buy_order_id
                sell_orders_detais = self._place_sell_order(symbol, sell_orders_number, selling_price, sell_order_id)
                if sell_orders_detais:
                    sell_order_details_arr = sell_orders_detais
                    sell_order_details_arr['amount'] = sell_orders_number
                    sell_order_details_arr['price'] = selling_price
                    sell_order_details_arr['order_id'] = sell_orders_detais['id']
                    sell_order_details_arr['client_order_id'] = sell_order_id

        if buy_order_details_arr and sell_order_details_arr and len(buy_order_details_arr) > 0 and len(sell_order_details_arr) > 0:
            print("下单成功")
            new_valuation = self._get_valuation(symbol)
            now = datetime.now()
            timestamp = now.strftime('%Y-%m-%d %H:%M:%S')
            insert_buy_order_data = {
                'exchange': exchange,
                'product_name': symbol,
                'symbol': symbol,
                'order_id': buy_order_details_arr['order_id'],
                'order_number': buy_order_details_arr['client_order_id'],
                'type': 1,
                'order_type': 'limit',
                'amount': buy_order_details_arr['amount'],
                'price': buy_order_details_arr['price'],
                'currency1': new_valuation['btc_balance'],
                'currency2': new_valuation['usdt_balance'],
                'clinch_currency1': valuation['btc_balance'] + buy_order_details_arr['amount'],
                'clinch_currency2': valuation['usdt_balance'] - buy_order_details_arr['amount'] * buy_order_details_arr['price'],
                'status': OrderStatus.PENDING.value,
                'time': timestamp,
                'up_time': timestamp,
            }
            is_set_buy_res = self.crud.create_piggybank_pendord(insert_buy_order_data)
            if is_set_buy_res:
                now = datetime.now()
                timestamp = now.strftime('%Y-%m-%d %H:%M:%S')
                insert_sell_order_data = {
                    'exchange': exchange,
                    'product_name': symbol,
                    'symbol': symbol,
                    'order_id': sell_order_details_arr['order_id'],
                    'order_number': sell_order_details_arr['client_order_id'],
                    'type': 2,
                    'order_type': 'limit',
                    'amount': sell_order_details_arr['amount'],
                    'price': sell_order_details_arr['price'],
                    'currency1': new_valuation['btc_balance'],
                    'currency2': new_valuation['usdt_balance'],
                    'clinch_currency1': valuation['btc_balance'] - sell_order_details_arr['amount'],
                    'clinch_currency2': valuation['usdt_balance'] + sell_order_details_arr['amount'] * sell_order_details_arr['price'],
                    'status': OrderStatus.PENDING.value,
                    'time': timestamp,
                    'up_time': timestamp,
                }
                is_set_sell_res = self.crud.create_piggybank_pendord(insert_sell_order_data)
                if is_set_sell_res:
                    return True

    def _place_sell_order(self, symbol, amount=None, price=None, clorder_id=None):
        """下限价卖单"""
        print(f"[挂限价卖单] 数量: {amount}, 价格: {price}")
        result = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.SELL.value,
            price=float(price),
            amount=float(amount),
            params={'tdMode': 'cross', 'clOrdId': clorder_id}
        )
        return result

    def _place_buy_order(self, symbol, amount=None, price=None, clorder_id=None):
        """下限价买单"""
        print(f"[挂限价买单] 数量: {amount}, 价格: {price}")
        result = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.LIMIT.value,
            side=OrderSide.BUY.value,
            price=float(price),
            amount=float(amount),
            params={'tdMode': 'cross', 'clOrdId': clorder_id}
        )
        return result