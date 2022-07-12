<?php
// +----------------------------------------------------------------------
// | 文件说明：理财产品 api
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-07-08
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\api\model\Product;
use app\api\model\MyProduct;
use app\api\model\User;
use app\api\model\DayNetworth;
use app\api\model\ProductDetails;
use think\Request;
use think\Db;
use think\Controller;
use ClassLibrary\ClFile;
use ClassLibrary\CLUpload;
use ClassLibrary\CLFfmpeg;
use lib\Filterstring;

error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '-1');
class ProductController extends BaseController
{
    // private $_cache;
    // public function __construct(Request $request)
    // {
    //     $address = $request->request('address', '', 'trim');
    //     if ($address == "") {
    //         $address = $request->request('user_address', '', 'trim');
    //     }

    //     //这个key记录三方标识
    //     $key = "H2O:Media:AddressTimeKeys_" . $address;
    //     //限制请求时间内
    //     $time = 3;
    //     //限制次数为3
    //     $limit = 5;
    //     $this->_cache = new \Redis();
    //     $this->_cache->connect(Config('redis')['redisprod']['host'], Config('redis')['redisprod']['port']); //连接 Redis
    //     $this->_cache->select(1);
    //     $check = $this->_cache->ttl($key); //-1,未设置过期, -2 不存在 ; >1 超过1秒
    //     if ($check > 0) { //0.1进来的，进来后过期又重新设置，成永久key,ttl判断为-1
    //         $count = $this->_cache->incr($key);
    //         if ($count > $limit) {
    //             exit(json_encode(['code' => 10001, 'msg' => "您在{$time}秒内已经请求超过最大次数,请稍后重试"], JSON_UNESCAPED_UNICODE));
    //         }
    //     } else {
    //         $this->_cache->setex($key, $time, 1); //过期重新设置该值
    //     }
    // }

    public function index()
    {
        set_time_limit(0);   // 设置脚本最大执行时间 为0 永不过期
        ini_set('max_execution_time', '0');
        ini_set("memory_limit", "-1");
    }

    /**
     * 获取产品列表
     * @author qinlh
     * @since 2022-07-08
     */
    public function getProductList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $where = [];
        $where['state'] = 1;
        $order = 'a.id asc';
        $result = Product::getProductList($where, $page, $limit, $order);
        return $this->as_json($result);
    }
    
    /**
     * 开始投资
     * @author qinlh
     * @since 2022-07-08
     */
    public function startInvestNow(Request $request) {
        $address = $request->post('address', '', 'trim');
        $product_id = $request->post('product_id', 1, 'intval');
        $number = $request->post('number', '', 'trim');
        $type = $request->post('type', 1, 'intval');
        if($address == '' || $product_id <= 0 || $number <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = MyProduct::startInvestNow($address, $product_id, $number, $type);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取产品详情
     * @author qinlh
     * @since 2022-07-08
     */
    public function getProductDetail(Request $request) {
        $address = $request->request('address', '', 'trim');
        $product_id = $request->request('product_id', 1, 'intval');
        if($address == '' || $product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Product::getProductDetail($product_id, $address);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取我的产品列表
     * @author qinlh
     * @since 2022-07-08
     */
    public function getMyProductList(Request $request) {
        $address = $request->request('address', '', 'trim');
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $where = [];
        $order = 'a.id desc';
        if($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $userId = User::getUserAddress($address);
        $where['a.uid'] = $userId;
        $result = MyProduct::getMyProductList($where, $page, $limit, $order);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取最新的结余和份数
     * @author qinlh
     * @since 2022-07-09
     */
    public function getNewsBuyAmount(Request $request) {
        $product_id = $request->post('product_id', 1, 'intval');
        if($product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = MyProduct::getNewsBuyAmount($product_id);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 实时计算最新净值数值
     * @author qinlh
     * @since 2022-07-09
     */
    public function calcNewsNetWorth(Request $request) {
        $profit = $request->request('profit', '', 'trim');
        $address = $request->request('address', '', 'trim');
        $product_id = $request->post('product_id', 1, 'intval');
        if($profit == '' || $product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = MyProduct::calcNewsNetWorth($profit, $product_id);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 实时更新今日净值
     * @author qinlh
     * @since 2022-07-09
     */
    public function saveDayNetworth(Request $request) {
        $address = $request->post('address', '', 'trim');
        $profit = $request->post('profit', '', 'trim');
        $product_id = $request->post('product_id', 0, 'intval');
        if(!$address || $address == '' || $profit == '' || $product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        if(strtolower($address) !== strtolower(config('admin_address'))) {
            return $this->as_json('70001', 'no operating authority');
        }
        $result = DayNetworth::saveDayNetworth($profit, $product_id);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取我的产品每天的明细数据
     * @author qinlh
     * @since 2022-07-08
     */
    public function getMyProductDetailsList(Request $request) {
        $address = $request->request('address', '', 'trim');
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $product_id = $request->request('product_id', 0, 'intval');
        if($address == '' || $product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $userId = User::getUserAddress($address);
        $where['a.uid'] = $userId;
        $where['a.product_id'] = $product_id;
        $order = 'a.date desc';
        $result = ProductDetails::getMyProductDetailsList($where, $page, $limit, $order);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }
    
    /**
     * 获取产品年化收益
     * @author qinlh
     * @since 2022-07-11
     */
    public function getCountAnnualizedIncome(Request $request) {
        $product_id = $request->request('product_id', 0, 'intval');
        if($product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = DayNetworth::getCountAnnualizedIncome($product_id);
        return $this->as_json($result);
    }
}
