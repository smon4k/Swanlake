<?php
// +----------------------------------------------------------------------
// | 文件说明：短期算力币租赁 市场 Model
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
use RequestService\RequestService;

class Power extends Base
{

    /**
     * 获取算力币列表
     * @author qinlh
     * @since 2022-12-17
     */
    public static function getPowerList($where, $page, $limit, $order='id desc')
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
        foreach ($lists as $key => $val) {
            $lists[$key]['price'] = self::getPowerPrice($val['id']);
        }
        return ['count'=>$count, 'allpage'=>$allpage, 'lists'=>$lists];
    }

    /**
     * 购买算力 减库存
     * @author qinlh
     * @since 2022-05-30
     */
    public static function setPowerStock($hashId=0, $amount=0) {
        if ($hashId > 0 && $amount > 0) {
            $res = self::where('id', $hashId)->setDec('stock', $amount);
            if($res !== false) {
                return true;
            }
        }
        return false;
    }




    /**
     * 获取所有算力币列表
     * @author qinlh
     * @since 2022-11-11
     */
    public static function getHashpowerLists() {
        $lists = self::where('status', 1)->select()->toArray();
        if($lists && count((array)$lists) > 0) {
            return $lists;
        }
    }

    /**
    * 获取详情数据
    * @author qinlh
    * @since 2022-03-12
    */
    public static function getPowerDetail($hashId = 0)
    {
        $lists = [];
        if ($hashId > 0) {
            $data = self::where('id', $hashId)->find();
            if (!$data) {
                return false;
            }
            $dataDetail = $data->toArray();
            $dataDetail['price'] = self::getPowerPrice($hashId);
            // p($dataDetail);
        }
        // p($detail);
        return $dataDetail;
    }

    /**
     * 获取算力币价格
     * @author qinlh
     * @since 2022-12-18
     */
    public static function getPowerPrice($hashId) {
        //获取长期算力币数据详情
        $powerDailyIncomeArr = self::getHashPowerDailyIncome($hashId);
        $daily_income_usdt = isset($powerDailyIncomeArr['daily_income_usdt']) ? $powerDailyIncomeArr['daily_income_usdt'] : 0; //净收入
        $poolBtcData = self::getPoolBtc();
        $next_difficulty = $poolBtcData['next_difficulty']; //预测下次难度
        $price = 0.99 * (2 * $daily_income_usdt + 5 * $daily_income_usdt) * (1 - $next_difficulty);
        if($price) {
            return $price;
        }
        return 0;
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
     * 计算日收益
     * @param [electricity_price 电价]
     * @param [power_consumption_ratio 功耗比]
     * @param [cost_revenue 收益成本]
     * @author qinlh
     * @since 2022-11-05
     */
    public static function calcBtcIncome($electricity_price=0, $power_consumption_ratio=0, $cost_revenue=0) {
        $poolBtcData = self::getPoolBtc();
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
        $url = config('h2o_finance_url')."/getPoolBtc";
        $poolBtcArr = RequestService::doCurlGetRequest($url, []);
        if($poolBtcArr && count((array)$poolBtcArr) > 0) {
            $dataJson = json_encode($poolBtcArr[0]);
            self::setCache($cache_params, $dataJson);
            return $poolBtcArr[0];
        }
        // return [];
    }

    /**
     * 获取长期算力币数据
     * 获取长期算力币日收益 当前净收入
     * @author qinlh
     * @since 2022-12-18
     */
    public static function getHashPowerDailyIncome($hashId=0) {
        if($hashId) {
            $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
            $dataJson = self::getCache($cache_params);
            $data = json_decode($dataJson, true);
            if($data && count((array)$data) > 0) {
                return $data;
            }
            $url = config('h2o_api_url'). '/Hashpower/Hashpower/getHashpowerDetail?hashId='.$hashId;
            $poolBtcArr = RequestService::doCurlGetRequest($url, []);
            // p($poolBtcArr);
            if($poolBtcArr && count((array)$poolBtcArr) > 0) {
                $dataJson = json_encode($poolBtcArr['data']);
                self::setCache($cache_params, $dataJson);
                return $poolBtcArr['data'];
            }
            return [];
        }
    }


    /**
     * 开始质押
     * @param [$address 钱包地址]
     * @param [$hash_id 算力币id]
     * @param [$number 份额]
     * @param [$hash 交易hash值]
     * @param [$reward_amount 自动收获收益数量]
     * @param [$type 1：投注 2：赎回]
     * @author qinlh
     * @since 2022-07-08
     */
    public static function startInvestNow($address='', $hash_id=0, $number=0, $type=0, $hash='', $reward_amount=0) {
        if($address !== '' || $hash_id > 0 || $number > 0 && $type > 0) {
            self::startTrans();
            try {
                $poolBtcData = self::getPoolBtc();
                $price = $poolBtcData['currency_price']; 
                $buy_number = 0; //usdt 数量
                $buy_number = (float)$price > 0 ? (float)$number * (float)$price : (float)$number; //计算购买数量
                $orderLog = HashpowerOrder::setHashpowerOrder($address, $hash_id, $buy_number, $number, $price, $type, $hash);
                if($orderLog) {
                    if($reward_amount && $reward_amount !== '') { //如果质押操作前有奖励未领取 自动领取
                        $hashDetail = self::getHashpowerOneDetail($hash_id);
                        if($hashDetail) {
                            $currency = $hashDetail['currency'];
                        } else {
                            $currency = "BTCB";
                        }
                        $autoSetHarvestRes = HashpowerHarvest::setHashpowerHarvest($address, $hash_id, $reward_amount, $hash, $currency);
                        if($autoSetHarvestRes) {
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
        return false;
    }

    /**
     * 计算总的日支出 日收益
     * @author qinlh
     * @since 2022-11-11
     */
    public static function calcDailyExpenditureIncome($hash_id=0) {
        $where = [];
        $where['status'] = 1;
        if($hash_id) {
            $where['id'] = $hash_id;
        }
        $lists = self::where($where)->select()->toArray();
        $daily_expenditure_usdt = 0; //日支出 usdt
        $daily_expenditure_btc = 0; //日支出 btc
        $daily_income_usdt = 0; //日收益 usdt
        $daily_income_btc = 0; //日收益 btc
        foreach ($lists as $key => $val) {
            $incomeArr = self::calcBtcIncome($val['electricity_price'], $val['power_consumption_ratio'], $val['cost_revenue']); 
            // p($incomeArr);
            $daily_expenditure = (float)$incomeArr['estimateBill']; //日支出 usdt
            $daily_expenditure_usdt += (float)$daily_expenditure;
            // p(convert_scientific_number_to_normal($num));
            $daily_expenditure_btc += (float)$daily_expenditure / (float)$incomeArr['currency_price'];
            $daily_income_usdt += (float)$incomeArr['dailyIncome']; //日收益 usdt
            $daily_income_btc += (float)$incomeArr['dailyIncome'] / (float)$incomeArr['currency_price'];; //日收益 btc
        }
        return ['daily_expenditure_usdt' => $daily_expenditure_usdt, 'daily_expenditure_btc' => convert_scientific_number_to_normal($daily_expenditure_btc), 'daily_income_usdt' => $daily_income_usdt, 'daily_income_btc' => convert_scientific_number_to_normal($daily_income_btc)];
    }


}
