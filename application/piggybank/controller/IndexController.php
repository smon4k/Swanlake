<?php
/**
 * 存钱罐功能
 * @copyright  Copyright (c) 2000-2025 QIN TEAM (http://www.qlh.com)
 * @version    GUN  General Public License 10.0.0
 * @license    Id:  Userinfo.php 2025-06-23 09:00:00
 * @author     Qinlh WeChat QinLinHui0706
 */
namespace app\piggybank\controller;
use think\Cookie;
use think\Request;
use lib\ClCrypt;
use ClassLibrary\ClFile;
use app\piggybank\model\Piggybank;
use app\tools\model\Okx;

class IndexController extends BaseController
{
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getPiggybankOrderList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $product_name = $request->request('product_name', '', 'trim');
        $order_number = $request->request('order_number', '', 'trim');
        $where = [];
        if($product_name && $product_name !== "") {
            $where['a.product_name'] = ['like',"%" . $product_name . "%"];
        } 
        if($order_number && $order_number !== "") {
            $where['a.order_number'] = ['like',"%" . $order_number . "%"];
        }
        $data = Piggybank::getPiggybankOrderList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        // p($lists);
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 获取存钱罐每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getPiggybankOrderDateList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $product_name = $request->request('product_name', '', 'trim');
        $order_number = $request->request('order_number', '', 'trim');
        // $standard = $request->request('standard', 0, 'intval');
        $where = [];
        if($product_name && $product_name !== "") {
            $where['a.product_name'] = ['like',"%" . $product_name . "%"];
        }
        // $where['standard'] = $standard;
        $data = Piggybank::getPiggybankDateList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 获取存钱罐每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getUBPiggybankOrderDateList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $product_name = $request->request('product_name', '', 'trim');
        $order_number = $request->request('order_number', '', 'trim');
        $standard = $request->request('standard', 0, 'intval');
        $currency_id = $request->request('currency_id', 0, 'intval');
        $where = [];
        $where['standard'] = $standard;
       if($product_name && $product_name !== "") {
            $where['a.product_name'] = ['like',"%" . $product_name . "%"];
        }
        $data = Piggybank::getUBPiggybankDateList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }
    
    /**
     * 出入金计算
     * @author qinlh
     * @since 2022-08-20
     */
    public function calcDepositAndWithdrawal(Request $request) {
        $product_name = $request->request('product_name', '', '');
        $direction = $request->request('direction', 0, 'intval');
        $amount = $request->request('amount', '', 'trim');
        $remark = $request->request('remark', '', '');
        $currency_id = $request->request('currency_id', 0, 'intval');
        if($currency_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        if($direction <= 0 || $amount == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Piggybank::calcDepositAndWithdrawal($product_name, $direction, $amount, $remark, $currency_id);
        if($result) {
            return $this->as_json('ok');
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
    * 获取出入金列表数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getInoutGoldList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $currency_id = $request->request('currency_id', 0, 'intval');
        $where = [];
        $where['pig_id'] = $currency_id;
        $data = Piggybank::getInoutGoldList($where, $page, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
     * 获取项目详情数据
     * @author qinlh
     * @since 2022-11-24
     */
    public function testBalancePosition(Request $request) {
        $currency_id = $request->request('currency_id', 0, 'intval');
        $data = Piggybank::testBalancePosition($currency_id);
        return $this->as_json($data);
    }

    /**
     * 获取存钱罐币种列表数据
     * @author qinlh
     * @since 2025-06-23
     */
    public function getCurrencyList() {
        $where = [];
        $where['state'] = 1;
        $result = Piggybank::getCurrencyList($where);
        return $this->as_json($result);
    }

    /**
     * 获取交易对名称
     * @author qinlh
     * @since 2025-06-23
     */
    public function getTradingPairData(Request $request) {
        $currency_id = $request->request('currency_id', 0, 'intval');
        $result = Piggybank::getTradingPairData($currency_id);
        return $this->as_json($result);
    }
    
}