from typing import List, Optional, Union
from fastapi import Depends
from pydantic import BaseModel


class AccountBalancesModel(BaseModel):
    api_key: str
    secret_key: str
    passphrase: str
    ccy: Optional[str] = None


class CommonResponse(BaseModel):
    status: str
    message: str
    data: Optional[Union[str, List[dict], dict]] = None
