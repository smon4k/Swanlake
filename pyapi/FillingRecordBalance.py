# encoding:utf-8
# 获取游戏充提余额 数据
import json, os, string, random, re, requests
from hexbytes import HexBytes
from decimal import Decimal
from flask import Flask, jsonify, request, make_response
from web3 import Web3, HTTPProvider
from web3.contract import ConciseContract
from tools import toWei, fromWei

app = Flask(__name__)


class JDSpider(object):
    def __init__(self):
        self.poolsList = []
        self.rpcUrls = 'https://bsc-dataseed.binance.org'
        self.web3Client = Web3(HTTPProvider(self.rpcUrls))
        self.sctFillingAddress = "0xdA9A81cf2000fc4df10362bA58EF4607d82E57BE"
        self.sstFillingAddress = "0xdA9A81cf2000fc4df10362bA58EF4607d82E57BE"
        self.gamesFillingAddress = "0x079bDC8845D0C6878716A3f5219f1D0DcdF15308"
        self.routerContractAddress = "0x96948447D1521260c24fCdE281d09364BdC5A2d0"
        self.SCT = "0xC446c2B48328e5D2178092707F8287289ED7e8D6"
        self.SST = "0xC446c2B48328e5D2178092707F8287289ED7e8D6"
        self.USDT = "0x55d398326f99059fF775485246999027B3197955"
        #读取abi文件
        with open(str(os.path.join('./abis/gameFillingABI.json')), 'r') as abi_definition:   
            self.gameFillingABI = json.load(abi_definition)
        with open(str(os.path.join('./abis/mdexABI.json')), 'r') as abi_definition:   
            self.mdexABI = json.load(abi_definition)

    # 获取CS余额
    def getGameFillingBalance(self, address):
        try:
            num = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.gamesFillingAddress), abi=self.gameFillingABI)
            result = contract.functions.userInfo(Web3.toChecksumAddress(address)).call()
            num = fromWei(result[0], 18)
            if result[1]:
                num = float("-"+num)
            return num
        except Exception as re:
            print('functions getHTokenAddress error %s' %re)

    # 获取SCT余额
    def getGameFillingSCTBalance(self, address):
        try:
            num = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.sctFillingAddress), abi=self.gameFillingABI)
            result = contract.functions.userInfo(Web3.toChecksumAddress(address)).call()
            num = fromWei(result[0], 18)
            if result[1]:
                num = float("-"+num)
            return num
        except Exception as re:
            print('functions getHTokenAddress error %s' %re)

    # 获取SST余额
    def getGameFillingSSTBalance(self, address):
        try:
            num = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.sstFillingAddress), abi=self.gameFillingABI)
            result = contract.functions.userInfo(Web3.toChecksumAddress(address)).call()
            num = fromWei(result[0], 18)
            if result[1]:
                num = float("-"+num)
            return num
        except Exception as re:
            print('functions getHTokenAddress error %s' %re)

    # 获取SCT估值 01
    def getSCTTokenPrice(self):
        Gwei1 = 1000000000
        try:
            price = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.routerContractAddress), abi=self.mdexABI)
            result = contract.functions.getAmountsOut(Gwei1, [self.SCT , self.USDT]).call()
            # print(result)
            if(result or result[1]):
                price = result[1] / Gwei1
            else:
                print('getToken2TokenPrice_err')
            return price
        except Exception as re:
            print('functions getToken2TokenPrice->getAmountsOut error %s' %re)

    # 获取SST估值 01
    def getSCSTokenPrice(self):
        Gwei1 = 1000000000
        try:
            price = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.routerContractAddress), abi=self.mdexABI)
            result = contract.functions.getAmountsOut(Gwei1, [self.SST , self.USDT]).call()
            # print(result)
            if(result or result[1]):
                price = result[1] / Gwei1
            else:
                print('getToken2TokenPrice_err')
            return price
        except Exception as re:
            print('functions getToken2TokenPrice->getAmountsOut error %s' %re)
    

# if __name__ == "__main__":
#     # searchName = sys.argv[1] # 接受参数
#     # print(searchName)
#     spider = JDSpider()
#     data = spider.getGameFillingBalance('0x7DCBFF9995AC72222C6d46A45e82aA90B627f36D')
#     json_str = json.dumps(data)
#     print(json_str)

@app.route('/v1.0/get_filling_balance', methods=['POST'])
def getGameFillingBalance():
    # if request.content_type == 'application/json':
    pageJson = request.json
    spider = JDSpider()
    data = spider.getGameFillingBalance(pageJson['address'])
    # print(data)
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

@app.route('/v1.0/get_filling_sct_balance', methods=['POST'])
def getGameFillingSCTBalance():
    # if request.content_type == 'application/json':
    pageJson = request.json
    spider = JDSpider()
    data = spider.getGameFillingSCTBalance(pageJson['address'])
    # print(data)
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

@app.route('/v1.0/get_filling_sst_balance', methods=['POST'])
def getGameFillingSSTBalance():
    # if request.content_type == 'application/json':
    pageJson = request.json
    spider = JDSpider()
    data = spider.getGameFillingSSTBalance(pageJson['address'])
    # print(data)
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

@app.route('/v1.0/get_sct_price', methods=['POST'])
def getSCTPrice():
    # if request.content_type == 'application/json':
    # pageJson = request.json
    spider = JDSpider()
    data = spider.getSCTTokenPrice()
    # print(data)
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

@app.route('/v1.0/get_sst_price', methods=['POST'])
def getSSTPrice():
    # if request.content_type == 'application/json':
    # pageJson = request.json
    spider = JDSpider()
    data = spider.getSSTTokenPrice()
    # print(data)
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

app.run(host='0.0.0.0', port=8011, debug=True)
