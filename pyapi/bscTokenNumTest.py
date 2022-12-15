# encoding:utf-8
import sys,os
import json
import re
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from flask import Flask, jsonify, request, make_response
# 实现规避检测
# from selenium.webdriver import ChromeOptions
from selenium.webdriver.common.by import By
import time
from web3 import Web3
from web3 import Web3, HTTPProvider
from tools import toWei, fromWei

# app = Flask(__name__)

class JDSpider(object):
    def __init__(self):
        self.rpcUrls = 'https://bsc-dataseed.binance.org'
        self.web3Client = Web3(HTTPProvider(self.rpcUrls))
        self.h2oRouterContractAddress = "0x96948447D1521260c24fCdE281d09364BdC5A2d0"
        self.cakeRouterContractAddress = "0x10ED43C718714eb63d5aA57B78B54704E256024E"
        self.USDT = "0x55d398326f99059fF775485246999027B3197955"
        self.BUSDT = "0xe9e7CEA3DedcA5984780Bafc599bD69ADd087D56"
        self.WBNB = "0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c"
        with open(str(os.path.join('./abis/mdexABI.json')), 'r') as abi_definition:   
            self.mdexABI = json.load(abi_definition)
        with open(str(os.path.join('./abis/cakeRouter.json')), 'r') as abi_definition:   
            self.cakeRouter = json.load(abi_definition)

        # print(sys.argv[1])
        os.system("killall -9 chrome")
        os.system("killall -9 chromedriver")
        # 创建浏览器对象，并实现让selenium 规避检测
        options = Options()
        options.add_argument('--no-sandbox')
        options.add_argument('--disable-dev-shm-usage')
        options.add_argument('--headless')
        options.add_argument('blink-settings=imagesEnabled=false') #不加载图片, 提升速度
        options.add_argument('--window-size=1920,1080')
        options.add_argument('--disable-gpu') #谷歌文档提到需要加上这个属性来规避bug
        path = "/usr/local/bin/chromedriver"# 注意这个路径需要时可执行路径（chmod 777 dir or 755 dir）
        # chrome_options = webdriver.ChromeOptions()
        # # 添加UA
        # chrome_options.add_argument('user-agent="MQQBrowser/26 Mozilla/5.0 (Linux; U; Android 2.3.7; zh-cn; MB200 Build/GRJ22; CyanogenMod-7) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1"')
        # chrome_options.add_argument('blink-settings=imagesEnabled=false') 
        # # 指定浏览器分辨率
        # chrome_options.add_argument('window-size=1920x3000') 
        # # 以最高权限运行
        # chrome_options.add_argument('--no-sandbox')
        # chrome_options.add_experimental_option('excludeSwitched', ['enable-automaytion'])
        # # 禁用浏览器弹窗
        # prefs = {  
        #     'profile.default_content_setting_values' :  {  
        #         'notifications' : 2  
        #     }  
        # }  
        # chrome_options.add_experimental_option('prefs',prefs)
        # chrome_options.add_argument('--disable-blink-features=AutomationControlled')
        self.browser = webdriver.Chrome(executable_path=path, options=options)
        # self.browser = webdriver.Chrome(chrome_options=chrome_options)
        self.browser.implicitly_wait(5)  # 等5s
        # self.address = "0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82"
        self.bscscan_url = f'https://bscscan.com/token/'
        self.bscscan_bnb_url = f'https://bscscan.com/blocks?p=1'
        self.returnList = {}

    # 加载所有数据 屏幕滚动
    def getListDataPage(self):
        while self.num < self.count:
            if(self.page <= 20):
                self.getListData()
                self.page += 1
            else:
                break
        return

    # 爬取数据币种供应量和价格
    def getListData(self, token, chain):
        # urls = self.bscscan_url + str(token)
        if chain == 'etherscan':
            urls = f'https://www.oklink.com/zh-cn/eth'
            self.browser.get(urls)
            time.sleep(5)
            # 获取地址数量
            HoldersDom = self.browser.find_element_by_xpath('//*[@id="root"]/main/div/div[3]/div[1]/div[3]')
            HoldersList = HoldersDom.text.split('\n')
            BalanceValueDom = self.browser.find_element_by_xpath('//*[@id="root"]/main/div/div[3]/div[1]/div[2]')
            BalanceValueList = BalanceValueDom.text.split('\n')
            # print(BalanceValueList)
            holders = re.sub("[^0-9.]", "", HoldersList[2])
        else:
            urls = f'https://{chain}.com/token/' + str(token) + '?a=0x000000000000000000000000000000000000dead'
            # print(self.page, self.num, self.count, urls)
            self.browser.get(urls)
            time.sleep(5)
            # 获取地址数量
            HoldersDom = self.browser.find_element_by_class_name('card-body')
            HoldersList = HoldersDom.text.split('\n')
            print(HoldersList)
            holders = re.sub("[^0-9.]", "", HoldersList[7])


        # 获取价格
        if token == "0xF1932eC9784B695520258F968b9575724af6eFa8": # Guru
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.BUSDT, self.WBNB)
        elif token == "0x3B5E381130673F794a5CF67FBbA48688386BEa86": # POT
            price = self.getToken2TokenPrice(self.cakeRouterContractAddress, token, self.USDT)
        elif token == "0xC446c2B48328e5D2178092707F8287289ED7e8D6": # H2O
            price = self.getTokenPrice(self.h2oRouterContractAddress, token, self.USDT, '')
        elif token == "0xc748673057861a797275CD8A068AbB95A902e8de": # BabyDoge
            returnPrice = self.getTokenPrice(self.cakeRouterContractAddress, token, self.USDT, '')
            price = fromWei(returnPrice, 9)
        elif token == "0x02fF5065692783374947393723dbA9599e59F591": # YOOSHI
            returnPrice = self.getTokenPrice(self.cakeRouterContractAddress, token, self.USDT, '')
            price = fromWei(returnPrice, 9)
        elif token == "0x8C851d1a123Ff703BD1f9dabe631b69902Df5f97": # BNX
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.USDT, '')
            # print(price)
        elif token == "0x602bA546A7B06e0FC7f58fD27EB6996eCC824689": # PinkSale
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.BUSDT, self.WBNB)
            # print(price)
        elif token == "0x2d716831D82d837C3922aD8c10FE70757b5814FE": # FORTUNE
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.BUSDT, self.WBNB)
            # print(price)
        elif token == "0xd0BA1Cad35341ACd1CD88a85E16B054bA9ccC385": # MYPO
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.BUSDT, self.WBNB)
            # print(price)
        elif token == "0x03D6BD3d48F956D783456695698C407A46ecD54d": # HYPR
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.BUSDT, self.WBNB)
            # print(price)
        elif token == "0xb0B2d54802B018B393A0086a34DD4c1f26F3D073": # AUDIO
            price = self.getTokenPrice(self.cakeRouterContractAddress, token, self.BUSDT, self.WBNB)
            # print(price)
        elif token == "0x36E714D63B676236B72a0a4405F726337b06b6e5": # GUT
            price = self.getToken2TokenPrice(self.cakeRouterContractAddress, token, self.USDT)
        elif token == "0x582d872A1B094FC48F5DE31D3B73F2D9bE47def1": # ETH
            ethUrls = f'https://www.oklink.com/zh-cn/eth/address/{token}'
            self.browser.get(ethUrls)
            time.sleep(5)
            priceElement = self.browser.find_element_by_xpath('//*[@id="root"]/main/div/div/div[3]/div/div[2]/div/div/div[1]/div[2]/span[2]')
            priceStr = priceElement.text.split('\n')
            price = re.sub("[^0-9.]", "", priceStr[0])
            # print(price)
        else:
            priceElement = self.browser.find_element_by_xpath('//*[@id="ContentPlaceHolder1_tr_valuepertoken"]/div/div[1]/span/span[1]')
            priceStr = priceElement.get_attribute('data-title')
            price = re.sub("[^0-9.]", "", priceStr)

        # 获取余额及价值
        if token == "0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c":
            self.browser.get(self.bscscan_bnb_url)
            balanceValueElement = self.browser.find_element_by_xpath('//*[@id="content"]/div[1]/div/div[2]/span/span[2]')
            balanceStr = balanceValueElement.text.split('\n')
            balance = re.sub("[^0-9.]", "", balanceStr[0])
            valueStr = balanceValueElement.get_attribute('data-original-title')
            value = re.sub("[^0-9.]", "", valueStr)
            # print(balance, value)
        elif token == "0x582d872A1B094FC48F5DE31D3B73F2D9bE47def1":
            balance = re.sub("[^0-9.]", "", BalanceValueList[2])
            value = re.sub("[^0-9.]", "", BalanceValueList[15])
        else:
            balanceValueElement = self.browser.find_element_by_xpath('//*[@id="ContentPlaceHolder1_filteredByAddress"]')
            balanceValueList = balanceValueElement.text.split('\n')
            # print(balanceValueList)
            balance = re.sub("[^0-9.]", "", balanceValueList[3])
            valueArr = balanceValueList[5].split(' ')
            value = re.sub("[^0-9.]", "", valueArr[0])
            # print(balance, value)
        self.returnList = {'price': price, 'holders': holders, 'balance': balance, 'value': value}
        return self.returnList

    def isElementExist(self, browser, element):
        flag = True
        try:
            browser.find_element_by_css_selector(element)
            flag = False
            return flag
        except:
            return flag
    
    # 获取价格估值
    def getTokenPrice(self, routerAddress, token1, token2, bnbAddress):
        Gwei1 = 1000000000
        path = []
        if bnbAddress or bnbAddress != '':
            path = [token1, bnbAddress, token2]
        else:
            path = [token1, token2]
        try:
            price = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(routerAddress), abi=self.mdexABI)
            result = contract.functions.getAmountsOut(Gwei1, path).call()
            print(result)
            if(result):
                if bnbAddress or bnbAddress != '':
                    price = result[2] / Gwei1
                else:
                    price = result[1] / Gwei1
            else:
                print('getTokenPrice_err')
            return price
        except Exception as re:
            print('functions getTokenPrice->getAmountsOut error %s' %re)
    
    # 获取估值 01
    def getToken2TokenPrice(self, routerAddress, token0, token1, amount = 1):
        price = 1
        path = []
        path = [token0, token1]
        try:
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(routerAddress), abi=self.cakeRouter)
            result = contract.functions.getAmountsOut(toWei(amount, 18), path).call()
            if(result or result[1]):
                price = fromWei(result[1], 18)
            else:
                print('getToken2TokenPrice_err')
            return price
        except Exception as re:
            print('functions getToken2TokenPrice->getAmountsOut error %s' %re)

    # 入口
    def main(self, token, chain):
        try:
            list = self.getListData(token, chain)
            os.system("killall -9 chrome")
            os.system("killall -9 chromedriver")
            return list
            # return
        except IndexError:
            self.browser.quit() 
            os.system("killall -9 chrome")
            os.system("killall -9 chromedriver")
        # print(apr)


if __name__ == "__main__":
    # searchName = sys.argv[1] # 接受参数
    # print(searchName)
    spider = JDSpider()
    data = spider.getListData('0x582d872A1B094FC48F5DE31D3B73F2D9bE47def1', 'etherscan')
    json_str = json.dumps(data)
    print(json_str)

# 获取bsc指定币种钱包地址数量及价格
# @app.route('/get_bsc_token_holders', methods=['POST'])
# def get_apy_mars_data():
#     # if request.content_type == 'application/json':
#     pageJson = request.json
#     spider = JDSpider()
#     data = spider.main(pageJson['token'], pageJson['chain'])
#     # json_str = json.dumps(data)
#     response = make_response(jsonify(data))
#     response.mimetype = 'application/json'
#     return response

# @app.route('/')
# def hello_world():
#     apis = [
#         {
#             'name': 'pyapi',
#             'version': '1.0',
#         }
#     ]
#     return make_response(jsonify(apis), 200)


# app.run(host='0.0.0.0', port=8013, debug=True)