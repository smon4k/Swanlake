from sqlalchemy import Column, Integer, String, Float, DateTime, Boolean
from .base import Base

class Piggybank(Base):
    __tablename__ = 'p_piggybank'
    
    id = Column(Integer, primary_key=True)
    exchange = Column(String(20))
    product_name = Column(String(50))
    order_id = Column(String(100))
    order_number = Column(String(100))
    td_mode = Column(String(20))
    base_ccy = Column(String(20))
    quote_ccy = Column(String(20))
    type = Column(Integer)  # 1: buy, 2: sell
    order_type = Column(String(20))
    amount = Column(Float)
    clinch_number = Column(Float)
    price = Column(Float)
    profit = Column(Float)
    pair = Column(Integer)
    currency1 = Column(Float)
    currency2 = Column(Float)
    balanced_valuation = Column(Float)
    make_deal_price = Column(Float)
    time = Column(DateTime)

class PiggybankPendord(Base):
    __tablename__ = 'p_piggybank_pendord'
    
    id = Column(Integer, primary_key=True)
    exchange = Column(String(20))
    product_name = Column(String(50))
    symbol = Column(String(50))
    order_id = Column(String(100))
    order_number = Column(String(100))
    type = Column(Integer)  # 1: buy, 2: sell
    order_type = Column(String(20))
    amount = Column(Float)
    clinch_amount = Column(Float)
    price = Column(Float)
    currency1 = Column(Float)
    currency2 = Column(Float)
    clinch_currency1 = Column(Float)
    clinch_currency2 = Column(Float)
    status = Column(Integer)  # 1: pending, 2: filled, 3: canceled
    time = Column(DateTime)
    up_time = Column(DateTime)

class PiggybankDate(Base):
    __tablename__ = 'p_piggybank_date'
    
    id = Column(Integer, primary_key=True)
    exchange = Column(String(20))
    product_name = Column(String(50))
    date = Column(String(20))
    count_market_value = Column(Float)
    grid_spread = Column(Float)
    grid_spread_rate = Column(Float)
    grid_day_spread = Column(Float)
    grid_day_spread_rate = Column(Float)
    average_day_rate = Column(Float)
    average_year_rate = Column(Float)
    up_time = Column(DateTime)