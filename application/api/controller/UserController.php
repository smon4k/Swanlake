<?php
// +----------------------------------------------------------------------
// | 文件说明：用户 api
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

use app\api\model\Notes;
use app\api\model\User;
use app\api\model\FillingRecord;
use app\api\model\MyProduct;
use think\Request;
use think\Cookie;
use think\Config;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\ClFile;
use ClassLibrary\ClString;
use ClassLibrary\ClHttp;
use ClassLibrary\ClImage;
use ClassLibrary\ClDate;
use ClassLibrary\CLAwsUpload;
use lib\Filterstring;
use think\Controller;
use RequestService\RequestService;

class UserController extends BaseController
{
    public function index()
    {
        echo "Hello User";
    }

    /**
     * 获取用户信息
     * @Author qinlh
     * @param Request $request
     * @return \think\response\Json
     */
    public function getUserInfo(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        if ($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $userInfo = User::getUserAddressInfo($address);
        if($userInfo) {
            // p($favorite_num);
            // if($userInfo && (float)$userInfo['wallet_balance'] <= 0) {
            if($userInfo || (float)$userInfo['wallet_balance'] <= 0) {
                $rewardBalance = User::getUserContractBalance($userInfo['address']);
                if ($rewardBalance) {
                    $userInfo['wallet_balance'] = $rewardBalance;
                    @User::resetUserRewardBalance($address, $rewardBalance);
                }
            }
            $userInfo['total_invest'] = 0;
            $userInfo['total_number'] = 0;
            $UserTotalInvest = MyProduct::getUserTotalInvest($userInfo['id']);
            if($UserTotalInvest) {
                $userInfo['total_invest'] = $UserTotalInvest['total_invest'];
                $userInfo['total_number'] = $UserTotalInvest['total_number'];
            }
            $cumulativeIncomeRes = MyProduct::getAllCumulativeIncome($userInfo['id']);
            $userInfo['cumulative_income'] = $cumulativeIncomeRes;
            $balanceData = MyProduct::getAllProductBalance($userInfo['id']);
            $userInfo['count_balance'] = $balanceData;
            return $this->as_json($userInfo);
        } else {
            return $this->as_json(70001, '添加失败');
        }
    }

    /**
     * 根据钱包地址获取用户信息
     * @author qinlh
     * @since 2022-05-29
     */
    public function getUserAddressInfo(Request $request) {
        $address = $request->request('address', '', 'trim');
        $invite_address = $request->request('invite_address', '', 'trim'); //邀请人地址
        if($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::getUserAddressInfo($address, $invite_address);
        if($result) {
            $result['is_admin'] = getAdminAddress($address);
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * [setUserLocalBalance] [修改用户本地余额 外部调用]
     * @param [$artId] [文章ID]
     * @return [type] [description]
     * @author [qinlh] [WeChat QinLinHui0706]
     */
    public function setUserLocalBalance(Request $request) {
        $address = $request->post('address', '', 'trim');
        $amount = $request->post('amount', '', 'trim');
        $type = $request->post('type', 0, 'intval');
        if ($amount <= 0 || $amount == '' || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::setUserLocalBalance($address, $amount, $type);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json('70001', 'Save Error');
        }
    }

    /**
     * 重置用户链上余额 外部调用
     * @author qinlh
     * @since 2022-06-15
     */
    public function resetUserRewardBalance(Request $request) {
        $address = $request->post('address', '', 'trim');
        $amount = $request->post('amount', '', 'trim');
        if ($amount <= 0 || $amount == '' || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::resetUserRewardBalance($address, $amount);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json('70001', 'Reset Error');
        }
    }

    /**
     * 获取我邀请的数据列表
     * @author qinlh
     * @since 2022-06-29
     */
    public function getiIinviteList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $userId = $request->request('userId', 0, 'intval');
        if ($userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $order = 'a.time desc';
        $where['a.fid'] = $userId;
        $result = UserLevel::getMyInvitationList($where, $page, $limit, $order);
        return $this->as_json($result);
    }
}
