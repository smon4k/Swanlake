# 挂单吃单主策略
from datetime import datetime
from decimal import Decimal
from pyapi.piggybank.strategies.balanced_strategy import BalancedStrategy
from pyapi.piggybank.strategies.base_strategy import BaseStrategy
from pyapi.piggybank.strategies.pending_strategy import PendingStrategy


class MainStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        # 初始化参数，包括交易所对象、数据库会话和配置
        super().__init__(exchange, db_session)
        self.config = config

    def execute(self, symbol):
        """
        执行平衡策略主流程：估值比较、成交状态处理、重新挂单等
        """
        try:
            base_token, quote_token = symbol.split("-")
            exchange = self.get_exchange_name()
            market_info = self.exchange.get_market_info(symbol)
            valuation = self._get_valuation(symbol)  # 获取当前估值和余额
            pending_orders = self.crud.get_open_pending_orders(
                exchange
            )  # 获取当前挂单信息

            # 没有挂单则直接挂新单
            if not pending_orders.get("buy") or not pending_orders.get("sell"):
                print(f"[{exchange}] 没有挂单，执行挂单流程")
                return self._re_place_orders(symbol, PendingStrategy)

            # 如果估值不平衡（超出设定阈值），执行撤单-吃单流程
            print(f"[{exchange}] 检查估值平衡状态...")
            if self._is_valuation_unbalanced(valuation):
                print(f"[{exchange}] 估值不平衡，执行撤单-吃单流程")
                return self._re_place_orders(symbol, BalancedStrategy)

            # 检查订单是否有一方成交
            print(f"[{exchange}] 检查当前挂单状态...")
            make_side, make_order = self._check_order_status(symbol, pending_orders)
            if make_side:
                print(f"[{exchange}] 订单已成交，执行处理成交逻辑")
                return self._handle_deal(
                    make_side,
                    make_order,
                    pending_orders,
                    market_info,
                    valuation,
                    symbol,
                )

            # 检查余额是否有变化（判断是否发生部分成交或资金变动）
            print(f"[{exchange}] 检查挂单余额变化...")
            if self._has_balance_changed(pending_orders["buy"], valuation):
                print(f"[{exchange}] 挂单余额变化，执行撤单-吃单流程")
                return self._re_place_orders(symbol, BalancedStrategy)

            print("[无成交] 当前挂单无变化，继续等待")
            return True
        except Exception as e:
            print(f"[{exchange}] 策略执行异常: {e}")
            return False

    def _is_valuation_unbalanced(self, valuation):
        """判断BTC和USDT估值是否不平衡，超出设定阈值返回True"""
        btc_val = Decimal(valuation["btc_valuation"])
        usdt_val = Decimal(valuation["usdt_valuation"])
        config = self.crud.get_config()
        threshold = Decimal(str(config.change_ratio))
        if btc_val == 0 or usdt_val == 0:
            return False
        print(
            f"[{self.get_exchange_name()}] [估值] BTC估值: {btc_val} USDT估值: {usdt_val}"
        )
        diff = abs(btc_val - usdt_val)
        print(
            f"[{self.get_exchange_name()}] [估值差异] 差异: {diff} 最小值: {min(btc_val, usdt_val)}"
        )
        ratio = diff / min(btc_val, usdt_val) * 100
        print(
            f"[{self.get_exchange_name()}] [估值比例] 比例: {ratio}% 阈值: {threshold}%"
        )
        return ratio > threshold

    def _check_order_status(self, symbol, pending_orders):
        """
        检查当前挂单的状态
        如果有订单成交超过50%，返回成交方向和订单信息
        """
        config = self.crud.get_config()
        for side in ["buy", "sell"]:
            order_data = pending_orders.get(side)
            if not order_data:
                continue
            order_info = self.exchange.fetch_order(order_data.order_id, symbol)
            if not order_info:
                continue
            # order_info_raw = order_info.get("info", {})
            # if order_info_raw:
            #     print(
            #         f"[{side.upper()}] 订单详情: state={order_info_raw.get('state')}, "
            #         f"cancelSource={order_info_raw.get('cancelSource')}, "
            #         f"cancelReason={order_info_raw.get('cancelReason')}, "
            #         f"rjctReason={order_info_raw.get('rjctReason')}"
            #     )
            # print(f"[{side.upper()}] 检查订单状态: {order_info}")

            order_amount = Decimal(order_info["info"]["sz"])
            deal_amount = Decimal(order_info["info"]["accFillSz"])
            order_data.clinch_amount = float(deal_amount)
            status = order_info["info"]["state"]

            if status == "filled" and deal_amount >= order_amount * Decimal(
                str(config.change_ratio)
            ):
                print(f"[{side.upper()}] 订单已成交: {order_data.order_id}")
                return (1 if side == "buy" else 2), order_data
            if status == "canceled":
                print(f"[{side.upper()}] 订单已撤销: {order_data.order_id}")
                self.crud.update_clinch_amount(
                    exchange=self.get_exchange_name(),
                    order_id=order_data.order_id,
                    deal_amount=0,
                    status=3,
                )
                return 0, None
        return 0, None

    def _handle_deal(
        self, make_side, make_order, pending_orders, market_info, valuation, symbol
    ):
        """
        处理有一方成交后的逻辑：更新数据库、撤销订单、记录利润、重新挂单
        """
        print(f"[成交] 成交方向: {make_side}")
        deal_price = make_order.price
        deal_amount = make_order.clinch_amount
        exchange = self.get_exchange_name()

        self._update_db_after_deal(make_side, make_order, pending_orders, deal_amount)
        self._cancel_open_orders(symbol)

        # 计算是否有配对订单及利润
        pair_info = self.crud.get_pair_and_calculate_profit(
            exchange=exchange, type=make_side, deal_price=float(deal_price)
        )

        profit = 0
        pair_id = 0
        if pair_info:
            pair_id = pair_info["pair_id"]
            profit = pair_info["profit"]

        new_valuation = self._get_valuation(symbol)
        self._insert_deal_record(
            symbol,
            make_side,
            make_order,
            deal_price,
            deal_amount,
            new_valuation,
            profit,
            pair_id,
        )

        # 重新挂单
        return self._re_place_orders(symbol, PendingStrategy)

    def _has_balance_changed(
        self, buy_order_data, new_valuation, tolerance=Decimal("0.00001")
    ):
        """判断买单时的余额和当前是否发生明显变化（考虑浮点精度）"""
        old_btc = Decimal(buy_order_data.currency1)
        old_usdt = Decimal(buy_order_data.currency2)
        new_btc = Decimal(new_valuation["btc_balance"])
        new_usdt = Decimal(new_valuation["usdt_balance"])

        btc_changed = abs(old_btc - new_btc) > tolerance
        usdt_changed = abs(old_usdt - new_usdt) > tolerance

        return btc_changed or usdt_changed

    def _update_db_after_deal(self, make_side, make_order, pending_orders, deal_amount):
        """更新数据库中的成交订单和撤销的订单状态"""
        exchange = self.get_exchange_name()
        status_done = 2  # 已成交
        status_cancel = 3  # 撤销

        self.crud.update_clinch_amount(
            exchange, make_order.order_id, deal_amount, status_done
        )

        # 找到另一侧订单进行撤销状态更新
        other_order = (
            pending_orders["sell"] if make_side == 1 else pending_orders["buy"]
        )
        self.crud.update_pendord_status(
            exchange, other_order.order_id, 0, status_cancel
        )

    def _insert_deal_record(
        self, symbol, make_side, order, price, amount, valuation, profit, pair_id
    ):
        """将成交记录写入 piggybank 表中"""
        exchange = self.get_exchange_name()
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        record = {
            "exchange": exchange,
            "product_name": order.product_name,
            "order_id": order.order_id,
            "order_number": order.order_number,
            "td_mode": "cross",
            "base_ccy": symbol.split("-")[0],
            "quote_ccy": symbol.split("-")[1],
            "type": make_side,
            "order_type": "LIMIT",
            "amount": amount,
            "clinch_number": amount,
            "price": price,
            "make_deal_price": price,
            "profit": profit,
            "currency1": valuation["btc_balance"],
            "currency2": valuation["usdt_balance"],
            "balanced_valuation": valuation["usdt_balance"],
            "pair": pair_id,
            "time": now,
            "up_time": now,
        }

        inserted = self.crud.create_piggybank(record)
        if inserted and pair_id > 0:
            self.crud.update_pair_and_profit(pair_id, float(profit))
