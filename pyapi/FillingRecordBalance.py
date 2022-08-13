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
        self.h2oFillingAddress = "0xdA9A81cf2000fc4df10362bA58EF4607d82E57BE"
        self.gamesFillingAddress = "0x079bDC8845D0C6878716A3f5219f1D0DcdF15308"
        #读取abi文件
        with open(str(os.path.join('./abis/gameFillingABI.json')), 'r') as abi_definition:   
            self.gameFillingABI = json.load(abi_definition)

    # 获取CS余额
    def getGameFillingBalance(self, address):
        try:
            num = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.gamesFillingAddress), abi=self.gameFillingABI)
            result = contract.functions.userInfo(address).call()
            num = fromWei(result[0], 18)
            if result[1]:
                num = float("-"+num)
            return num
        except Exception as re:
            print('functions getHTokenAddress error %s' %re)

    # 获取H2O余额
    def getGameFillingH2OBalance(self, address):
        try:
            num = 0
            contract = self.web3Client.eth.contract(address=Web3.toChecksumAddress(self.h2oFillingAddress), abi=self.gameFillingABI)
            result = contract.functions.userInfo(address).call()
            num = fromWei(result[0], 18)
            if result[1]:
                num = float("-"+num)
            return num
        except Exception as re:
            print('functions getHTokenAddress error %s' %re)


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

@app.route('/v1.0/get_filling_h2o_balance', methods=['POST'])
def getGameFillingH2OBalance():
    # if request.content_type == 'application/json':
    pageJson = request.json
    spider = JDSpider()
    data = spider.getGameFillingH2OBalance(pageJson['address'])
    # print(data)
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

app.run(host='0.0.0.0', port=8011, debug=True)
