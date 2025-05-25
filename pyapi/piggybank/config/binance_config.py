import os
from dotenv import load_dotenv

load_dotenv()

class BinanceConfig:
    API_KEY = os.getenv('BINANCE_API_KEY', 'your_binance_api_key')
    SECRET_KEY = os.getenv('BINANCE_SECRET_KEY', 'your_binance_secret_key')