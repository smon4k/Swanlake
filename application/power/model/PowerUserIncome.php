<?php
// +----------------------------------------------------------------------
// | 文件说明：用户收益
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-12-17
// +----------------------------------------------------------------------
namespace app\power\model;

use think\Model;

class PowerUserIncome extends Base {

     /**
     * 获取用户日收益列表
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getPowerDailyincomeList($where, $page, $limit, $order='a.time desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
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
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 写入领取记录 暂时作废
     * @param [$address 用户]
     * @param [$hash_id 产品ID]
     * @param [$amount 领取数量]
     * @param [$price 净值]
     * @param [$status 1：成功 0：失败]
     * @author qinlh
     * @since 2022-07-10
     */
    public static function asyncSetHashpowerDailyincome($data=[]) {
        if($data && count((array)$data) > 0) {
            // p($data);
            self::startTrans();
            try {
                $insertDataAll = [];
                $yesterDate = date("Y-m-d",strtotime("-1 day"));
                foreach ($data as $key => $val) {
                    $balance = $val['balance'];
                    $userFirstOneDay = HashpowerLog::getUserFirstOneDay($val['address'], $val['hash_id']);
                    // p($userFirstOneDay);
                    $time1 = strtotime($userFirstOneDay['time']);
                    $time2 = time();
                    $diff_seconds = $time2 - $time1;
                    $diff_diys = floor($diff_seconds/86400);
                    if($diff_diys > 0) {
                        $detail = Hashpower::getHashpowerOneDetail($val['hash_id']);
                        $incomeArr = Hashpower::calcBtcIncome($detail['electricity_price'], $detail['power_consumption_ratio'], $detail['cost_revenue']); 
                        if($detail && count((array)$detail) > 0) {
                            $yesterday_usdt = convert_scientific_number_to_normal($val['balance'] * $incomeArr['dailyIncome']); //昨日收益 USDT = 余额 * 日收益
                            $dailyIncomeBtc = convert_scientific_number_to_normal($incomeArr['dailyIncome'] / $incomeArr['currency_price']);
                            $yesterday_btc = convert_scientific_number_to_normal($val['balance'] * $dailyIncomeBtc); //昨日收益BTC = 余额 * （日收益 / 币价）
                            $insertDataAll[] = [
                                'address' => $val['address'],
                                'hash_id' => $val['hash_id'],
                                'income_usdt' => $yesterday_usdt,
                                'income_btc' => $yesterday_btc,
                                'date' => $yesterDate,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                        }
                    }
                }
                // p($insertDataAll);
                if($insertDataAll && count((array)$insertDataAll) > 0) {
                    $isDelRes = self::where('date', $yesterDate)->delete();
                    if($isDelRes !== false) {
                        $maxId = self::getMaxId();
                        $maxInsertId = (int)$maxId + 1;
                        self::query("ALTER TABLE hp_hashpower_dailyincome auto_increment = {$maxInsertId};");
                        // p($res);
                        self::insertAll($insertDataAll);
                        $insertCount = self::getLastInsID();
                        if($insertCount > 0) {
                            self::commit();
                            return true;
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
    * 获取最大的id值
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getMaxId() {
        return self::max("id");
    }

    /**
     * 写入用户收益数据明细
     * @author qinlh
     * @since 2022-12-18
     */
    public static function setPowerUserIncome($user_power_id=0, $hash_id=0, $address='', $amount='') {
        $date = date('Y-m-d');
        if($user_power_id && $hash_id && $address !== '') {
            $dataRes = self::where(['user_power_id' => $user_power_id, 'hash_id' => $hash_id, 'address' => $address, 'date' => $date])->find();
            if($dataRes && count((array)$dataRes) > 0) {
                $res = self::where(['user_power_id' => $user_power_id, 'hash_id' => $hash_id, 'address' => $address, 'date' => $date])->setField('amount', $amount);
                if(false !== $res) {
                    return true;
                }
            } else {
                $insertData = [
                    'user_power_id' => $user_power_id,
                    'address' => $address,
                    'hash_id' => $hash_id,
                    'amount' => $amount,
                    'date' => $date,
                    'time' => date('Y-m-d H:i:s'),
                ];
                self::insert($insertData);
                $insertId = self::getLastInsID();
                if($insertId) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取用户今日是否已发放奖励
     * @author qinlh
     * @since 2022-12-18
     */
    public static function getIsPowerUserIncome($user_power_id=0, $hash_id=0, $address='', $date='') {
        $dataRes = self::where(['user_power_id' => $user_power_id, 'hash_id' => $hash_id, 'address' => $address, 'date' => $date])->find();
        if($dataRes && count((array)$dataRes) > 0) {
            return true;
        }
        return false;
    }

    /**
     * 获取用户算力币总收益
     * @author qinlh
     * @since 2022-12-18
     */
    public static function getUserPowerCountIncome($user_power_id=0, $hash_id=0, $address='') {
        $amount = self::where(['user_power_id' => $user_power_id, 'hash_id' => $hash_id, 'address' => $address])->sum('amount');
        return $amount;
    }
}