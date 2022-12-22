<?php
// +----------------------------------------------------------------------
// | 文件说明：短期算力币租赁 用户租赁 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-12-17
// +----------------------------------------------------------------------
namespace app\power\model;

use think\Model;
use app\api\model\User;
use RequestService\RequestService;

class PowerUser extends Base
{

    /**
     * 获取我的算力数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getUserPowerList($where, $page, $limit, $address='', $order='add_time desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        self::screeningUserPowerIsiIvalid($address); //筛查是否有大于有效期的数据
        $count = self::alias('a')->join('hp_power b', 'a.hash_id=b.id')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->field("a.*")
                    ->join('hp_power b', 'a.hash_id=b.id')
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->field('a.*,b.name')
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        foreach ($lists as $key => $val) {
            $lists[$key]['income'] = PowerUserIncome::getUserPowerCountIncome($val['id'], $val['hash_id'], $val['address']);
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 购买算力币
     * @author qinlh
     * @since 2022-12-17
     */
    public static function buyHashPower($hashId=0, $address='', $amount=0) {
        if($hashId && $address) {
            $powerDetail = Power::getPowerDetail($hashId);
            $buy_price = Power::getPowerPrice($hashId);
            $quota = $amount * $buy_price; //计算消耗额度 usdt
            self::startTrans();
            try {
                $res = self::where(['hash_id' => $hashId, 'address' => $address, 'state' => 1])->find();
                if($res && count((array)$res) > 0) {
                    $amount_data = (float)$amount + (float)$res['amount'];
                    $total_quota = (float)$res['total_quota'] + (float)$quota; //计算总额度
                    $res = self::where(['hash_id' => $hashId, 'address' => $address, 'state' => 1])->update(['amount' => $amount_data, 'total_quota' => $total_quota, 'update_time' => date('Y-m-d H:i:s')]);
                    if($res !== false) {
                        $setPowerOrderRes = PowerOrder::setPowerOrder($address, $hashId, $amount, $buy_price, $quota);
                        if($setPowerOrderRes) {
                            $setUserBalanceRes = User::setUserLocalBalance($address, $quota, 2);
                            if($setUserBalanceRes) {
                                $stockRes = Power::setPowerStock($hashId, $amount); //减库存
                                if($stockRes) {
                                    self::commit();
                                    return true;
                                }
                            }
                        }
                    }
                } else {
                    $insertData = [
                        'hash_id' => $hashId,
                        'address' => $address,
                        'amount' => $amount,
                        'total_quota' => $quota,
                        'add_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'state' => 1
                    ];
                    self::insert($insertData);
                    $insertId = self::getLastInsID();
                    if($insertId) {
                        $setPowerOrderRes = PowerOrder::setPowerOrder($address, $hashId, $amount, $buy_price, $quota);
                        if($setPowerOrderRes) {
                            $setUserBalanceRes = User::setUserLocalBalance($address, $quota, 2);
                            if($setUserBalanceRes) {
                                $stockRes = Power::setPowerStock($hashId, $amount); //减库存
                                if($stockRes) {
                                    self::commit();
                                    return true;
                                }
                            }
                        }
                    }
                }
                self::rollback();
                return false;
            } catch ( PDOException $e) {
                self::rollback();
                return false;
            }
        }
    }

    /**
     * 筛查用户租赁算力币是否过期 修改状态
     * @author qinlh
     * @since 2022-12-18
     */
    public static function screeningUserPowerIsiIvalid($address='', $hashId=0) {
        $where = [];
        $where['a.state'] = 1;
        if($address && $address !== '') {
            $where['a.address'] = $address;
        }
        if($hashId) {
            $where['a.hash_id'] = $hashId;
        }
        $list = self::alias('a')->join('hp_power b', 'a.hash_id=b.id')->where($where)->field('a.*, b.validity_period')->select()->toArray();
        if($list && count((array)$list) > 0) {
            foreach ($list as $key => $val) {
                $isIvalidRes = self::rangeDay($val['add_time'], $val['validity_period'] + 1);
                if($isIvalidRes) { //过期
                    $h = date('H');
                    if($h > 12) { //如果过了发放奖励时间的话
                        $saveRes = self::where('id', $val['id'])->setField('state', 2);
                        if($saveRes !== false) {
                            return true;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * 时间对比
     * $startTime  开始日期
     * $dayNumber  天数
     */
    public static function rangeDay($startTime, $dayNumber){
        if(((time() - strtotime($startTime)) / 86400) >= $dayNumber) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 发放收益
     * @author qinlh
     * @since 2022-12-18
     */
    public static function startSendingIncome() {
        self::startTrans();
        try {
            $sql = 'SELECT * FROM hp_power_user WHERE now() > SUBDATE( add_time, INTERVAL - 24 HOUR ) AND `state` = 1;';
            $list = self::query($sql);
            // p($list);
            if($list && count((array)$list) > 0) {
                $countRum = 0;
                $date = date('Y-m-d');
                foreach ($list as $key => $val) {
                    //检查是否超过有效天数
                    $powerDetail = Power::getPowerDetail($val['hash_id']);
                    $validity_period = $powerDetail['validity_period'] + 2; //算力币有效天数
                    $isFail = self::rangeDay($val['add_time'], $validity_period);
                    if($isFail) {
                        $res = self::where('id', $val['id'])->setField('state', 2);
                    } else {
                        //开始发放收益
                        //获取今日是否已发放收益
                        $dayIsIncome = PowerUserIncome::getIsPowerUserIncome($val['id'], $val['hash_id'], $val['address'], $date);
                        if(!$dayIsIncome) {
                            $incomeArr = self::calcBtcIncome($powerDetail['electricity_price'], $powerDetail['power_consumption_ratio'], $powerDetail['cost_revenue']); 
                            $dailyIncome = $incomeArr['dailyIncome']; //日收益
                            $userRewardNum = convert_scientific_number_to_normal($val['amount'] * $dailyIncome); //用户今日收益数数量
                            $setPowerIncome = PowerUserIncome::setPowerUserIncome($val['id'], $val['hash_id'], $val['address'], $userRewardNum);
                            if($setPowerIncome) { //如果写入奖励数据 
                                $res = User::setUserLocalBalance($val['address'], $userRewardNum, 1); //开始增加用户余额
                            }
                        } else {
                            $res = true;
                        }
                        //获取已购买天数
                        $dayer = (time() - strtotime($val['add_time'])) / 86400;
                        if($dayer >= $powerDetail['validity_period'] + 1) { //如果是最后一天发收益 修改订单状态已失效
                            self::where('id', $val['id'])->setField('state', 2);
                        }
                    }
                    if($res !== false) {
                        $countRum ++;
                    }
                }
                if($countRum == count((array)$list)) {
                    self::commit();
                    return true;
                }
            }
            self::rollback();
            return false;
        } catch ( PDOException $e) {
            self::rollback();
            return false;
        }
    }

    /**
     * 计算日收益
     * @param [electricity_price 电价]
     * @param [power_consumption_ratio 功耗比]
     * @param [cost_revenue 收益成本]
     * @author qinlh
     * @since 2022-11-05
     */
    public static function calcBtcIncome($electricity_price=0, $power_consumption_ratio=0, $cost_revenue=0) {
        $poolBtcData = Power::getPoolBtc();
        if($poolBtcData && count((array)$poolBtcData) > 0) {
            //计算预估电费 -> 日支出
            $estimateBill = $electricity_price * $power_consumption_ratio * 24 / 1000;
            //日支出 BTC数量
            // $dailyExpenses = $estimateBill / $poolBtcArr['currency_price'];
            $dailyIncome = ($poolBtcData['daily_income'] - $estimateBill) * 0.95; //日收益 = 收益-电费
            if($dailyIncome > 0) {
                $dailyIncomeNum = sprintfNum($dailyIncome, 3);
                $dailyIncome = sprintfNum($dailyIncomeNum + 0.001, 3);
            }
            $annualizedIncome = 0;
            if($cost_revenue > 0) {
                $annualizedIncome = $dailyIncome / $cost_revenue * 365 * 100; //年化收益 = 日收益 / 收益成本 * 365
            }
            // $dailyIncomeNum = $dailyIncome / $poolBtcData['currency_price'];
            return ['currency_price' => $poolBtcData['currency_price'], 'dailyIncome' => $dailyIncome, 'annualizedIncome' => $annualizedIncome, 'estimateBill' => $estimateBill];
        }
        return false;
    }

    /**
     * 获取总的本金额度
     * @author qinlh
     * @since 2022-12-22
     */
    public static function getTotalQuotaNum() {
        $num = self::where('state', 1)->sum('total_quota');
        return $num;
    }

}