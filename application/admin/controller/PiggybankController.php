<?php
/**
 * H2O-BTC-存钱罐功能
 * @copyright  Copyright (c) 2000-2020 QIN TEAM (http://www.qlh.com)
 * @version    GUN  General Public License 10.0.0
 * @license    Id:  Userinfo.php 2022-08-17 15:00:00
 * @author     Qinlh WeChat QinLinHui0706
 */
namespace app\admin\controller;
use think\Cookie;
use think\Request;
use lib\ClCrypt;
use ClassLibrary\ClFile;
use app\admin\model\Piggybank;
use app\tools\model\Okx;

class PiggybankController extends BaseController
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
        } else {
            $pigName = Okx::gettTradingPairName('Okx');
            $where['a.product_name'] = $pigName;
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
        } else {
            $pigName = Okx::gettTradingPairName('Okx');
            $where['a.product_name'] = $pigName;
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
        $where = [];
        $where['standard'] = $standard;
        $pigName = Okx::gettTradingPairName('Okx');
        $where['product_name'] = $pigName;
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
        if($direction <= 0 || $amount == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Piggybank::calcDepositAndWithdrawal($product_name, $direction, $amount, $remark);
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
        $where = [];
        $pigId = Okx::gettTradingPairId('Okx');
        $where['pig_id'] = $pigId;
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
    public function testBalancePosition() {
        $data = Okx::testBalancePosition();
        return $this->as_json($data);
    }

    /**
     * 获取交易对信息
     * @author qinlh
     * @since 2023-01-11
     */
    public function getTradingPairData() {
        $result = Okx::getTradingPairData('Okx');
        return $this->as_json($result);
    }

}