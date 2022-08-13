<?php
// +----------------------------------------------------------------------
// | 文件说明：充提功能 前台页面接口
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-07-11
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\api\model\User;
use app\api\model\FillingRecord;
use app\api\model\RewardList;
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

class DepositwithdrawalController extends BaseController
{
    /**
     * 获取用户充提信息
     * @Author qinlh
     * @param Request $request
     * @return \think\response\Json
     */
    public function getFillingRecordUserInfo(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        if ($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::getUserAddressInfo($address);
        if($result) {
            $isDeWithdrawRes = FillingRecord::getUserIsInWithdraw($address); //是否充提中的记录
            $result['isDeWith'] = false; //是否充提中的记录
            $result['isDeWithStatusId'] = 0; //正在充提中的状态id
            $result['isDeWithType'] = '';
            $result['isDeWithHash'] = '';
            // p($isDeWithdrawRes);
            if ($isDeWithdrawRes && count((array)$isDeWithdrawRes) > 0) {
                $result['isDeWith'] = true;
                $result['isDeWithStatusId'] = $isDeWithdrawRes['id'];
                $result['isDeWithType'] = $isDeWithdrawRes['type'];
                $result['isDeWithHash'] = $isDeWithdrawRes['hash'];
            }
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }
    

    /**
     * 开始充值中
     * 写入数据库状态 通知GS
     * @author qinlh
     * @since 2022-03-19
     */
    public function saveNotifyStatus(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        $status = $request->request('status', 0, 'intval');
        $isGsGetBalance = $request->request('type', 1, 'intval');
        $currency = $request->request('currency', 'usdt', 'trim');
        if (!$address || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::saveNotifyStatus($address, $status, $isGsGetBalance, $currency);
        return $this->as_json($result);
    }

    /**
     * 获取是否有交易正在进行中 有交易进行中不能进行充提操作
     * @author qinlh
     * @since 2022-03-19
     */
    public function getIsInTradeProgress(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        if (!$address || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::getRewardUserInfo($address);
        $isDeWithdrawRes = FillingRecord::getUserIsInWithdraw($address); //是否充提中的记录
        $returnData = [];
        $returnData['isDeWith'] = false;
        $returnData['status'] = 0;
        if ($result && $isDeWithdrawRes && count((array)$isDeWithdrawRes) > 0) {
            $returnData['status'] = $result['dw_status'];
            $returnData['isDeWith'] = true;
        }
        return $this->as_json($returnData);
    }

    /**
     * 修改充提状态 已完成
     * 这里暂时先充值执行 提取异步执行
     * @author qinlh
     * @since 2022-04-11
     */
    public function setDepWithdrawStatus(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        $status = $request->request('status', 0, 'intval');
        $deWithId = $request->request('deWithId', 0, 'intval');
        $isGsGetBalance = $request->request('type', 1, 'intval');
        $currency = $request->request('currency', 'usdt', 'trim');
        if (!$address || $address == '' || $status <= 0 || $deWithId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = FillingRecord::setDepWithdrawStatus($address, $deWithId, $status, $isGsGetBalance, $currency);
        return $this->as_json($result);
    }

    /**
     * 如果充提进行中 监听充提状态是否执行完成以通知GS更新余额
     * @author qinlh
     * @since 2022-03-19
     * @return
     */
    public function getGameFillingWithdrawStatus(Request $request)
    {
        $withdrawId = $request->request('withdrawId', 0, 'intval');
        if (!$withdrawId || $withdrawId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = FillingRecord::getGameFillingWithdrawStatus($withdrawId);
        return $this->as_json($result);
    }

    /**
     * 获取用户余额
     * @author qinlh
     * @since 2022-03-18
     */
    public function resetUserRewardBalance(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        if (!$address || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $rewardBalance = User::getUserContractBalance($address);
        if($rewardBalance) {
            $res = User::resetUserRewardBalance($address, $rewardBalance);
            if(false !== $res) {
                return $this->as_json('更新成功');
            } else {
                return $this->as_json(70001, '更新失败');
            }
        }
        return $this->as_json(70001, '更新失败');
        // p($result);
    }

    /**
    * 充值或者提现
    * @author qinlh
    * @since 2022-03-18
    */
    public function depositWithdraw(Request $request)
    {
        $amount = $request->post('amount', '', 'trim');
        $address = $request->post('address', '', 'trim');
        $localBalance = $request->post('local_balance', '', 'trim');
        $walletBalance = $request->post('wallet_balance', '', 'trim');
        $hash = $request->post('hash', '', 'trim');
        $type = $request->post('type', 0, 'intval');
        $currency = $request->post('currency', 'usdt', 'trim');
        if (!$address || $address == '' || $amount <= 0 || $type <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = FillingRecord::setDepositWithdrawRecord($address, $amount, $type, $localBalance, $walletBalance, $hash, $currency);
        return $this->as_json($result);
    }

    /**
     * 获取充提记录
     * @author qinlh
     * @since 2022-05-04
     */
    public function getDepositWithdrawRecord(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        $limit = $request->request('limit', 20, 'intval');
        $page = $request->request('page', 1, 'intval');
        if (!$address || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where['address'] = $address;
        $order = "time desc";
        $result = FillingRecord::getDepositWithdrawRecord($where, $page, $limit, $order);
        return $this->as_json($result);
    }

    /**
     * 外部获取充提记录
     * @author qinlh
     * @since 2022-05-08
     */
    public function exterDepositWithdrawRecord(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        $limit = $request->request('limit', 20, 'intval');
        $page = $request->request('page', 1, 'intval');
        $where = [];
        if($address && $address !== '') {
            $where['address'] = $address;
        }
        $order = "time desc";
        $result = FillingRecord::getDepositWithdrawRecord($where, $page, $limit, $order);
        return $this->as_json($result);
    }

    /**
     * 写入打赏记录
     * @author qinlh
     * @since 2022-04-24
     */
    public function setRewardList(Request $request)
    {
        $nft_id = $request->request('nft_id', 0, 'intval');
        $address = $request->request('address', '', 'trim');
        $amount = $request->request('amount', '', 'trim');
        if (!$address || $address == '' || $amount <= 0 || $nft_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $userInfo = User::getUserInfoOne($address);
        if (((float)$userInfo['balance'] + (float)$userInfo['local_balance']) < $amount) {
            return $this->as_json('70001', "The amount of reward cannot be greater than the user's balance");
        }
        $result = RewardList::setRewardList($nft_id, $address, $amount);
        if ($result) {
            return $this->as_json('ok');
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取NFT打赏列表
     * @author qinlh
     * @since 2022-04-25
     */
    public function getRewardList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 100000, 'intval');
        $nft_id = $request->request('nft_id', 0, 'intval');
        $address = $request->request('address', '', 'trim');
        if (!$address || $address == '' || $nft_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $where['nft_id'] = $nft_id;
        $result = RewardList::getRewardList($where, $page, $limit);
        return $this->as_json($result);
    }

    /**
     * 获取用户打赏列表
     * @author qinlh
     * @since 2022-04-25
     */
    public function getUserRewardList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $address = $request->request('address', '', 'trim');
        if (!$address || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        // $where['a.address'] = $address;
        $where['a.address|a.to_address'] = $address;
        $result = RewardList::getRewardList($where, $page, $limit, $address);
        return $this->as_json($result);
    }

    /**
     * 获取用户每天的奖励数据
     * @author qinlh
     * @since 2022-04-30
     */
    public function getUserAwardList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $address = $request->request('address', '', 'trim');
        if ($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        if ($address && $address !== '') {
            $where['a.address'] = $address;
        }
        $order = "date desc,id desc";
        $result = Award::getUserAwardList($where, $page, $limit, $order);
        return $this->as_json($result);
    }

    /**
     * 获取所有用户昨天的奖励数据
     * @author qinlh
     * @since 2022-04-30
     */
    public function getMiningRankingAwardList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 10000, 'intval');
        $where = [];
        // $date = date("Y-m-d", strtotime("-1 day"));
        // if ($date && $date !== '') {
        //     $where['date'] = $date;
        // }
        // $result = Award::getMiningRankingAwardList($where, $page, $limit);
        $order = "date desc,id desc";
        $result = Award::getUserAwardList($where, $page, $limit, $order);
        $newResult = [];
        $newSortResultArr = [];
        if ($result && count($result) > 0) {
            foreach ($result['lists'] as $key => $info) {
                // 根据age排序
                $newResult[$info['date']][] = $info;
            }
            foreach ($newResult as $key => $val) {
                // 根据count_award 从大到小排序
                $ages = array_column($val, 'count_award');
                array_multisort($ages, SORT_DESC, $val);
                $newSortResultArr[$key] = $val;
            }
        }
        // p($result);
        // p($newSortResultArr);
        return $this->as_json(['count'=>$result['count'],'allpage'=>$result['allpage'],'lists'=>$newSortResultArr]);
    }

    /**
     * 获取所有用户当天的奖励数据 前三
     * @author qinlh
     * @since 2022-04-30
     */
    public function getMiningRankingAwardTopThree(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 10000, 'intval');
        $where = [];
        // $date = date("Y-m-d", strtotime("-1 day"));
        // $date = date("Y-m-d");
        // if ($date && $date !== '') {
        //     $where['date'] = $date;
        // }
        // $result = Award::getMiningRankingAwardList($where, $page, $limit);
        $order = "date desc,id desc";
        $result = Award::getUserAwardList($where, $page, $limit, $order);
        $newResult = [];
        $newSortResultArr = [];
        $newArray = [];
        $ranking = 3; //获取前3
        if ($result && count($result) > 0) {
            foreach ($result['lists'] as $key => $info) {
                // 根据age排序
                // $newResult[$info['date']][] = $info;
                $newResult[$info['date']][$key]['address'] = $info['address'];
                $newResult[$info['date']][$key]['count_award'] = $info['count_award'];
                $newResult[$info['date']][$key]['nickname'] = $info['nickname'];
            }
            // p($newResult);
            foreach ($newResult as $key => $val) {
                // 根据count_award 从大到小排序
                $ages = array_column($val, 'count_award');
                array_multisort($ages, SORT_DESC, $val);
                $newSortResultArr[$key] = $val;
            }
            $arr = current($newSortResultArr); //获取第一个对象
            $newArray = array_slice($arr, 0, $ranking);
        }
        // p($newSortResultArr);
        // p($newArray);
        return $this->as_json($newArray);
    }

    /**
     * 免费给本地账户赠送50个H2O
     * @author qinlh
     * @since 2022-05-08
     */
    public function saveUserLocalBalance(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        if ($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::saveUserHandselH2O($address);
        return $this->as_json($result);
    }
}
