# encoding:utf-8
import sys,os
import json
import re
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from flask import Flask, jsonify, request, make_response
# 实现规避检测
from selenium.webdriver import ChromeOptions
from selenium.webdriver.common.by import By
import time
from web3 import Web3

app = Flask(__name__)

class JDSpider(object):
    def __init__(self):
        # print(sys.argv[1])
        os.system("killall -9 chrome")
        os.system("killall -9 chromedriver")
        chrome_options = Options()
        chrome_options.add_argument('--no-sandbox')
        chrome_options.add_argument('--disable-dev-shm-usage')
        chrome_options.add_argument('--headless')
        chrome_options.add_argument('blink-settings=imagesEnabled=false') #不加载图片, 提升速度
        chrome_options.add_argument('--window-size=1920,1080')
        chrome_options.add_argument('--disable-gpu') #谷歌文档提到需要加上这个属性来规避bug
        # 创建浏览器对象，并实现让selenium 规避检测
        # option = ChromeOptions()
        # option.add_experimental_option('excludeSwitched', ['enable-automaytion'])
        path = "/usr/local/bin/chromedriver"# 注意这个路径需要时可执行路径（chmod 777 dir or 755 dir）
        # self.browser = webdriver.Chrome(executable_path=path, options=chrome_options)
        self.browser = webdriver.Chrome(options=chrome_options)
        self.browser.implicitly_wait(5)  # 等5s
        # self.address = "0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82"
        self.url = f'https://bscscan.com/token/'
        
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

    # 爬取数据
    def getListData(self, token):
        urls = self.url + str(token)
        # print(self.page, self.num, self.count, urls)
        self.browser.get(urls)
        # time.sleep(5)

        # 获取地址数量
        HoldersDom = self.browser.find_element_by_class_name('card-body')
        HoldersList = HoldersDom.text.split('\n')
        holders = re.sub("[^0-9.]", "", HoldersList[7])

        # 获取价格
        priceElement = self.browser.find_element_by_xpath('//*[@id="ContentPlaceHolder1_tr_valuepertoken"]/div/div[1]/span/span[1]')
        priceStr = priceElement.get_attribute('data-title')
        price = re.sub("[^0-9.]", "", priceStr)
        self.returnList = {'price': price, 'holders': holders}
        return self.returnList

    def isElementExist(self, browser, element):
        flag = True
        try:
            browser.find_element_by_css_selector(element)
            flag = False
            return flag
        except:
            return flag

    # 入口
    def main(self, token):
        try:
            list = self.getListData(token)
            os.system("killall -9 chrome")
            os.system("killall -9 chromedriver")
            return list
            # return
        except IndexError:
            self.browser.quit() 
            os.system("killall -9 chrome")
            os.system("killall -9 chromedriver")
        # print(apr)


# if __name__ == "__main__":
#     # searchName = sys.argv[1] # 接受参数
#     # print(searchName)
#     spider = JDSpider()
#     data = spider.main('0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82')
#     json_str = json.dumps(data)
#     print(json_str)

# 获取bsc指定币种钱包地址数量及价格
@app.route('/get_bsc_token_holders', methods=['POST'])
def get_apy_mars_data():
    # if request.content_type == 'application/json':
    pageJson = request.json
    spider = JDSpider()
    data = spider.main(pageJson['token'])
    # json_str = json.dumps(data)
    response = make_response(jsonify(data))
    response.mimetype = 'application/json'
    return response

@app.route('/')
def hello_world():
    apis = [
        {
            'name': 'pyapi',
            'version': '1.0',
        }
    ]
    return make_response(jsonify(apis), 200)


app.run(host='0.0.0.0', port=8013, debug=True)