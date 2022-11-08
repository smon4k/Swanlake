<?php
// +----------------------------------------------------------------------
// | 文件说明：H2O-算力币 市场 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-03-07
// +----------------------------------------------------------------------
namespace app\hashpower\model;

use think\Model;
use RequestService\RequestService;

class Hashpower extends Base
{
    /**
    * 获取算力币列表
    * @author qinlh
    * @since 2022-03-12
    */
    public static function getHashpowerData($where, $page, $limit, $order='id desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::alias('a')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->field("a.*")
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        $newArray = [];
        foreach ($lists as $key => $val) {
            $incomeArr = self::calcBtcIncome($val['cost_revenue']); //获取收益
            $newArray[$key]['id'] = $val['id'];
            $newArray[$key]['currency'] = $val['currency'];
            $newArray[$key]['name'] = $val['name'];
            $newArray[$key]['cost_revenue'] = $val['cost_revenue'];
            $daily_income = $incomeArr['dailyIncome']; //日收益
            $annualized_income = $incomeArr['annualizedIncome'] * 100;
            // p($annualized_income);
            //获取年化收益
            $newArray[$key]['daily_income'] = $daily_income;
            $newArray[$key]['annualized_income'] = $annualized_income;
            $newArray[$key]['networth'] = 0;
            $newArray[$key]['total_balance'] = 0;
            $newArray[$key]['yest_income'] = 0;
            $newArray[$key]['yest_income_rate'] = 0;
            // $newArray[$key]['initial_deposit'] = MyHashpower::getSumProductTotalInvest($val['id']); //获取初始入金 = sum(用户购买份额*当时的价格);
            $newArray[$key]['initial_deposit'] = 0;
        }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$newArray];
    }

    /**
     * 计算日收益
     * @author qinlh
     * @since 2022-11-05
     */
    public static function calcBtcIncome($cost_revenue=0) {
        $poolBtcData = self::getPoolBtc();
        if($poolBtcData && count((array)$poolBtcData) > 0) {
            //计算预估电费
            $estimateBill = 0.065 * 29.55 * 24 / 1000;
            //日支出 BTC数量
            // $dailyExpenses = $estimateBill / $poolBtcArr['currency_price'];
            $dailyIncome = ($poolBtcData['daily_income'] - $estimateBill) * 0.95; //日收益 = 收益-电费
            if($dailyIncome > 0) {
                $dailyIncomeNum = sprintfNum($dailyIncome, 3);
                $dailyIncome = sprintfNum($dailyIncomeNum + 0.001, 3);
            }
            $annualizedIncome = 0;
            if($cost_revenue > 0) {
                $annualizedIncome = $dailyIncome / $cost_revenue * 365; //年化收益 = 日收益 / 收益成本 * 365
            }
            // $dailyIncomeNum = $dailyIncome / $poolBtcData['currency_price'];
            return ['dailyIncome' => $dailyIncome, 'annualizedIncome' => $annualizedIncome];
        }
        return false;
    }

    /**
     * 获取BTC爬虫数据
     * @author qinlh
     * @since 2022-11-06
     */
    public static function getPoolBtc() {
        $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
        $dataJson = self::getCache($cache_params);
        $data = json_decode($dataJson, true);
        if($data && count((array)$data) > 0) {
            return $data;
        }
        $url = "https://www.h2ofinance.pro/getPoolBtc";
        $poolBtcArr = RequestService::doCurlGetRequest($url, []);
        if($poolBtcArr && count((array)$poolBtcArr) > 0) {
            $dataJson = json_encode($poolBtcArr[0]);
            self::setCache($cache_params, $dataJson);
            return $poolBtcArr[0];
        }
        return [];
    }

    /**
    * 获取详情数据
    * @author qinlh
    * @since 2022-03-12
    */
    public static function getHashpowerDetail($hashId = 0)
    {
        $detail = [];
        if ($hashId > 0) {
            $detail = self::where('id', $hashId)->find()->toArray();
            if (!$detail) {
                return false;
            }
            $end_time = date('Y-m-d');
            $start_time = $detail['online_time'];
            // $detail['online_days'] = self::diffBetweenTwoDays($start_time, $end_time);
            $detail['online_days'] = 0;
        }
        return $detail;
    }

    /**
     * 两个日期相差多少天
     * @author qinlh
     * @since 2022-03-13
     */
    public static function diffBetweenTwoDays($day1, $day2)
    {
        $second1 = strtotime($day1);
        $second2 = strtotime($day2);
        $days = 0;
        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
            $days =  round(($second1 - $second2) / 86400);
        } else {
            $days = 0;
        }
        return $days;
    }

    /**
     * 购买算力 减库存
     * @author qinlh
     * @since 2022-05-30
     */
    public static function buyHashPower($hashId=0, $address='', $amount=0, $hash='') {
        if ($hashId > 0 && $address !== '' && $amount > 0) {
            self::startTrans();
            try {
                $res = self::where('id', $hashId)->setDec('stock', $amount);
                if($res !== false) {
                    $setHashBalanceRes = MyHashpower::setHashBalance($hashId, $address, $amount);
                    if($setHashBalanceRes) {
                        $saveLogRes = HashpowerLog::setPurchaseLog($hashId, $address, $amount, $hash);
                        if($saveLogRes) {
                            self::commit();
                            return true;
                        }
                    }
                }
                self::rollback();
                return false;
            } catch (\Exception $e) {
                // 回滚事务
                self::rollback();
                return false;
            }
        }
        return false;
    }
}
