<?php
// +----------------------------------------------------------------------
// | 文件说明：H2O-算力币 前台页面接口
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-03-07
// +----------------------------------------------------------------------
namespace app\hashpower\controller;

use app\hashpower\model\Hashpower;
use app\hashpower\model\HashpowerLog;
use app\hashpower\model\Output;
use app\hashpower\model\MyHashpower;
use app\api\model\User;
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

class HashpowerController extends BaseController
{

    /**
     * 获取产品列表
     * @author qinlh
     * @since 2022-11-05
     */
    public function getHashpowerData(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $where = [];
        $where['a.status'] = 1;
        $order = 'a.id asc';
        $result = Hashpower::getHashpowerData($where, $page, $limit, $order);
        return $this->as_json($result);
    }

    /**
     * 获取算力币操作日志数据
     * @author qinlh
     * @since 2021-11-26
     */
    public function getHashPowerList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $address = $request->request('address', '', 'trim');
        $where = [];
        if ($address && $address !== '') {
            $where['address'] = $address;
        }
        // $order = '';
        $result = HashpowerLog::getHashPowerLogList($where, $page, $limit);
        // p($result);
        return $this->as_json($result);
    }

    /**
     * 记录认购地址
     * @author qinlh
     * @since 2022-03-12
     */
    public function setPurchaseLog(Request $request)
    {
        $hashId = $request->request('hashId', 0, 'intval');
        $amount = $request->request('amount', 0, 'intval');
        $address = $request->request('address', '', 'trim');
        $hash = $request->request('hash', '', 'trim');
        if (!$address || $address == '' || $amount <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Hashpower::buyHashPower($hashId, $address, $amount, $hash);
        if ($result) {
            return $this->as_json('Success');
        } else {
            return $this->as_json('70001', 'Error');
        }
    }

    /**
    * 获取指定id详情数据
    * @author qinlh
    * @since 2021-11-26
    */
    public function getHashpowerDetail(Request $request)
    {
        $hashId = $request->request('hashId', 0, 'intval');
        if ($hashId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Hashpower::getHashpowerDetail($hashId);
        return $this->as_json($result);
    }

    /**
     * 获取产出数据以及上下天数
     * @author qinlh
     * @since 2022-06-05
     */
    public function getHashpowerOutput(Request $request) {
        $data = Output::getOutputYesterday();
        $hashpowerDetail = Hashpower::getHashpowerDetail(1);
        // p($data);
        $onlineDays = floor((strtotime(date('Y-m-d H:i:s')) - strtotime($hashpowerDetail['online_time']))/86400);
        $result = [
            'yester_output' => $data['yester_output'],
            'to_output' => $data['to_output'],
            'count_output' => $data['count_output'],
            'online_days' => $onlineDays,
            'cost_revenue' => (float)$hashpowerDetail['cost_revenue'],
        ];
        return $this->as_json($result);
    }

    /**
     * 开始质押
     * @author qinlh
     * @since 2022-07-08
     */
    public function startInvestNow(Request $request) {
        $address = $request->post('address', '', 'trim');
        $hashId = $request->post('hashId', 1, 'intval');
        $number = $request->post('number', '', 'trim');
        $type = $request->post('type', 1, 'intval');
        if($address == '' || $hashId <= 0 || $number <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = MyHashpower::startInvestNow($address, $hashId, $number, $type);
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
    public function getMyHashpowerList(Request $request) {
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
        $result = MyHashpower::getMyHashpowerList($where, $page, $limit, $order, $userId);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }
}
