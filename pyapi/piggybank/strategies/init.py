from decimal import Decimal
from typing import Dict

from pyapi.piggybank.config.constants import OrderSide


def safe_float(value, default=0.0):
    if value is None:
        return default
    try:
        return float(value)
    except (TypeError, ValueError):
        return default