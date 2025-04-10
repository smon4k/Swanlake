<?php
// +----------------------------------------------------------------------
// | 文件说明：网格管理
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-04-09
// +----------------------------------------------------------------------
namespace app\grid\controller;

use app\grid\model\Orders;
use app\grid\model\Config;
use think\Request;
use think\Controller;
use think\Db;
use think\Loader;
use RequestService\RequestService;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\ClFile;
use ClassLibrary\ClString;
use ClassLibrary\ClHttp;
use ClassLibrary\ClImage;

class GridController extends BaseController
{
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getOrderList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        // $product_name = $request->request('product_name', '', 'trim');
        $order_number = $request->request('order_number', '', 'trim');
        $where = [];
        // if($product_name && $product_name !== "") {
        //     $where['a.product_name'] = ['like',"%" . $product_name . "%"];
        // } 
        if($order_number && $order_number !== "") {
            $where['a.order_id'] = ['like',"%" . $order_number . "%"];
        }
        $data = Orders::getOrderList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        // p($lists);
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }


    /**
     * 获取机器人配置
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
     */
    public function getRobotConfig(Request $request) { 
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval'); 
        $where = [];
        $data = Config::getConfigList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        // p($lists);
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

     /**
     * 添加机器人配置
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
     */
    public function addRobotConfig(Request $request) {
        $multiple = $request->post('multiple', '', 'trim');
        $position_percent = $request->post('position_percent', '', 'trim');
        $max_position = $request->post('max_position', '', 'trim');
        $total_position = $request->post('total_position', '', 'trim');
        $stop_profit_loss = $request->post('stop_profit_loss', '', 'trim');
        $grid_step = $request->post('grid_step', '', 'trim');
        $grid_sell_percent = $request->post('grid_sell_percent', '', 'trim');
        $grid_buy_percent = $request->post('grid_buy_percent', '', 'trim');
        $commission_price_difference = $request->post('commission_price_difference', '', 'trim');

        // 验证参数是否为空
        if (!$multiple || !$position_percent || !$max_position || !$total_position || !$stop_profit_loss || !$grid_step || !$grid_sell_percent || !$grid_buy_percent || !$commission_price_difference) {
            return $this->as_json(['code' => 400, 'msg' => '参数错误']);
        }

        $data = [
            'multiple' => $multiple,
            'position_percent' => $position_percent,
            'max_position' => $max_position,
            'total_position' => $total_position,
            'stop_profit_loss' => $stop_profit_loss,
            'grid_step' => $grid_step,
            'grid_sell_percent' => $grid_sell_percent,
            'grid_buy_percent' => $grid_buy_percent, 
            'commission_price_difference' => $commission_price_difference,
            'is_active' => 1,
        ];
        $res = Config::addRobotConfig($data);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

     /**
     * 修改机器人配置
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
     */
    public function updateRobotConfig(Request $request) {
        $id = $request->post('id', '', 'trim');
        $multiple = $request->post('multiple', '', 'trim');
        $position_percent = $request->post('position_percent', '', 'trim');
        $max_position = $request->post('max_position', '', 'trim');
        $total_position = $request->post('total_position', '', 'trim');
        $stop_profit_loss = $request->post('stop_profit_loss', '', 'trim');
        $grid_step = $request->post('grid_step', '', 'trim');
        $grid_sell_percent = $request->post('grid_sell_percent', '', 'trim');
        $grid_buy_percent = $request->post('grid_buy_percent', '', 'trim'); 
        $commission_price_difference = $request->post('commission_price_difference', '', 'trim');

        // 验证参数是否为空
        if (!$id ||!$multiple ||!$position_percent ||!$max_position ||!$total_position ||!$stop_profit_loss ||!$grid_step ||!$grid_sell_percent ||!$grid_buy_percent ||!$commission_price_difference) {
            return $this->as_json(['code' => 400,'msg' => '参数错误']); 
        }
        $data = [
            'multiple' => $multiple,
            'position_percent' => $position_percent,
            'max_position' => $max_position,
            'total_position' => $total_position,
            'stop_profit_loss' => $stop_profit_loss,
            'grid_step' => $grid_step,
            'grid_sell_percent' => $grid_sell_percent,
            'grid_buy_percent' => $grid_buy_percent,
            'commission_price_difference' => $commission_price_difference,
            'updated_at' => time(), 
        ];
        $res = Config::updateRobotConfig($id, $data);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']); 
        } else {
            return $this->as_json(70001, $res['msg']); 
        }
    }
}