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
// | Date: 2022-09-05
// +----------------------------------------------------------------------
namespace app\admin\controller;

use app\answer\model\Product;
use app\answer\model\MyProduct;
use app\api\model\User;
use app\answer\model\DayNetworth;
use app\answer\model\ProductUserDetails;
use app\answer\model\ProductDetails;
use app\answer\model\ProductOrder;
use app\answer\model\FundMonitoring;
use think\Request;
use think\Db;
use think\Controller;
use ClassLibrary\ClFile;
use ClassLibrary\CLUpload;
use ClassLibrary\CLFfmpeg;
use lib\Filterstring;

class ProductController extends BaseController
{
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
        $profit = $request->post('profit', '', 'trim');
        $channel_fee = $request->post('channel_fee', '', 'trim');
        $management_fee = $request->post('management_fee', '', 'trim');
        $product_id = $request->post('product_id', 0, 'intval');
        if($profit == '' || $product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $address = "0x0000000000000000000000000000000000000000";
        $result = DayNetworth::saveDayNetworth($address, $profit, $product_id, $channel_fee, $management_fee);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取产品每天的净值数据
     * @author qinlh
     * @since 2022-07-13
     */
    public function getProductDetailsList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $product_id = $request->request('product_id', 0, 'intval');
        if($product_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $where['a.product_id'] = $product_id;
        $order = 'a.date desc';
        $result = ProductDetails::getProductDetailsList($where, $page, $limit, $order);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }
    
}
