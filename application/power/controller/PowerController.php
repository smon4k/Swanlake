<?php
// +----------------------------------------------------------------------
// | 文件说明：短期算力币租赁 前台页面接口
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-12-17
// +----------------------------------------------------------------------
namespace app\power\controller;

use app\power\model\Power;
use app\power\model\PowerUser;
use app\power\model\HashpowerLog;
use app\power\model\Output;
use app\power\model\HashpowerHarvest;
use app\power\model\PowerUserIncome;
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

class PowerController extends BaseController
{

    /**
     * 获取产品列表
     * @author qinlh
     * @since 2022-11-05
     */
    public function getPowerList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $where = [];
        $where['a.status'] = 1;
        $order = 'a.id asc';
        $result = Power::getPowerList($where, $page, $limit, $order);
        return $this->as_json($result);
    }


    /**
    * 获取指定id详情数据
    * @author qinlh
    * @since 2021-11-26
    */
    public function getPowerDetail(Request $request)
    {
        $hashId = $request->request('hashId', 0, 'intval');
        $address = $request->request('address', '', 'trim');
        if ($hashId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Power::getPowerDetail($hashId, $address);
        return $this->as_json($result);
    }


    /**
     * 获取我的算力币数据
     * @author qinlh
     * @since 2021-11-26
     */
    public function getUserPowerList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $address = $request->request('address', '', 'trim');
        if (!$address || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        if ($address && $address !== '') {
            $where['a.address'] = $address;
        }
        // $order = '';
        $result = PowerUser::getUserPowerList($where, $page, $limit, $address);
        // p($result);
        return $this->as_json($result);
    }

    /**
     * 购买算力币
     * @author qinlh
     * @since 2022-03-12
     */
    public function buyHashPower(Request $request)
    {
        $hashId = $request->post('hashId', 0, 'intval');
        $amount = $request->post('amount', 0, 'trim');
        $address = $request->post('address', '', 'trim');
        $recomme_code = $request->post('recomme_code', '', 'trim');
        if (!$address || $address == '' || $amount <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = PowerUser::buyHashPower($hashId, $address, $amount);
        if ($result) {
            return $this->as_json('Success');
        } else {
            return $this->as_json('70001', 'Error');
        }
    }

    /**
    * 获取指定用户算力收益数据
    * @author qinlh
    * @since 2021-11-26
    */
    public function getPowerDailyincomeList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $hashId = $request->request('hash_id', 0, 'intval');
        $address = $request->request('address', '', 'trim');
        if ($hashId <= 0 || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $where['a.address'] = $address;
        $where['a.hash_id'] = $hashId;
        $result = PowerUserIncome::getPowerDailyincomeList($where, $page, $limit);
        if ($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json('70001', 'Error');
        }
    }

    /**
     * 获取产出数据以及上下天数
     * @author qinlh
     * @since 2022-06-05
     */
    public function getHashpowerOutput(Request $request) {
        $data = Output::getOutputYesterday();
        $hashpowerDetail = Hashpower::getHashpowerDetail(1);
        $dailyExpenditureIncome = Hashpower::calcDailyExpenditureIncome(); //获取总的日收益 产出
        // p($data);
        $onlineDays = floor((strtotime(date('Y-m-d H:i:s')) - strtotime($hashpowerDetail['online_time']))/86400);
        $result = [
            'yester_output' => $data['yester_output'],
            'to_output' => $data['to_output'],
            'count_output' => $data['count_output'],
            'count_btc_output' => $data['count_btc_output'],
            'online_days' => $onlineDays,
            'daily_expenditure_usdt' => $dailyExpenditureIncome['daily_expenditure_usdt'], 
            'daily_expenditure_btc' => $dailyExpenditureIncome['daily_expenditure_btc'], 
            'daily_income_usdt' => $dailyExpenditureIncome['daily_income_usdt'],
            'daily_income_btc' => $dailyExpenditureIncome['daily_income_btc'],
        ];
        return $this->as_json($result);
    }

    public function test() {
        p($_SERVER['HTTP_USER_AGENT']);
        PowerUser::startSendingIncome();
    }
}
