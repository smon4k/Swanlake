import os
from dotenv import load_dotenv

load_dotenv()

class OkxConfig:
    API_KEY = os.getenv('OKX_API_KEY', '236b8938-f693-448d-93fd-4d8f6d7cc40a')
    SECRET_KEY = os.getenv('OKX_SECRET_KEY', '91216FFD5ED06E24DEBE5552FDEE6AA3')
    PASSPHRASE = os.getenv('OKX_PASSPHRASE', 'Zx112211@Q')
    IS_SIMULATION = os.getenv('IS_SIMULATION', '1')
    IS_LOCAL = os.getenv('IS_LOCAL', 'dev').lower() == 'dev'  # 是否本地环境