<?php
// +----------------------------------------------------------------------
// | 文件说明：充提清算系统 充提记录 市场 Model 暂时停用
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2022 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-06-13
// +----------------------------------------------------------------------
namespace app\api\model;

use think\Model;
use RequestService\RequestService;

class FillingRecord extends Base
{

    /**
     * 记录充提记录
     * @author qinlh
     * @since 2022-06-23
     */
    public static function setDepositWithdrawRecord($user_id=0, $address='', $amount=0, $type=0, $gsBalance=0, $csBalance=0, $hash='')
    {
        if ($user_id > 0 && $address !== '' && $amount > 0 && $type > 0) {
            if ($type == 2) { //如果是提取的话 校验 amount 必须 <= gs_balance+cs_balance
                if ((float)$amount > ((float)$gsBalance + (float)$csBalance)) {
                    return false;
                }
            }
            try {
                $insertData = [
                    'user_id' => $user_id,
                    'address' => $address,
                    'amount' => $amount,
                    'gs_balance' => $gsBalance,
                    'cs_balance' => $csBalance,
                    'type' => $type,
                    'hash' => $hash,
                    'time' => date('Y-m-d H:i:s'),
                    // 'status' => $type == 1 ? 2 : 1 //提取的话 状态为进行中 1 否则为执行成功
                    'status' => 1 //提取的话 状态为进行中 1 否则为执行成功
                ];
                $insertId = self::insertGetId($insertData);
                if ($insertId && $insertId > 0) {
                    if ($type == 1) { //存的话 直接通知更新余额
                        @User::saveNotifyStatus($address, 0);
                    }
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                // p($e);
                return false;
            }
        }
        return false;
    }

    /**
     * 获取用户余额
     * @author qinlh
     * @since 2022-06-23
     */
    public static function getUserBalance($user_id='')
    {
        $gsBalance = 0; //获取GS余额 大于0或者小于0
        $csBalance = 0;
        // if ($address !== '') {
        //     $rechargeNum = self::where(['address'=>$address, 'type' => 1])->sum('amount'); //充值余额
        //     $extractNum = self::where(['address'=>$address, 'type' => 2])->sum('amount');//提取余额
        //     $csBalance = $rechargeNum > 0 ? (float)$rechargeNum - (float)$extractNum : 0;
        // }
        $userInfo = User::getUserInfoOne($user_id);
        if ($userInfo && $userInfo['local_balance']) {
            $gsBalance = $userInfo['local_balance'];
        }
        // $api_url = "https://api.h2ofinance.pro/api/getGamesBalance.ashx";
        // $params = array('address' => $address);
        // $resultArray = RequestService::doCurlGetRequest($api_url, $params);
        // if ($resultArray && $resultArray['code']) {
        //     $gsBalance = (float)$resultArray['data'];
        // }
        // $csBalance = User::getUserContractBalance($address);
        return ['gsBalance'=>$gsBalance, 'csBalance'=>$csBalance];
    }

    /**
     * 获取用户是否有正在提取中的记录
     * @author qinlh
     * @since 2022-03-19
     */
    public static function getUserIsInWithdraw($address='')
    {
        if ($address !== '') {
            $res = self::where(['address' => $address, 'status' => 1])->find();
            if ($res || count((array)$res) > 0) {
                return $res;
            } else {
                return [];
            }
        }
        return [];
    }

    /**
     * 监听提取状态是否完成
     * @author qinlh
     * @since 2022-03-19
     */
    public static function getGameFillingWithdrawStatus($withdrawId=0)
    {
        if ($withdrawId > 0) {
            $res = self::where("id", $withdrawId)->field('id,status')->find();
            if ($res || count((array)$res) > 0) {
                if ($res['status'] == 2) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * 修改充提状态 已完成
     * @author qinlh
     * @since 2022-04-11
     */
    public static function setDepWithdrawStatus($address='', $deWithId=0, $status=0, $isGsGetBalance=true)
    {
        if ($deWithId > 0) {
            $res = self::where('id', $deWithId)->setField('status', $status);
            if (false !== $res) {
                if ($status == 2 && $isGsGetBalance) { //如果是非重提状态 且 允许通知NFT更新打赏余额
                    $rewardBalance = User::getUserContractBalance($address);
                    if ($rewardBalance) {
                        @User::resetUserRewardBalance($address, $rewardBalance);
                    }
                    //     $api_url = "https://api.h2ofinance.pro/api/notifyUpdateBalance.ashx";
                //     $params = array('address' => $address);
                //     $resultArray = RequestService::doCurlGetRequest($api_url, $params);
                //     if ($resultArray && $resultArray['code']) {
                //         return true;
                //     } else {
                //         return false;
                //     }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * 获取用户充提记录
     * @author qinlh
     * @since 2022-05-04
     */
    public static function getDepositWithdrawRecord($where, $page, $limit=20, $order='')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::where($where)->page($page, $limit)->order($order)->select()->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
}
