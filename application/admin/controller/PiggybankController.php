<?php
/**
 * H2O-存钱罐功能
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
        }
        if($order_number && $order_number !== "") {
            $where['a.order_number'] = ['like',"%" . $order_number . "%"];
        }
        $data = Piggybank::getPiggybankOrderList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 获取每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getPiggybankOrderDateList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $product_name = $request->request('product_name', '', 'trim');
        $order_number = $request->request('order_number', '', 'trim');
        $where = [];
        $data = Piggybank::getPiggybankDateList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

}