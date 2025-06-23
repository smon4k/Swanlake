<?php

// +----------------------------------------------------------------------
// | 文件说明：BTC-存钱罐-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-06-23
// +----------------------------------------------------------------------

namespace app\piggybank\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;
use RequestService\RequestService;

class Piggybank extends Base
{
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankOrderList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        $lists = self::name("piggybank")
                    ->alias("a")
                    ->where($where)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        $newArrayData = [];
        foreach ($lists as $key => $val) {
            if ($val['pair'] > 0) {
                $newArrayData[$val['pair']][] = $val;
            } else {
                $newArrayData[$val['id']][0] = $val;
                $newArrayData[$val['id']][1] = [];
                $newArrayData[$val['id']][1]['type_str'] = $val['type'] == 1 ? '等待卖出' : '等待买入';
            }
        }
        // p($newArrayData);
        $resultArray = [];
        foreach ($newArrayData as $key => $val) {
            $resultArray[$key]['time'] = $val[0]['time'];
            $price = 0;
            $profit = $val[0]['profit'];
            if (isset($val[1]) && count((array)$val[1]) > 1) {
                $price = abs($val[0]['price'] -  $val[1]['price']);
            } else {
                $price = $val[0]['price'];
            }
            $resultArray[$key]['price'] = $price;
            $resultArray[$key]['profit'] = $profit;
            $resultArray[$key]['lists'] = $val;
        }
        // p($resultArray);
        $newResultArray = array_values($resultArray);
        // p($newResultArray);
        //总数
        $total = count($newResultArray);
        $allpage = intval(ceil($total / $limits));
        //分页
        $start = ($page - 1) * $limits;
        $returnArr = array_slice($newResultArray, $start, $limits);
        // p($returnArr);
        return ['count'=>$total,'allpage'=>$allpage,'lists'=>$returnArr];
    }

    /**
    * 获取存钱罐每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankDateList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 获取U币本位统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getUBPiggybankDateList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("piggybank_currency_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("piggybank_currency_date")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 出入金计算
     * @author qinlh
     * @since 2022-08-20
     */
    public static function calcDepositAndWithdrawal($product_name='', $direction=0, $amount=0, $remark='')
    {
        $date = date('Y-m-d');
        //总结余
        $balanceDetails = self::getTradeValuation($product_name);
        $btcPrice = $balanceDetails['btcPrice'];
        $countUstandardPrincipal = 0;
        $countBstandardPrincipal = 0;
        $uPrincipalRes = self::getPiggybankCurrencyPrincipal(1); //获取昨天的U数据
        $bPrincipalRes = self::getPiggybankCurrencyPrincipal(2); //获取昨天的B数据
        $URes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
        $BRes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
        $total_balance = self::getInoutGoldTotalBalance(); //出入金总结余
        if (!$amount || $amount == 0) {
            // $countUstandardPrincipal = isset($uPrincipalRes['principal']) ? $uPrincipalRes['principal'] : 0;
            // $countBstandardPrincipal = isset($bPrincipalRes['principal']) ? $bPrincipalRes['principal'] : 0;
            if(!$URes || empty($URes)) { //今日第一次执行 获取昨日本金
                if(isset($uPrincipalRes['principal']) && $uPrincipalRes['principal'] > 0) {
                    $countUstandardPrincipal = isset($uPrincipalRes['principal']) ? (float)$uPrincipalRes['principal'] : 0;
                } else {
                    $countUstandardPrincipal = $total_balance;
                }
            } else {
                $countUstandardPrincipal = $URes['principal'] > 0 ? $URes['principal'] : $total_balance;
            }
            if(!$BRes || empty($BRes)) {
                if(isset($bPrincipalRes['principal']) && $bPrincipalRes['principal'] > 0) {
                    $countBstandardPrincipal = isset($bPrincipalRes['principal']) ? (float)$bPrincipalRes['principal'] : 0;
                } else {
                    $countBstandardPrincipal = (float)$total_balance / $btcPrice;
                }
            } else {
                $countBstandardPrincipal = $BRes['principal'] > 0 ? $BRes['principal'] : (float)$total_balance / $btcPrice;
            }
        } else {
            //本金
            if ($direction == 1) {
                $countUstandardPrincipal = (float)$total_balance + (float)$amount;
                $countBstandardPrincipal = ((float)$total_balance / $btcPrice) + ((float)$amount / $btcPrice);
            } else {
                $countUstandardPrincipal = (float)$total_balance - (float)$amount;
                $countBstandardPrincipal = ((float)$total_balance / $btcPrice) - ((float)$amount / $btcPrice);
            }
        }


        $UTotalBalance = $balanceDetails['usdtBalance'] + $balanceDetails['btcValuation']; //U本位总结余 = USDT数量+BTC数量*价格
        $BTotalBalance = $balanceDetails['btcBalance'] + $balanceDetails['usdtBalance'] / $btcPrice; //币本位结余 = BTC数量+USDT数量/价格

        //总利润 = 总结余 - 本金
        $UProfit = $UTotalBalance - $countUstandardPrincipal;
        $BProfit = $BTotalBalance - $countBstandardPrincipal;

        //总利润率 = 利润 / 本金
        $UProfitRate = $countUstandardPrincipal > 0 ? $UProfit / $countUstandardPrincipal : 0;
        $BProfitRate = $countBstandardPrincipal > 0 ? $BProfit / $countBstandardPrincipal : 0;

        //日利润 日利润率
        $dailyUProfit = 0; //昨日U本位利润
        $dailyBProfit = 0; //昨日B本位利润
        $dailyUProfitRate = 0; //昨日B本位利润率
        $dailyBProfitRate = 0; //昨日B本位利润率
        $yestUTotalBalance = isset($uPrincipalRes['total_balance']) ? (float)$uPrincipalRes['total_balance'] : 0;
        $yestBTotalBalance = isset($bPrincipalRes['total_balance']) ? (float)$bPrincipalRes['total_balance'] : 0;
        $depositToday = self::getInoutGoldDepositToday(); //获取今日入金数量
        $dailyUProfit = $UTotalBalance - $yestUTotalBalance - $depositToday; //U本位日利润 = 今日的总结余-昨日的总结余-今日入金数量
        $dailyBProfit = $BTotalBalance - $yestBTotalBalance - ($depositToday / $btcPrice); //币本位日利润 = 今日的总结余-昨日的总结余-今日入金数量
        $dailyUProfitRate = $yestUTotalBalance > 0 ? $dailyUProfit / $yestUTotalBalance * 100 : 0; // 日利润率
        $dailyBProfitRate = $yestBTotalBalance > 0 ? $dailyBProfit / $yestBTotalBalance * 100 : 0;

        $pig_name = self::gettTradingPairName('Okx');
        $UaverageDayRate = self::name('piggybank_currency_date')->where(['standard' => 1, 'product_name' => $pig_name])->whereNotIn('date', $date)->avg('daily_profit_rate'); //获取U本位平均日利率
        $UaverageYearRate = $UaverageDayRate * 365; //平均年利率 = 平均日利率 * 365
        $BaverageDayRate = self::name('piggybank_currency_date')->where(['standard' => 2, 'product_name' => $pig_name])->whereNotIn('date', $date)->avg('daily_profit_rate'); //获取B本位平均日利率
        $BaverageYearRate = $BaverageDayRate * 365; //平均年利率 = 平均日利率 * 365

        self::startTrans();
        try {
            $URes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
            if ($URes && count((array)$URes) > 0) {
                $upDataU = [
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'daily_profit' => $dailyUProfit,
                    'daily_profit_rate' => $dailyUProfitRate, //日利润率
                    'average_day_rate' => $UaverageDayRate,
                    'average_year_rate' => $UaverageYearRate,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'price' => $btcPrice,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update($upDataU);
            } else {
                $insertDataU = [
                    'product_name' => $product_name,
                    'standard' => 1,
                    'date' => $date,
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'daily_profit' => $dailyUProfit,
                    'daily_profit_rate' => $dailyUProfitRate,
                    'average_day_rate' => $UaverageDayRate,
                    'average_year_rate' => $UaverageYearRate,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'price' => $btcPrice,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('piggybank_currency_date')->insertGetId($insertDataU);
            }
            if ($saveUres !== false) {
                $BRes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
                if ($BRes && count((array)$BRes) > 0) {
                    $upDataB = [
                        'principal' => $countBstandardPrincipal,
                        'total_balance' => $BTotalBalance,
                        'daily_profit' => $dailyBProfit,
                        'daily_profit_rate' => $dailyBProfitRate,
                        'average_day_rate' => $BaverageDayRate,
                        'average_year_rate' => $BaverageYearRate,
                        'profit' => $BProfit,
                        'profit_rate' => $BProfitRate,
                        'price' => $btcPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update($upDataB);
                } else {
                    $insertDataB = [
                        'product_name' => $product_name,
                        'standard' => 2,
                        'date' => $date,
                        'principal' => $countBstandardPrincipal,
                        'total_balance' => $BTotalBalance,
                        'daily_profit' => $dailyBProfit,
                        'daily_profit_rate' => $dailyBProfitRate,
                        'average_day_rate' => $BaverageDayRate,
                        'average_year_rate' => $BaverageYearRate,
                        'profit' => $BProfit,
                        'profit_rate' => $BProfitRate,
                        'price' => $btcPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('piggybank_currency_date')->insertGetId($insertDataB);
                }
                if ($saveBres !== false) {
                    if ($amount > 0) {
                        $isIntOut = self::setInoutGoldRecord($amount, $btcPrice, $direction, $remark);
                        if ($isIntOut) {
                            self::commit();
                            return true;
                        }
                    } else {
                        self::commit();
                        return true;
                    }
                }
            }
            self::rollback();
            return false;
        } catch (\Exception $e) {
            self::rollback();
            return false;
        }
    }

    /**
     * 实时更新今日总结余 币价 作废
     * @author qinlh
     * @since 2022-09-05
     */
    public static function saveUpdateDayTotalBalance($product_name='') {
        $date = date('Y-m-d');
        //总结余
        $balanceDetails = self::getTradeValuation($product_name);
        $btcPrice = $balanceDetails['btcPrice'];
        $UTotalBalance = $balanceDetails['usdtBalance'] + $balanceDetails['btcValuation']; //U本位总结余 = USDT数量+BTC数量*价格
        $BTotalBalance = $balanceDetails['btcBalance'] + $balanceDetails['usdtBalance'] / $btcPrice; //币本位结余 = BTC数量+USDT数量/价格
        
        $URes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
        if($URes) {
            self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update(['total_balance' => $UTotalBalance, 'price' => $btcPrice]);
        }
        $BRes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
        if($BRes) {
            self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update(['total_balance' => $BTotalBalance, 'price' => $btcPrice]);
        }
        return true;
    }

    /**
     * 获取U本位币本位累计本金
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getPiggybankStandard($standard=0)
    {
        if ($standard > 0) {
            $date = date('Y-m-d');
            $pig_name = self::gettTradingPairName('Okx');
            $total = self::name('piggybank_currency_date')->where(['product_name' => $pig_name, 'standard' => $standard, 'date' => ['<>', $date]])->sum('principal');
            if ($total) {
                return $total;
            }
        }
        return 0;
    }

    /**
     * 获取总的利润或者今日利润
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getUStandardProfit($product_name='', $date='')
    {
        $where = [];
        $where['pair'] = ['>', 0];
        $where['product_name'] = $product_name;
        if ($date && $date !== '') {
            $start_date = $date . ' 00:00:00';
            $end_date = $date . ' 23:59:59';
            $where['time'] = ['between time', [$start_date, $end_date]];
        }
        $data = self::name('piggybank')->where($where)->select()->toArray();
        if ($data) {
            $newArray = [];
            foreach ($data as $key => $val) {
                $newArray[$val['pair']] = $val;
            }
            $countProfit = 0;
            foreach ($newArray as $key => $val) {
                $countProfit += $val['profit'];
            }
            return $countProfit;
        }
        return 0;
    }

    /**
     * 记录出入金记录
     * @author qinlh
     * @since 2022-08-20
     */
    public static function setInoutGoldRecord($amount='', $price, $type=0, $remark='')
    {
        if ($amount !== 0 && $type > 0) {
            if($type == 1) {
                $total_balance = self::getInoutGoldTotalBalance() + (float)$amount;
                $amount_num = $amount;
            } else {
                $total_balance = self::getInoutGoldTotalBalance() - (float)$amount;
                $amount_num = $amount *= -1;
            }
            $pig_id = Okx::gettTradingPairId('Okx');
            $insertData = [
                'pig_id' => $pig_id,
                'amount' => $amount_num,
                // 'price' => $price,
                'type' => $type,
                'total_balance' => $total_balance,
                'remark' => $remark,
                'time' => date('Y-m-d H:i:s'),
            ];
            $res = self::name('inout_gold')->insertGetId($insertData);
            if ($res) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取出入金总结余
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getInoutGoldTotalBalance()
    {
        $pig_id = Okx::gettTradingPairId('Okx');
        $count = self::name('inout_gold')->where('pig_id', $pig_id)->sum('amount');
        if ($count !== 0) {
            return $count;
        }
        return 0;
    }

    /**
     * 获取今日入金总数量
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getInoutGoldDepositToday()
    {
        $pig_id = Okx::gettTradingPairId('Okx');
        $amount = self::name('inout_gold')->whereTime('time', 'today')->where(['pig_id' => $pig_id])->sum('amount');
        if ($amount !== 0) {
            return $amount;
        }
        return 0;
    }

    /**
     * 获取交易对名称
     * @author qinlh
     * @since 2023-01-10
     */
    public static function gettTradingPairName($exchange='') {
        $data = self::name('currency_list')->where(['exchange' => $exchange, 'state' => 1])->find();
        if($data && count((array)$data) > 0) {
            return $data['name'];
        }
        return [];
    }

    /**
     * 获取昨天累计本金
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getPiggybankCurrencyPrincipal($standard=0)
    {
        if ($standard > 0) {
            $date = date("Y-m-d", strtotime("-1 day")); //获取昨天的时间
            $pig_name = self::gettTradingPairName('Okx');
            $res = self::name('piggybank_currency_date')->where(['date' => $date, 'standard' => $standard, 'product_name' => $pig_name])->find();
            if ($res && count((array)$res) > 0) {
                return $res;
            }
        }
        return [];
    }

    /**
    * 获取出入金记录
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getInoutGoldList($where, $page, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("inout_gold")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("inout_gold")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 每日存钱罐数据统计
     * @author qinlh
     * @since 2025-06-23
     */
    public static function piggybankDate() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $currencyArr = explode('-', $transactionCurrency);
        $currency1 = $currencyArr[0]; //交易币种
        $currency2 = $currencyArr[1]; //USDT
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try { 
            $balanceDetails = $exchange->fetch_account_balance();
            // p($balanceDetails);
            $btcBalance = 0;
            $usdtBalance = 0;
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['eq'])) {
                    if($v['ccy'] == $currency1 || $v['ccy'] == $currency2) {
                        if($v['ccy'] == $currency1 && (float)$v['eq'] > 0) {
                            $btcBalance += (float)$v['eq'];
                        }
                        if($v['ccy'] == $currency2 && (float)$v['eq'] > 0) {
                            $usdtBalance += (float)$v['eq'];
                        }
                    }
                }
            }
            $totalAssets = 0;//总资产
            $marketIndexTickers = self::fetchMarketIndexTickers($transactionCurrency); //获取交易BTC价格
            // $marketIndexTickers = $exchange->fetch_market_index_tickers($transactionCurrency); //获取交易BTC价格
            // p($marketIndexTickers);
            if($marketIndexTickers && isset($marketIndexTickers['last']) && $marketIndexTickers['last'] > 0) {
                $btcValuation = $btcBalance * (float)$marketIndexTickers['last'];
                $usdtValuation = $usdtBalance;
            }
            $totalAssets = $btcValuation + $usdtValuation;
            
            $date = date('Y-m-d');

            //获取总的利润
            $countProfit = Piggybank::getUStandardProfit($transactionCurrency); //获取总的利润 网格利润
            $countProfitRate = $countProfit / $totalAssets * 100; //网格总利润率 = 总利润 / 总市值
            $dayProfit = Piggybank::getUStandardProfit($transactionCurrency, $date); //获取总的利润 网格利润
            $dayProfitRate = $dayProfit / $totalAssets * 100; //网格日利润率 = 日利润 / 总市值
            $averageDayRate = Db::name('piggybank_date')->whereNotIn('date', $date)->avg('grid_day_spread_rate'); //获取平均日利润率
            $averageYearRate = $averageDayRate * 365; //平均年利率 = 平均日利率 * 365
            $data = Db::name('piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->find();
            if($data && count((array)$data) > 0) {
                $upData = [
                    'count_market_value'=>$totalAssets, 
                    'grid_spread' => $countProfit,
                    'grid_spread_rate' => $countProfitRate,
                    'grid_day_spread' => $dayProfit,
                    'grid_day_spread_rate' => $dayProfitRate,
                    'average_day_rate' => $averageDayRate,
                    'average_year_rate' => $averageYearRate,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $res = Db::name('piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->update($upData);
            } else {
                $insertData = [
                    'product_name' => $transactionCurrency, 
                    'date'=>$date, 
                    'count_market_value'=>$totalAssets, 
                    'grid_spread' => $countProfit,
                    'grid_spread_rate' => $countProfitRate,
                    'grid_day_spread' => $dayProfit,
                    'grid_day_spread_rate' => $dayProfitRate,
                    'average_day_rate' => $averageDayRate,
                    'average_year_rate' => $averageYearRate,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $res = Db::name('piggybank_date')->insertGetId($insertData);
            }
            if($res !== false) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            // p($e);
            logger("GMX-USDT 每日存钱罐数据统计 Error \r\n".$e);
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取 currency_list 表中的币种列表
     * @param array $where 查询条件，默认为空获取所有
     * @param int $page 当前页码，默认为1
     * @param int $limit 每页条数，默认为10
     * @return array 返回包含总数、总页数和列表数据的数组
     */
    public static function getCurrencyList($where = [], $page = 1, $limit = 10) {
        $count = self::name('currency_list')->where($where)->count();
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('currency_list')
                    ->where($where)
                    ->page($page, $limit)
                    ->order('id desc')
                    ->select()
                    ->toArray();
        return ['count' => $count, 'allpage' => $allpage, 'lists' => $lists];
    }

    /**
     * 获取交易对名称
     * @author qinlh
     * @since 2025-06-23
     */
    public static function getTradingPairData($currency_id=0) {
        $data = self::name('currency_list')->where(['id' => $currency_id, 'state' => 1])->find();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }

    /**
     * 获取交易对估值
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradeValuation($transactionCurrency) {
        $balanceDetails = self::getTradePairBalance($transactionCurrency);
        $usdtBalance = isset($balanceDetails['usdtBalance']) ? $balanceDetails['usdtBalance'] : 0;
        $btcBalance = isset($balanceDetails['btcBalance']) ? $balanceDetails['btcBalance'] : 0;
        $marketIndexTickers = self::fetchMarketIndexTickers($transactionCurrency); //获取交易BTC价格
        $btcPrice = 1;
        $btcValuation = 0;
        $usdtValuation = 0;
        if($marketIndexTickers && isset($marketIndexTickers['last']) && $marketIndexTickers['last'] > 0) {
            $btcPrice = (float)$marketIndexTickers['last'];
            $btcValuation = $btcBalance * (float)$marketIndexTickers['last'];
            $usdtValuation = $usdtBalance;
        }
        return ['btcPrice' => $btcPrice, 'usdtBalance' => $usdtBalance, 'btcBalance' => $btcBalance, 'btcValuation' => $btcValuation, 'usdtValuation' => $usdtValuation];
    }

     /**
     * 获取交易对余额
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradePairBalance($transactionCurrency) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $currencyArr = explode('-', $transactionCurrency);
        $currency1 = $currencyArr[0]; //交易币种
        $currency2 = $currencyArr[1]; //USDT
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            $balanceDetails = $exchange->fetch_account_balance();
            // p($balance);
            $btcBalance = 0;
            $usdtBalance = 0;
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['eq'])) {
                    if($v['ccy'] == $currency1 || $v['ccy'] == $currency2) {
                        if($v['ccy'] == $currency1 && (float)$v['eq'] > 0) {
                            $btcBalance += (float)$v['eq'];
                        }
                        if($v['ccy'] == $currency2 && (float)$v['eq'] > 0) {
                            $usdtBalance += (float)$v['eq'];
                        }
                    }
                }
            }
            return ['btcBalance' => $btcBalance, 'usdtBalance' => $usdtBalance];
        } catch (\Exception $e) {
            logger("GMX-USDT 获取交易对余额 Error \r\n".$e);
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取账户币种基本信息
     * @author qinlh
     * @since 2025-06-23
     */
    public static function getSymbolInfo($symbol) {
        $url = Config('piggybank_uri') . "/symbol-info";
        $params = [
            'symbol' => $symbol,
        ];
        $response_string = RequestService::doCurlGetRequest($url, $params);
        $response_arr = $response_string;
        if($response_arr) {
            return $response_arr;
        }
        return [];
    }

    
    /**
     * 获取单个产品行情信息 价格
     * @author qinlh
     * @since 2022-08-19
     */
    public static function fetchMarketIndexTickers($transactionCurrency) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            $marketIndexTickers = $exchange->fetch_ticker($transactionCurrency); //获取交易BTC价格
            return $marketIndexTickers;
        } catch (\Exception $e) {
            logger("GMX-USDT 获取单个产品行情信息 价格 Error \r\n".$e);
            return array(0, $e->getMessage());
        }
    }


    /**
     * 测试平衡仓位
     * @author qinlh
     * @since 2022-11-21
     */
    public static function testBalancePosition() {
        $result = [];
        $arr = self::getSymbolInfo("BTC-USDT");
        p($arr);
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $currencyArr = explode('-', $transactionCurrency);
        $currency1 = $currencyArr[0]; //交易币种
        $currency2 = $currencyArr[1]; //USDT
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));

        $changeRatioNum = 2; //涨跌比例 2%
        $balanceRatio = '1:1'; //平衡比例
        $balanceRatioArr = explode(':', $balanceRatio);
        $sellPropr = ($changeRatioNum / $changeRatioNum) + ($changeRatioNum / 100); //出售比例
        $buyPropr = ($changeRatioNum / $changeRatioNum) - ($changeRatioNum / 100); //购买比例

        //获取最小下单数量
        $rubikStatTakerValume = $exchange->fetch_markets_by_type('SPOT', ['instId'=>$transactionCurrency]);
        $minSizeOrderNum = isset($rubikStatTakerValume[0]['info']['minSz']) ? $rubikStatTakerValume[0]['info']['minSz'] : 0; //最小下单数量
        $base_ccy = isset($rubikStatTakerValume[0]['info']['baseCcy']) ? $rubikStatTakerValume[0]['info']['baseCcy'] : ''; //交易货币币种
        $quote_ccy = isset($rubikStatTakerValume[0]['info']['quoteCcy']) ? $rubikStatTakerValume[0]['info']['quoteCcy'] : ''; //计价货币币种

        $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
        $btcBalance = $tradeValuation['btcBalance'];
        $usdtBalance = $tradeValuation['usdtBalance'];
        $btcValuation = $tradeValuation['btcValuation'];
        $usdtValuation = $tradeValuation['usdtValuation'];
        $btcPrice = $tradeValuation['btcPrice'];


        $balancedValuation = self::getLastBalancedValuation(); // 获取上一次平衡状态下估值
        $changeRatio = 0;
        if($balancedValuation > 0) {
            $changeRatio = abs($btcValuation - $usdtValuation) / $balancedValuation * 100;
        } else if($usdtValuation > 0){
            $changeRatio = abs($btcValuation - $usdtValuation) / $usdtValuation * 100;
        }

        $btcSellNum = $balanceRatioArr[0] * (($btcValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        $btcSellOrdersNumber = $btcSellNum / $btcPrice;

        $usdtBuyNum = $balanceRatioArr[1] * (($usdtValuation - $btcValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
        $usdtBuyOrdersNumber = $usdtBuyNum;

        $result['minSizeOrderNum'] = $minSizeOrderNum;
        $result['base_ccy'] = $base_ccy;
        $result['quote_ccy'] = $quote_ccy;
        $result['tradingPrice'] = $btcPrice;
        $result['usdtBalance'] = $usdtBalance;
        $result['usdtValuation'] = $usdtValuation;
        $result['btcBalance'] = $btcBalance;
        $result['btcValuation'] = $btcValuation;
        $result['defaultRatio'] = $changeRatioNum;
        $result['changeRatio'] = $changeRatio;
        $result['sellOrdersNumberStr'] = '';
        $getLastRes = self::getLastRes();
        $lastPrice = (float)$getLastRes['price']; //最近一次交易价格
        $result['lastTimePrice'] = $lastPrice;
        $result['sellingPrice'] = $lastPrice * $sellPropr;
        $result['buyingPrice'] = $lastPrice * $buyPropr;
        if($btcValuation > $usdtValuation) { //GMX的估值超过BUSD时候，卖GMX换成BUSDT
            $result['sellOrdersNumberStr'] = $currency1 . '出售数量: ' . $btcSellOrdersNumber ;
        }
        if($btcValuation < $usdtValuation) { //GMX的估值低于BUSD时，买GMX，换成BUSD
            $result['sellOrdersNumberStr'] = $currency2 . '购买数量: ' . $usdtBuyOrdersNumber ;
        }

        $peningOrderList = self::getOpenPeningOrder();
        //计算下一次下单购买出售数据
        $result['pendingOrder'] = [];
        $result['pendingOrder']['buy']['price'] = 0;
        $result['pendingOrder']['buy']['amount'] = 0;
        $result['pendingOrder']['buy']['btcValuation'] = 0;
        $result['pendingOrder']['buy']['usdtValuation'] = 0;
        $result['pendingOrder']['sell']['price'] = 0;
        $result['pendingOrder']['sell']['amount'] = 0;
        $result['pendingOrder']['sell']['btcValuation'] = 0;
        $result['pendingOrder']['sell']['usdtValuation'] = 0;
        foreach ($peningOrderList as $key => $val) {
            if($val['type'] == 1) {
                $result['pendingOrder']['buy']['price'] = $val['price'];
                $result['pendingOrder']['buy']['amount'] = $val['amount'];
                // $result['pendingOrder']['buy']['bifiValuation'] = ($bifiBalance + (float)$val['amount']) * $val['price'];
                $result['pendingOrder']['buy']['btcValuation'] = $val['clinch_currency1'] * $val['price'];
                // $result['pendingOrder']['buy']['busdValuation'] = $busdValuation - ((float)$val['amount'] * $val['price']);
                $result['pendingOrder']['buy']['usdtValuation'] = $val['clinch_currency2'];
            } else {
                $result['pendingOrder']['sell']['price'] = $val['price'];
                $result['pendingOrder']['sell']['amount'] = $val['amount'];
                // $result['pendingOrder']['sell']['bifiValuation'] = ($bifiBalance - (float)$val['amount']) * $val['price'];
                $result['pendingOrder']['sell']['btcValuation'] = $val['clinch_currency1'] * $val['price'];
                // $result['pendingOrder']['sell']['busdValuation'] = $busdValuation + ((float)$val['amount'] * $val['price']);
                $result['pendingOrder']['sell']['usdtValuation'] = $val['clinch_currency2'];
            }
        }

        return $result;
    }
    
}
