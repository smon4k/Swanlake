import os
from dotenv import load_dotenv

load_dotenv()

class OkxConfig:
    API_KEY = os.getenv('OKX_API_KEY', '68ca4b58-f022-42e1-8055-8fd565bc4eff')
    SECRET_KEY = os.getenv('OKX_SECRET_KEY', 'BD573AF9E7B806503C9AB25B255BD62D')
    PASSPHRASE = os.getenv('OKX_PASSPHRASE', 'Zx112211@')