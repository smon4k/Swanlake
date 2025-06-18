from sqlalchemy.orm import Session
from datetime import datetime
from .models import Piggybank, PiggybankPendord, PiggybankDate
from typing import Optional, Dict, List
from sqlalchemy import func

class CRUD:
    def __init__(self, db: Session):
        self.db = db
    
    # Piggybank 操作
    def create_piggybank(self, data: Dict) -> Piggybank:
        piggybank = Piggybank(**data)
        self.db.add(piggybank)
        self.db.commit()
        self.db.refresh(piggybank)
        return piggybank
    
    def get_last_piggybank(self, exchange: str, symbol: str) -> Optional[Piggybank]:
        return self.db.query(Piggybank)\
            .filter(Piggybank.exchange == exchange, Piggybank.product_name == symbol)\
            .order_by(Piggybank.id.desc(), Piggybank.time.desc())\
            .first()
    
    # 获取上一次平衡估值
    def get_last_balanced_valuation(self, exchange: str, symbol: str) -> float:
        """Get the last balanced valuation"""
        data = self.db.query(Piggybank)\
            .filter(Piggybank.exchange == exchange, Piggybank.product_name == symbol)\
            .order_by(Piggybank.id.desc(), Piggybank.time.desc())\
            .first()
        if data:
            return data.balanced_valuation
        return 0
    
    # 创建新的 PiggybankPendord 操作
    def create_piggybank_pendord(self, data: Dict) -> PiggybankPendord:
        pendord = PiggybankPendord(**data)
        self.db.add(pendord)
        self.db.commit()
        self.db.refresh(pendord)
        return pendord
    
    # 获取当前挂单
    def get_open_pending_orders(self, exchange: str) -> Dict[str, Optional[PiggybankPendord]]:
        """Get current pending orders separated by type"""
        data = self.db.query(PiggybankPendord)\
            .filter(PiggybankPendord.exchange == exchange, PiggybankPendord.status == 1)\
            .order_by(PiggybankPendord.id.desc(), PiggybankPendord.time.desc())\
            .limit(2)\
            .all()
        pending_order_list = {}
        for order in data:
            if order.type == 1:
                pending_order_list['buy'] = order
            elif order.type == 2:
                pending_order_list['sell'] = order
        return pending_order_list
    
    # 更新挂单的成交金额和状态
    def update_clinch_amount(self, exchange: str, order_id: str, deal_amount: float, status: int) -> bool:
        """Update the clinch amount and status of a pending order"""
        self.db.query(PiggybankPendord)\
            .filter(PiggybankPendord.exchange == exchange, PiggybankPendord.order_id == order_id)\
            .update({'status': status, 'clinch_amount': deal_amount, 'up_time': datetime.now()})
        self.db.commit()
        return True
    
    # 更新挂单的状态
    def update_pendord_status(self, exchange: str, order_id: str, deal_amount: float, status: int) -> bool:
        try:
            result = self.db.query(PiggybankPendord)\
                .filter(PiggybankPendord.exchange == exchange, PiggybankPendord.order_id == order_id)\
                .update({'status': status, 'clinch_amount': deal_amount, 'up_time': datetime.now()})
            if result == 0:
                raise ValueError(f"No pending order found with exchange '{exchange}' and order_id '{order_id}'")
            self.db.commit()
            return True
        except Exception as e:
            self.db.rollback()
            print(f"Error updating pending order status: {e}")
            return False
    
    # 获取挂单并计算利润
    def get_pair_and_calculate_profit(self, exchange: str, type: int, deal_price: float) -> Optional[Dict]:
        """
        获取配对id和利润，逻辑与原PHP一致:
        查询type=2且pair=0的记录，按time倒序和价格差绝对值排序，取第一条，
        如果deal_price < price，则计算利润
        """
        result = self.db.query(Piggybank.id, Piggybank.price, Piggybank.clinch_number)\
            .filter(Piggybank.type == type, Piggybank.pair == 0, Piggybank.exchange == exchange)\
            .order_by(Piggybank.time.desc(), func.abs(deal_price - Piggybank.price))\
            .first()
        if (
            result
            and result.price is not None
            and result.clinch_number is not None
            and deal_price < result.price
        ):
            pair_id = result.id
            profit = float(result.clinch_number) * (float(result.price) - deal_price)
            return {"pair_id": pair_id, "profit": profit}
        return None
    
    def revoke_all_pending_orders(self, exchange: str) -> bool:
        """Revoke all pending orders by setting their status to 3"""
        try:
            result = self.db.query(PiggybankPendord)\
                .filter(PiggybankPendord.exchange == exchange, PiggybankPendord.status == 1)\
                .update({'status': 3, 'up_time': datetime.now()})
            self.db.commit()
            return result > 0
        except Exception as e:
            self.db.rollback()
            print(f"Error revoking pending orders: {e}")
            return False

    
    # 更新配对和利润
    def update_pair_and_profit(self, pair_id: int, profit: float) -> bool:
        """Update pair and profit for a specific Piggybank entry"""
        try:
            result = self.db.query(Piggybank)\
                .filter(Piggybank.id == pair_id)\
                .update({'pair': pair_id, 'profit': profit, 'up_time': datetime.now()})
            if result == 0:
                raise ValueError(f"No Piggybank entry found with id '{pair_id}'")
            self.db.commit()
            return True
        except Exception as e:
            self.db.rollback()
            print(f"Error updating pair and profit: {e}")
            return False
        finally:
            self.db.close()
    
    # PiggybankDate 操作
    def create_or_update_piggybank_date(self, data: Dict) -> PiggybankDate:
        existing = self.db.query(PiggybankDate)\
            .filter(
                PiggybankDate.exchange == data['exchange'],
                PiggybankDate.product_name == data['product_name'],
                PiggybankDate.date == data['date']
            )\
            .first()
        
        if existing:
            for key, value in data.items():
                setattr(existing, key, value)
            existing.up_time = datetime.now()
            self.db.commit()
            self.db.refresh(existing)
            return existing
        
        piggybank_date = PiggybankDate(**data)
        self.db.add(piggybank_date)
        self.db.commit()
        self.db.refresh(piggybank_date)
        return piggybank_date
    
    def get_last_deal_price(self, exchange: str, symbol: str) -> float:
        """Get the last balanced valuation"""
        data = self.db.query(Piggybank)\
            .filter(Piggybank.exchange == exchange, Piggybank.product_name == symbol)\
            .order_by(Piggybank.id.desc(), Piggybank.time.desc())\
            .first()
        if data:
            return data.price
        return 0
