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
    public static function setDepositWithdrawRecord($address='', $amount=0, $type=0, $localBalance=0, $walletBalance=0, $hash='', $currency='usdt', $orderId='', $source=1)
    {
        if ($address !== '' && $amount > 0 && $type > 0) {
            if ($type == 2) { //如果是提取的话 校验 amount 必须 <= gs_balance+cs_balance
                if ((float)$amount > ((float)$localBalance + (float)$walletBalance)) {
                    return false;
                }
            }
            self::startTrans();
            try {
                $insertData = [
                    'address' => $address,
                    'amount' => $amount,
                    'local_balance' => $localBalance,
                    'wallet_balance' => $walletBalance,
                    'type' => $type,
                    'hash' => $hash,
                    'currency' => $currency,
                    'time' => date('Y-m-d H:i:s'),
                    // 'status' => $type == 1 ? 2 : 1 //提取的话 状态为进行中 1 否则为执行成功
                    'status' => 1, //提取的话 状态为进行中 1 否则为执行成功
                    'source' => $source, //来源：1: 天鹅湖 2：短视频 3：一站到底
                ];
                $insertId = self::insertGetId($insertData);
                if ($insertId && $insertId > 0) {
                    $isRes = User::saveNotifyStatus($address, 1, true);
                    if ($isRes) {
                        if ($type == 1) { //存的话 直接通知更新余额
                            // @User::saveNotifyStatus($address, 0);
                            $command = 'app\api\model\FillingRecord::asyncSetDepWithdrawStatus(' . "'" . $address . "'" . ',' . $insertId . ',' . "'" . $currency . "'" .');';
                            TaskContract::addTaskData($address, $hash, $command, '监听充值机器人执行状态');
                            self::commit();
                            return true;
                        } else {
                            //监听机器人任务是否执行成功
                            $command = 'app\api\model\FillingRecord::asyncFillingWithdrawStatus(' . $insertId .');';
                            TaskContract::addTaskData($address, $hash, $command, '监听提取机器人执行状态');
                            self::commit();
                            return true;
                        }
                    }
                }
                self::rollback();
                return false;
            } catch (\Exception $e) {
                p($e);
                self::rollback();
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
     * 异步任务监听提取机器人状态是否执行完成
     * @author qinlh
     * @since 2022-08-05
     */
    public static function asyncFillingWithdrawStatus($withdrawId=0)
    {
        if ($withdrawId > 0) {
            $res = self::where("id", $withdrawId)->field('id,address,status')->find();
            if ($res || count($res) > 0) {
                if ($res['status'] == 2) {
                    $isRes = User::saveNotifyStatus($res['address'], 0, true);
                    if($isRes) {
                        return ['code' => 1, 'message' => '提取机器人执行已完成'];
                    } else {
                        return ['code' => 0, 'message' => '同步获取用户余额失败'];
                    }
                } else {
                    return ['code' => 0, 'message' => '提取机器人执行中'];
                }
            } else {
                return ['code' => 0, 'message' => '获取提取数据失败'];
            }
        } else {
            return ['code' => 0, 'message' => '获取提取任务ID失败'];
        }
    }

    /**
     * 修改充提状态 已完成
     * @author qinlh
     * @since 2022-04-11
     */
    public static function setDepWithdrawStatus($address='', $deWithId=0, $status=0, $isGsGetBalance=true, $currency='usdt')
    {
        if ($deWithId > 0) {
            $res = self::where('id', $deWithId)->setField('status', $status);
            if (false !== $res) {
                $isRes = User::saveNotifyStatus($address, 0, true, $currency);
                if ($isRes) {
                    // if ($status == 2 && $isGsGetBalance) { //如果是非重提状态 且 允许通知NFT更新打赏余额
                    //     $rewardBalance = User::getUserContractBalance($address);
                    //     if ($rewardBalance) {
                    //         @User::resetUserRewardBalance($address, $rewardBalance);
                    //     }
                    // }
                    return true;
                }
                return false;
            }
        }
        return false;
    }

     /**
     * 异步监听执行
     * 修改充提状态 已完成
     * @author qinlh
     * @since 2022-04-11
     */
    public static function asyncSetDepWithdrawStatus($address='', $deWithId=0, $currency='usdt')
    {
        if ($deWithId > 0) {
            self::startTrans();
            try {
                $res = self::where('id', $deWithId)->find();
                if($res['status'] && $res['status'] == 2) {
                    self::commit();
                    return ['code' => 1, 'message' => '同步已执行成功'];
                }
                $saveRes = self::where('id', $deWithId)->setField('status', 2);
                if (false !== $saveRes) {
                    $isRes = User::saveNotifyStatus($address, 0, true);
                    if ($isRes) { 
                        $rewardBalance = User::getUserContractBalance($address, $currency);
                        if ($rewardBalance) {
                            @User::resetUserRewardBalance($address, $rewardBalance, $currency);
                            self::commit();
                            return ['code' => 1, 'message' => 'ok'];
                        }
                    } else {
                        self::rollback();
                        return ['code' => 0, 'message' => '修改用户充提状态失败'];
                    }
                } else {
                    self::rollback();
                    return ['code' => 0, 'message' => '修改充提状态失败'];
                }
            } catch (\Exception $e) {
                // 回滚事务
                self::rollback();
                $error_msg = json_encode([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ], JSON_UNESCAPED_UNICODE);
                return ['code' => 0, 'message' => $error_msg];
            }
        }
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

    /**
     * 获取自增ID
     * @author qinlh
     * @since 2022-09-10
     */
    public static function getIncreasingId() {
        $sql = "SELECT `AUTO_INCREMENT` FROM information_schema.TABLES WHERE `TABLE_NAME`='s_filling_record' LIMIT 1";
        $data = self::query($sql);
        if($data && count((array)$data) > 0) {
            return $data[0]['AUTO_INCREMENT'];
        }
        return 0;
    }
}
