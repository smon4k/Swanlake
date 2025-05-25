from datetime import datetime
from typing import Dict, Optional, Tuple
from .base_strategy import BaseStrategy
from config.constants import OrderType, OrderSide
from utils.helpers import generate_client_order_id

class BalanceStrategy(BaseStrategy):
    def __init__(self, exchange, db_session, config):
        super().__init__(exchange, db_session)
        self.config = config
    
    def execute(self, symbol: str) -> bool:
        """执行平衡策略"""
        try:
            # 标准化交易对符号
            normalized_symbol = self.exchange.normalize_symbol(symbol)
            
            # 获取交易对估值和市场信息
            valuation = self._get_valuation(normalized_symbol)
            market_info = self.exchange.get_market_info(normalized_symbol)
            
            if not market_info:
                print(f"[{self.get_exchange_name()}] 无法获取市场信息")
                return False
                
            # 获取最小交易量
            min_size = float(market_info['info'].get('minSz', 0))
            
            # 计算变化比例
            change_ratio = self._calculate_change_ratio(valuation)
            
            if change_ratio > self.config.CHANGE_RATIO:
                client_order_id = generate_client_order_id()
                print(f"[{self.get_exchange_name()}] 下单ID: {client_order_id}")
                
                # 计算订单数量
                btc_amount, usdt_amount = self._calculate_order_amounts(valuation)
                
                if btc_amount > min_size:
                    return self._place_sell_order(normalized_symbol, btc_amount, client_order_id, market_info, valuation)
                elif usdt_amount > min_size:
                    return self._place_buy_order(normalized_symbol, usdt_amount, client_order_id, market_info, valuation)
                else:
                    print(f"[{self.get_exchange_name()}] 下单数量小于最小下单量 {min_size}，停止下单")
                    return False
            else:
                print(f"[{self.get_exchange_name()}] 涨跌幅度小于 {self.config.CHANGE_RATIO}%，停止下单")
                return False
        except Exception as e:
            print(f"[{self.get_exchange_name()}] 平衡策略执行错误: {str(e)}")
            return False
    
    def _get_valuation(self, symbol: str) -> Dict:
        """获取交易对估值"""
        balance = self.exchange.get_balance()
        ticker = self.exchange.get_ticker(symbol)
        
        currency1, currency2 = symbol.split('-') if '-' in symbol else symbol.split('/')
        
        # 获取币种余额
        btc_balance = sum(
            float(asset['free']) for asset in balance['info'].get('details', balance['info'].get('balances', [])) 
            if asset.get('ccy', asset.get('asset', '')) == currency1
        )
        usdt_balance = sum(
            float(asset['free']) for asset in balance['info'].get('details', balance['info'].get('balances', [])) 
            if asset.get('ccy', asset.get('asset', '')) == currency2
        )
        
        btc_price = float(ticker['last'])
        btc_valuation = btc_balance * btc_price
        usdt_valuation = usdt_balance
        
        return {
            'btc_price': btc_price,
            'btc_balance': btc_balance,
            'usdt_balance': usdt_balance,
            'btc_valuation': btc_valuation,
            'usdt_valuation': usdt_valuation
        }
    
    def _calculate_change_ratio(self, valuation: Dict) -> float:
        """计算变化比例"""
        last_balanced = self.crud.get_last_piggybank(self.get_exchange_name(), valuation['product_name'])
        last_valuation = last_balanced.balanced_valuation if last_balanced else valuation['usdt_valuation']
        
        return abs(valuation['btc_valuation'] - valuation['usdt_valuation']) / last_valuation * 100
    
    def _calculate_order_amounts(self, valuation: Dict) -> Tuple[float, float]:
        """计算买卖订单数量"""
        ratio_parts = [float(x) for x in self.config.BALANCE_RATIO.split(':')]
        
        if valuation['btc_valuation'] > valuation['usdt_valuation']:
            # 卖出BTC
            sell_amount = ratio_parts[0] * (
                (valuation['btc_valuation'] - valuation['usdt_valuation']) / 
                (ratio_parts[0] + ratio_parts[1])
            )
            return sell_amount / valuation['btc_price'], 0
        else:
            # 买入BTC
            buy_amount = ratio_parts[1] * (
                (valuation['usdt_valuation'] - valuation['btc_valuation']) / 
                (ratio_parts[0] + ratio_parts[1])
            )
            return 0, buy_amount
    
    def _place_sell_order(self, symbol: str, amount: float, client_order_id: str, 
                         market_info: Dict, valuation: Dict) -> bool:
        """处理卖出订单"""
        order = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.MARKET.value,
            side=OrderSide.SELL.value,
            amount=amount,
            params={'clOrdId': client_order_id}
        )
        
        if order['info'].get('sCode', '0') == '0' or order.get('status') == 'filled':
            print(f"[{self.get_exchange_name()}] 卖出订单成功")
            return self._process_order_result(
                order, symbol, client_order_id, OrderSide.SELL.value, 
                market_info, valuation
            )
        return False
    
    def _place_buy_order(self, symbol: str, amount: float, client_order_id: str, 
                        market_info: Dict, valuation: Dict) -> bool:
        """处理买入订单"""
        order = self.exchange.create_order(
            symbol=symbol,
            order_type=OrderType.MARKET.value,
            side=OrderSide.BUY.value,
            amount=amount,
            params={'clOrdId': client_order_id}
        )
        
        if order['info'].get('sCode', '0') == '0' or order.get('status') == 'filled':
            print(f"[{self.get_exchange_name()}] 买入订单成功")
            return self._process_order_result(
                order, symbol, client_order_id, OrderSide.BUY.value, 
                market_info, valuation
            )
        return False
    
    def _process_order_result(self, order: Dict, symbol: str, client_order_id: str, 
                            side: str, market_info: Dict, valuation: Dict) -> bool:
        """处理订单结果并保存到数据库"""
        order_details = self.exchange.fetch_order(order['id'], symbol)
        
        filled_amount = float(order_details['info'].get('accFillSz', order_details.get('filled', 0)))
        avg_price = float(order_details['info'].get('avgPx', order_details.get('average', 0)))
        last_price = float(order_details['info'].get('fillPx', avg_price)) if order_details['info'].get('fillPx') else avg_price
        
        # 获取配对订单和利润
        pair_id, profit = self._get_pair_info(side, last_price, filled_amount, symbol)
        
        # 获取平衡后的估值
        new_valuation = self._get_valuation(symbol)
        
        # 准备订单数据
        order_data = {
            'exchange': self.get_exchange_name(),
            'product_name': symbol,
            'order_id': order['id'],
            'order_number': client_order_id,
            'td_mode': 'cross',
            'base_ccy': market_info['info'].get('baseCcy', symbol.split('-')[0]),
            'quote_ccy': market_info['info'].get('quoteCcy', symbol.split('-')[1]),
            'type': 1 if side == OrderSide.BUY.value else 2,
            'order_type': OrderType.MARKET.value,
            'amount': float(order['amount']),
            'clinch_number': filled_amount,
            'price': last_price,
            'profit': profit,
            'pair': pair_id,
            'currency1': valuation['btc_balance'],
            'currency2': valuation['usdt_balance'],
            'balanced_valuation': new_valuation['usdt_valuation'],
            'make_deal_price': avg_price,
            'time': datetime.now()
        }
        
        # 保存订单到数据库
        order_record = self.crud.create_piggybank(order_data)
        
        # 如果找到配对订单，更新配对信息
        if pair_id:
            self.crud.db.query(Piggybank).filter(Piggybank.id == pair_id).update({
                'pair': order_record.id,
                'profit': profit
            })
            self.crud.db.commit()
        
        return True
    
    def _get_pair_info(self, side: str, price: float, amount: float, symbol: str) -> Tuple[Optional[int], float]:
        """获取配对订单信息和利润"""
        last_order = self.crud.get_last_piggybank(self.get_exchange_name(), symbol)
        
        if not last_order or last_order.pair != 0:
            return None, 0.0
        
        if (side == OrderSide.SELL.value and price > last_order.price) or \
           (side == OrderSide.BUY.value and price < last_order.price):
            profit = amount * (price - last_order.price) if side == OrderSide.SELL.value else \
                     last_order.clinch_number * (last_order.price - price)
            return last_order.id, profit
        
        return None, 0.0