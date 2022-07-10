# encoding:utf-8
from bs4 import BeautifulSoup
import requests
import sys

def main(token):
    req_obj = requests.get('https://polygonscan.com/token/0x96e7593e376a8f75fd52ae71b7b45358ef373ae8?a='+token)
    req_obj.encoding='utf-8'
    soup = BeautifulSoup(req_obj.text, 'lxml')

    guruNum = soup.find('div',id='ContentPlaceHolder1_divFilteredHolderBalance').get_text()
    print(guruNum)

def ceshi(a,b,c):
    d = int(a)+int(b)
    r = int(d)+c
    print(r)

if __name__ == "__main__":
    # ceshi(a=1,b=3,c = 5)
    token = sys.argv[1]
    main(token)