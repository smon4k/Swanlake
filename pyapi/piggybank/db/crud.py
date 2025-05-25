from sqlalchemy.orm import Session
from datetime import datetime
from .models import Piggybank, PiggybankPendord, PiggybankDate
from typing import Optional, Dict, List

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
            .order_by(Piggybank.id.desc())\
            .first()
    
    # PiggybankPendord 操作
    def create_pendord(self, data: Dict) -> PiggybankPendord:
        pendord = PiggybankPendord(**data)
        self.db.add(pendord)
        self.db.commit()
        self.db.refresh(pendord)
        return pendord
    
    def get_pending_orders(self, exchange: str, symbol: str = None) -> List[PiggybankPendord]:
        query = self.db.query(PiggybankPendord)\
            .filter(PiggybankPendord.exchange == exchange, PiggybankPendord.status == 1)
        if symbol:
            query = query.filter(PiggybankPendord.product_name == symbol)
        return query.order_by(PiggybankPendord.id.desc()).all()
    
    def update_pendord_status(self, exchange: str, order_id: str, status: int) -> bool:
        self.db.query(PiggybankPendord)\
            .filter(PiggybankPendord.exchange == exchange, PiggybankPendord.order_id == order_id)\
            .update({'status': status, 'up_time': datetime.now()})
        self.db.commit()
        return True
    
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