from datetime import datetime
from typing import Dict, Optional, Tuple
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide, OrderStatus
from utils.helpers import generate_client_order_id

class PendingStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config
    
    def execute(self, symbol: str) -> bool:
        """执行挂单策略"""
        try:
            normalized_symbol = self.exchange.normalize_symbol(symbol)
            
            # 检查现有挂单
            pending_orders = self.crud.get_pending_orders(self.get_exchange_name(), normalized_symbol)
            
            if pending_orders and len(pending_orders) >= 2:
                return self._handle_existing_orders(normalized_symbol, pending_orders)
            else:
                return self._place_new_orders(normalized_symbol)
        except Exception as e:
            print(f"[{self.get_exchange_name()}] 挂单策略执行错误: {str(e)}")
            return False
    
    def _handle_existing_orders(self, symbol: str, pending_orders: list) -> bool:
        """处理现有挂单"""
        buy_order = next((o for o in pending_orders if o.type == 1), None)
        sell_order = next((o for o in pending_orders if o.type == 2), None)
        
        # 检查订单成交情况
        buy_filled = self._check_order_filled(buy_order) if buy_order else False
        sell_filled = self._check_order_filled(sell_order) if sell_order else False
        
        if buy_filled or sell_filled:
            return self._process_filled_order(
                symbol, 
                buy_order if buy_filled else sell_order,
                buy_filled
            )
        else:
            return self._check_balance_change(symbol, pending_orders)
    
    def _check_order_filled(self, order) -> bool:
        """检查订单是否成交超过50%"""
        order_details = self.exchange.fetch_order(order.order_id, order.symbol)
        filled_amount = float(order_details['info'].get('accFillSz', order_details.get('filled', 0)))
        return filled_amount >= (order.amount * 0.5)
    
    def _process_filled_order(self, symbol: str, filled_order, is_buy: bool) -> bool:
        """处理已成交订单"""
        # 获取订单详情
        order_details = self.exchange.fetch_order(filled_order.order_id, symbol)
        filled_amount = float(order_details['info'].get('accFillSz', order_details.get('filled', 0)))
        avg_price = float(order_details['info'].get('avgPx', order_details.get('average', 0)))
        
        # 取消所有挂单
        self.exchange.cancel_all_orders(symbol)
        
        # 更新数据库状态
        self.crud.update_pendord_status(self.get_exchange_name(), filled_order.order_id, OrderStatus.FILLED.value)
        
        # 获取配对信息
        pair_id, profit = self._get_pair_info(
            OrderSide.BUY.value if is_buy else OrderSide.SELL.value,
            avg_price,
            filled_amount,
            symbol
        )
        
        # 保存成交订单
        valuation = self._get_valuation(symbol)
        order_data = {
            'exchange': self.get_exchange_name(),
            'product_name': symbol,
            'order_id': filled_order.order_id,
            'order_number': filled_order.order_number,
            'td_mode': 'cross',
            'base_ccy': filled_order.base_ccy,
            'quote_ccy': filled_order.quote_ccy,
            'type': 1 if is_buy else 2,
            'order_type': OrderType.LIMIT.value,
            'amount': filled_order.amount,
            'clinch_number': filled_amount,
            'price': avg_price,
            'profit': profit,
            'pair': pair_id,
            'currency1': valuation['btc_balance'],
            'currency2': valuation['usdt_balance'],
            'balanced_valuation': valuation['usdt_valuation'],
            'make_deal_price': avg_price,
            'time': datetime.now()
        }
        self.crud.create_piggybank(order_data)
        
        # 更新配对订单
        if pair_id:
            self.crud.db.query(Piggybank).filter(Piggybank.id == pair_id).update({
                'pair': filled_order.order_id,
                'profit': profit
            })
            self.crud.db.commit()
        
        # 重新挂单
        return self._place_new_orders(symbol)
    
    def _check_balance_change(self, symbol: str, pending_orders: list) -> bool:
        """检查余额是否变化"""
        current_valuation = self._get_valuation(symbol)
        first_order = pending_orders[0]
        
        if (abs(first_order.currency1 - current_valuation['btc_balance']) > 0.0001 or
            abs(first_order.currency2 - current_valuation['usdt_balance']) > 0.0001):
            print(f"[{self.get_exchange_name()}] 余额有变化，撤单重新挂单")
            self.exchange.cancel_all_orders(symbol)
            return self._place_new_orders(symbol)
        return False
    
    def _place_new_orders(self, symbol: str) -> bool:
        """放置新的挂单"""
        valuation = self._get_valuation(symbol)
        market_info = self.exchange.get_market_info(symbol)
        
        if not market_info:
            print(f"[{self.get_exchange_name()}] 无法获取市场信息")
            return False
            
        min_size = float(market_info['info'].get('minSz', 0))
        
        # 获取上次成交价格
        last_order = self.crud.get_last_piggybank(self.get_exchange_name(), symbol)
        last_price = last_order.price if last_order else valuation['btc_price']
        
        # 计算买卖价格
        buy_price, sell_price = self._calculate_pending_prices(last_price)
        
        # 计算买卖数量
        buy_amount, sell_amount = self._calculate_pending_amounts(valuation, buy_price, sell_price)
        
        if buy_amount < min_size and sell_amount < min_size:
            print(f"[{self.get_exchange_name()}] 买卖数量均小于最小下单量 {min_size}，停止挂单")
            return False
        
        # 放置买单
        buy_order_id = generate_client_order_id('Zx1')
        buy_order = None
        if buy_amount >= min_size:
            buy_order = self.exchange.create_order(
                symbol=symbol,
                order_type=OrderType.LIMIT.value,
                side=OrderSide.BUY.value,
                amount=buy_amount,
                price=buy_price,
                params={'clOrdId': buy_order_id}
            )
        
        # 放置卖单
        sell_order_id = generate_client_order_id('Zx2')
        sell_order = None
        if sell_amount >= min_size:
            sell_order = self.exchange.create_order(
                symbol=symbol,
                order_type=OrderType.LIMIT.value,
                side=OrderSide.SELL.value,
                amount=sell_amount,
                price=sell_price,
                params={'clOrdId': sell_order_id}
            )
        
        # 保存挂单信息
        if buy_order or sell_order:
            new_valuation = self._get_valuation(symbol)
            base_ccy = market_info['info'].get('baseCcy', symbol.split('-')[0])
            quote_ccy = market_info['info'].get('quoteCcy', symbol.split('-')[1])
            
            if buy_order:
                self._save_pending_order(
                    symbol, buy_order, buy_order_id, 1, OrderType.LIMIT.value,
                    buy_amount, buy_price, new_valuation, base_ccy, quote_ccy
                )
            
            if sell_order:
                self._save_pending_order(
                    symbol, sell_order, sell_order_id, 2, OrderType.LIMIT.value,
                    sell_amount, sell_price, new_valuation, base_ccy, quote_ccy
                )
            
            return True
        return False
    
    def _calculate_pending_prices(self, last_price: float) -> Tuple[float, float]:
        """计算挂单价格"""
        sell_prop = (self.config.CHANGE_RATIO / self.config.CHANGE_RATIO) + (self.config.CHANGE_RATIO / 100)
        buy_prop = (self.config.CHANGE_RATIO / self.config.CHANGE_RATIO) - (self.config.CHANGE_RATIO / 100)
        return last_price * buy_prop, last_price * sell_prop
    
    def _calculate_pending_amounts(self, valuation: Dict, buy_price: float, sell_price: float) -> Tuple[float, float]:
        """计算挂单数量"""
        ratio_parts = [float(x) for x in self.config.BALANCE_RATIO.split(':')]
        
        if valuation['btc_valuation'] > valuation['usdt_valuation']:
            # 主要考虑卖出
            sell_amount = ratio_parts[0] * (
                (valuation['btc_valuation'] - valuation['usdt_valuation']) / 
                (ratio_parts[0] + ratio_parts[1])
            )
            return 0, sell_amount / sell_price
        else:
            # 主要考虑买入
            buy_amount = ratio_parts[1] * (
                (valuation['usdt_valuation'] - valuation['btc_valuation']) / 
                (ratio_parts[0] + ratio_parts[1])
            )
            return buy_amount / buy_price, 0
    
    def _save_pending_order(self, symbol: str, order: Dict, order_id: str, 
                          order_type: int, order_class: str, amount: float, 
                          price: float, valuation: Dict, base_ccy: str, quote_ccy: str):
        """保存挂单信息到数据库"""
        order_data = {
            'exchange': self.get_exchange_name(),
            'product_name': symbol,
            'symbol': symbol,
            'order_id': order['id'],
            'order_number': order_id,
            'type': order_type,
            'order_type': order_class,
            'amount': amount,
            'price': price,
            'currency1': valuation['btc_balance'],
            'currency2': valuation['usdt_balance'],
            'clinch_currency1': valuation['btc_balance'] + (amount if order_type == 1 else -amount),
            'clinch_currency2': valuation['usdt_balance'] - (amount * price if order_type == 1 else -amount * price),
            'status': OrderStatus.PENDING.value,
            'time': datetime.now(),
            'up_time': datetime.now(),
            'base_ccy': base_ccy,
            'quote_ccy': quote_ccy
        }
        self.crud.create_pendord(order_data)
    
    # 复用 BalanceStrategy 中的 _get_valuation 和 _get_pair_info 方法
    _get_valuation = BalanceStrategy._get_valuation
    _get_pair_info = BalanceStrategy._get_pair_info