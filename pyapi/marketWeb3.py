# encoding:utf-8
import json
from logging import error
from hexbytes import HexBytes
from web3 import Web3, HTTPProvider
from flask import Flask, jsonify, request, make_response

app = Flask(__name__)

class HexJsonEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, HexBytes):
            return obj.hex()
        return super().default(obj)

class JDSpider(object):
    def __init__(self):
        # print(sys.argv[1])
        rpcUrls = 'https://bsc-dataseed.binance.org'
        self.web3Client = Web3(HTTPProvider(rpcUrls))
    

    # 返回匹配指定交易哈希值的交易
    def getTransaction(self, hash):
        try:
            result = self.web3Client.eth.getTransaction(hash)
            return result
        except IndexError:
            return False

    # 返回匹配指定交易收据
    def getTransactionReceipt(self, hash):
        try:
            result = self.web3Client.eth.getTransactionReceipt(hash)
            return result
        except IndexError:
            return False

    # 查看账户余额
    def getBalance(self, address):
        try:
            result = self.web3Client.eth.getBalance(address)
            return result
        except IndexError:
            return False

    def toJSON(self):
        return json.dumps(self, default=lambda o: o.__dict__, 
            sort_keys=True, indent=4)

# if __name__ == "__main__":
    # searchName = sys.argv[1] # 接受参数
    # print(searchName)
    # spider = JDSpider()
    # data = spider.main("")
    # json_str = json.dumps(data)
    # print(json_str)

@app.route('/v1.0/get_transaction', methods=['POST'])
def get_transaction():
    # if request.content_type == 'application/json':
    pageJson = request.json
    spider = JDSpider()
    txData = spider.getTransactionReceipt(pageJson['hash'])
    tx_dict = dict(txData)
    # print(tx_dict)
    # tx_json = json.dumps(tx_dict, cls=HexJsonEncoder)
    tx_json = json.dumps(tx_dict, default=lambda o: o.__dict__, 
            sort_keys=True, indent=4)
    # print(tx_json)
    # response = make_response(tx_dict)
    # response.mimetype = 'application/json'
    return tx_json

@app.route('/')
def hello_world():
    apis = [
        {
            'name': 'pyapi',
            'version': '1.0',
        }
    ]
    return make_response(jsonify(apis), 200)


app.run(host='0.0.0.0', port=8012, debug=True)