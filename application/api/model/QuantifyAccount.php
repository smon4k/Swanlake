<?php
// +----------------------------------------------------------------------
// | 文件说明：量化账户监控数据统计
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2023 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2023-01-30
// +----------------------------------------------------------------------
namespace app\api\model;

use think\Model;

class QuantifyAccount extends Base
{
    public static $apiKey = 'OabKFBSGn1xDrGUnakH4Ft6M3TZgyhmEwHDlqqFlcd8aaKOqE2P1oXJHrLCWo8D1';
    public static $secret = 'vbz1M0gf3PPMI0QoE0uAO8OuZQ2DYfVJUjGCYGTNVJAGSwdBepHHsHP0MJbWl0lp';


    /**
    * 获取U币本位统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getQuantifyAccountDateList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("quantify_equity_monitoring")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("quantify_equity_monitoring")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("date desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 计算量化账户数据
     * @params [account_id 账户id]
     * @params [direction 出金 入金]
     * @params [amount 数量]
     * @params [remark 描述]
     * @author qinlh
     * @since 2023-01-31
     */
    public static function calcQuantifyAccountData($account_id=0, $direction=0, $amount=0, $remark='') {
        if($account_id) {
            self::startTrans();
            try {
                date_default_timezone_set("Etc/GMT-8");
                $date = date('Y-m-d');
                // $date = '2023-01-02';
                $accountInfo = self::getAccountInfo($account_id);
                $tradingPrice = 1;
                if($accountInfo['type'] == 1) { //Binance
                    $balanceList = self::getTradePairBalance($accountInfo);
                } else { //OKX
                    $balanceList = self::getOkxTradePairBalance($accountInfo);
                }
                if($account_id == 1) {
                    $totalBalance = $balanceList['usdtBalance'] + 900; //总结余
                } else {
                    $totalBalance = $balanceList['usdtBalance']; //总结余
                }
                // $totalBalance = 42792.03; //总结余
                $yestData = self::getYestTotalPrincipal($account_id, $date); //获取昨天的数据
                $dayData = self::getDayTotalPrincipal($account_id, $date); //获取今天的数据
                $countStandardPrincipal = 0; //累计本金
                $total_balance = self::getInoutGoldTotalBalance($account_id); //出入金总结余
                if (!$amount || $amount == 0) { 
                    if(!$dayData || empty($dayData)) { //今日第一次执行 获取昨日本金
                        if(isset($yestData['principal']) && $yestData['principal'] > 0) {
                            $countStandardPrincipal = isset($yestData['principal']) ? (float)$yestData['principal'] : 0;
                        } else {
                            $countStandardPrincipal = $total_balance;
                        }
                    } else {
                        $countStandardPrincipal = isset($dayData['principal']) ? $dayData['principal'] : $total_balance;
                    }
                } else {
                    //本金
                    if ($direction == 1) { //入金
                        $countStandardPrincipal = (float)$total_balance + (float)$amount;
                    } else {
                        $countStandardPrincipal = (float)$total_balance - (float)$amount;
                    }
                }
                
                $dailyProfit = 0; //昨日利润
                $dailyProfitRate = 0; //昨日利润率
                $yestTotalBalance = isset($yestData['total_balance']) ? (float)$yestData['total_balance'] : 0;
                $depositToday = self::getInoutGoldDepositToday($account_id, $date); //获取今日入金数量
                // p($depositToday);
                $dailyProfit = $totalBalance - $yestTotalBalance - $depositToday; //日利润 = 今日的总结余-昨日的总结余-今日入金数量
                // if($dailyProfit < -801) {
                //     $totalBalance = $totalBalance + 700;
                //     $dailyProfit = $totalBalance - $yestTotalBalance - $depositToday; //日利润 = 今日的总结余-昨日的总结余-今日入金数量
                // }
                // $dailyProfitRate = $yestTotalBalance > 0 ? $dailyProfit / $yestTotalBalance * 100 : 0; //日利润率 = 日利润 / 昨日的总结余
                $dailyProfitRate = $countStandardPrincipal > 0 ? $dailyProfit / $countStandardPrincipal * 100 : 0; //日利润率 = 日利润 / 本金
                $averageDayCountNum = self::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => ['<=', $date]])->count(); //获取平均数总人数
                $averageDayRateRes = self::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => ['<=', $date]])->avg('daily_profit_rate'); //获取平均日利率
                if(!$dayData || empty($dayData)) { //今日第一次执行 加上今天的日利润率
                    $averageDayRate = ($averageDayRateRes * $averageDayCountNum + $dailyProfitRate) / (1 + $averageDayCountNum);
                } else {
                    $averageDayRate = $averageDayRateRes;
                }
                $averageYearRate = $averageDayRate * 365; //平均年利率 = 平均日利率 * 365
                $profit = $totalBalance - $countStandardPrincipal;//总利润 = 总结余 - 本金
                $profitRate = $countStandardPrincipal > 0 ? $profit / $countStandardPrincipal : 0;//总利润率 = 利润 / 本金
                // p($daily);
                if ($dayData && count((array)$dayData) > 0) {
                    $upData = [
                        'principal' => $countStandardPrincipal,
                        'total_balance' => $totalBalance,
                        'daily_profit' => $dailyProfit,
                        'daily_profit_rate' => $dailyProfitRate, //日利润率
                        'average_day_rate' => $averageDayRate, //平均日利率
                        'average_year_rate' => $averageYearRate, //平均年利率
                        'profit' => $profit,
                        'profit_rate' => $profitRate,
                        'price' => $tradingPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveUres = self::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => $date])->update($upData);
                } else {
                    $insertData = [
                        'account_id' => $account_id,
                        'date' => $date,
                        'principal' => $countStandardPrincipal,
                        'total_balance' => $totalBalance,
                        'daily_profit' => $dailyProfit,
                        'daily_profit_rate' => $dailyProfitRate, //日利润率
                        'average_day_rate' => $averageDayRate, //平均日利率
                        'average_year_rate' => $averageYearRate, //平均年利率
                        'profit' => $profit,
                        'profit_rate' => $profitRate,
                        'price' => $tradingPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveUres = self::name('quantify_equity_monitoring')->insertGetId($insertData);
                }
                if ($saveUres !== false) {
                    if ($amount > 0) {
                        $isIntOut = self::setInoutGoldRecord($account_id, $amount, $tradingPrice, $direction, $remark);
                        if ($isIntOut) {
                            self::commit();
                            return true;
                        }
                    } else {
                        self::commit();
                        return true;
                    }
                }
                self::rollback();
                return false;
            } catch (\Exception $e) {
                $error_msg = json_encode([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ], JSON_UNESCAPED_UNICODE);
                echo $error_msg . "\r\n";
                self::rollback();
                return false;
            }
        }
    }

     /**
     * 获取Binance余额
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradePairBalance($accountInfo) {
        // $assetArr = explode('-', $transactionCurrency);
        // $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
        // $dataJson = self::getCache($cache_params);
        // $data = json_decode($dataJson, true);
        // if($data && count((array)$data) > 0) {
        //     return $data;
        // }
        if($accountInfo) {
            $vendor_name = "ccxt.ccxt";
            Vendor($vendor_name);
            $className = "\ccxt\\binance";
            $exchange  = new $className(array( //子账户
                'apiKey' => $accountInfo['api_key'],
                'secret' => $accountInfo['secret_key'],
            ));
            try {
                $balanceDetails = $exchange->fetch_balance([]);
                // p($balanceDetails);
                $usdtBalance = 0;
                if(isset($balanceDetails['info']['balances'])) {
                    foreach ($balanceDetails['info']['balances'] as $k => $v) {
                        if(isset($v['asset'])) {
                            if($v['asset'] == 'USDT') {
                                if((float)$v['free'] >= 0) {
                                    $usdtBalance += (float)$v['free'];
                                }
                                @self::updateQuantifyAccountDetails($accountInfo['id'], $v['asset'], (float)$v['free'], (float)$v['free']);
                            }
                            if($v['asset'] == 'BIFI' || $v['asset'] == 'GMX' || $v['asset'] == 'BTC' || $v['asset'] == 'ETH' || $v['asset'] == 'BNB' || $v['asset'] == 'TON' || $v['asset'] == 'SAND' || $v['asset'] == 'ARB' || $v['asset'] == 'OKB') {
                                if((float)$v['free'] >= 0) {
                                    $prices = $exchange->fetch_ticker_price($v['asset'] . 'USDT');
                                    $valuation = (float)$v['free'] * (float)$prices['price'];
                                    $usdtBalance += $valuation;
                                    @self::updateQuantifyAccountDetails($accountInfo['id'], $v['asset'], (float)$v['free'], $valuation);

                                    //开始写入每个交易对交易明细数据
                                    $maxTradeId = self::getAccountTradeDetailsMaxTradeId($accountInfo['id'], $v['asset'].'USDT');
                                    if($maxTradeId) {
                                        $tradesList = $exchange->fetch_my_trades($v['asset'].'USDT', null, null, ['fromId' => $maxTradeId]);
                                    } else {
                                        $tradesList = $exchange->fetch_my_trades($v['asset'].'USDT');
                                    }
                                    $setAccountTradeDetailsRes = self::setAccountTradeDetails($accountInfo['id'], $v['asset'], $tradesList, $maxTradeId);
                                }
                            }
                        }
                    }
                }
                self::commit();
                //  p($usdtBalance);
                $returnArray = ['usdtBalance' => $usdtBalance];
                // $dataJson = json_encode($returnArray);
                // self::setCache($cache_params, $dataJson);
                return $returnArray;
            } catch (\Exception $e) {
                $error_msg = json_encode([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ], JSON_UNESCAPED_UNICODE);
                self::rollback();
                echo $error_msg . "\r\n";
                return false;
            }
        }
    }

    /**
     * 获取Okx交易对余额
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getOkxTradePairBalance($accountInfo) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => $accountInfo['api_key'],
            'secret' => $accountInfo['secret_key'],
            'password' => $accountInfo['pass_phrase'],
        ));
        try {
            $balanceDetails = $exchange->fetch_account_balance();
            // p($balance);
            $btcBalance = 0;
            $usdtBalance = 0;
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['ccy'])) {
                    if($v['ccy'] == 'USDT' || $v['ccy'] == 'BIFI' || $v['ccy'] == 'GMX' || $v['ccy'] == 'BTC' || $v['ccy'] == 'ETH' || $v['ccy'] == 'SAND') {
                        if((float)$v['eq'] >= 0) {
                            // $prices = $exchange->fetch_ticker($v['ccy'].'USDT'); //获取交易BTC价格
                            // $valuation = (float)$v['eq'] * (float)$prices['price'];
                            $usdtBalance += (float)$v['eqUsd'];
                            @self::updateQuantifyAccountDetails($accountInfo['id'], $v['ccy'], (float)$v['eq'], (float)$v['eqUsd']);
                        }
                    }
                }
            }
            $returnArray = ['usdtBalance' => $usdtBalance];
            return $returnArray;
        } catch (\Exception $e) {
            logger("获取交易对余额 Error \r\n".$e);
            return array(0, $e->getMessage());
        }
    }

    /**
     * 更新账户币种余额明细
     * @author qinlh
     * @since 2023-02-23
     */
    public static function updateQuantifyAccountDetails($account_id=0, $currency='', $balance=0, $valuation=0) {
        if($account_id && $currency) {
            $date = date('Y-m-d');
            $res = self::name('quantify_account_details')->where(['account_id' => $account_id, 'currency' => $currency])->find();
            if($res && count((array)$res) > 0) {
                $saveRes = self::name('quantify_account_details')->where('id', $res['id'])->update([
                    'balance' => $balance,
                    'valuation' => $valuation,
                    'time' => date('Y-m-d H:i:s')
                ]);
                if($saveRes) {
                    return true;
                }
            } else {
                $saveRes = self::name('quantify_account_details')->insertGetId([
                    'account_id' => $account_id,
                    'currency' => $currency,
                    'balance' => $balance,
                    'valuation' => $valuation,
                    'time' => date('Y-m-d H:i:s'),
                    'state' => 1,
                ]);
                if($saveRes) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取指定账户数据详情
     * @author qinlh
     * @since 2023-01-31
     */
    public static function getAccountInfo($account_id=0) {
        if($account_id) {
            $data = self::name('quantify_account')->where('id', $account_id)->find();
            if($data && count((array)$data) > 0) {
                return $data->toArray();
            }
        }
        return [];
    }

    /**
     * 获取指定账户列表数据
     * @author qinlh
     * @since 2023-01-31
     */
    public static function getAccountList($where=[]) {
        $data = self::name('quantify_account')->where($where)->select();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }

    /**
     * 获取昨天数据
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getYestTotalPrincipal($account_id=0, $date='')
    {
        if($account_id) {
            if($date && $date !== '') {
                $dayDate = $date;
                $date = date("Y-m-d", strtotime("-1 day", strtotime($date))); //获取昨天的时间
            } else {
                $dayDate = date("Y-m-d");
                $date = date("Y-m-d", strtotime("-1 day")); //获取昨天的时间
            }
            $res = self::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => $date])->find();
            if ($res && count((array)$res) > 0) {
                return $res;
            } else {
                $res = self::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => ['<', $dayDate]])->order('date desc')->find();
                return $res;
            }
        }
        return [];
    }

    /**
     * 获取今天数据
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getDayTotalPrincipal($account_id=0, $dates='')
    {
        if($account_id) {
            if($dates && $dates !== '') {
                $date = $dates;
            } else {
                $date = date("Y-m-d"); //获取昨天的时间
            }
            $res = self::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => $date])->find();
            if ($res && count((array)$res) > 0) {
                return $res;
            }
        }
        return [];
    }

    /**
     * 获取出入金总结余
     * @author qinlh
     * @since 2023-01-31
     */
    public static function getInoutGoldTotalBalance($account_id=0)
    {
        if($account_id) {
            $count = self::name('quantify_inout_gold')->where('account_id', $account_id)->sum('amount');
            if ($count !== 0) {
                return $count;
            }
        }
        return 0;
    }

    /**
     * 获取今日入金总数量
     * @author qinlh
     * @since 2023-01-31
     */
    public static function getInoutGoldDepositToday($account_id=0, $date)
    {
        if($account_id) {
            $start_time = $date . "00:00:00";
            $end_time = $date . "23:59:59";
            $amount = self::name('quantify_inout_gold')->where('account_id', $account_id)->whereTime('time', 'between', [$start_time, $end_time])->sum('amount');
            if ($amount !== 0) {
                return $amount;
            }
        }
        return 0;
    }

    /**
     * 记录出入金记录
     * @author qinlh
     * @since 2023-01-31
     */
    public static function setInoutGoldRecord($account_id=0, $amount='', $price, $type=0, $remark='')
    {
        if ($account_id && $amount !== 0 && $type > 0) {
            $total_balance = 0;
            if($type == 1) {
                $amount_num = $amount;
                $total_balance = self::getInoutGoldTotalBalance($account_id) + (float)$amount;
            } else {
                $amount_num = $amount *= -1;
                $total_balance = self::getInoutGoldTotalBalance($account_id) - (float)$amount;
            }
            $insertData = [
                'account_id' => $account_id,
                'amount' => $amount_num,
                // 'price' => $price,
                'type' => $type,
                'total_balance' => $total_balance,
                'remark' => $remark,
                'time' => date('Y-m-d H:i:s'),
            ];
            $res = self::name('quantify_inout_gold')->insertGetId($insertData);
            if ($res) {
                return true;
            }
        }
        return false;
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
        $count = self::name("quantify_inout_gold")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("quantify_inout_gold")
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
     * 获取账户币种余额
     * @author qinlh
     * @since 2023-02-23
     */
    public static function getQuantifyAccountDetails($account_id=0) {
        if($account_id) {
            $data = self::name('quantify_account_details')->where('account_id', $account_id)->select();
            if($data && count((array)$data) > 0) {
                $resultArray = [];
                foreach($data as $key => $val) {
                    if($val['balance'] > 0) {
                        $resultArray[] = $val;
                    }
                }
                return $resultArray;
            }
        }
        return [];
    }

    /**
     * 获取账户币种交易明细
     * @author qinlh
     * @since 2023-04-04
     */
    public static function setAccountTradeDetails($account_id=0, $currency='', $list=[], $maxTradeId=0) {
        if($account_id && $currency && count((array)$list) > 0) {
            $insertDataAll = [];
            foreach ($list as $key => $val) {
                if($maxTradeId > 0 && $key == 0) {
                    continue;
                }
                $infoArr = $val['info'];
                $insertDataAll[] = [
                    'account_id' => $account_id,
                    'currency' => $currency,
                    'symbol' => $infoArr['symbol'],
                    'trade_id' => $infoArr['id'],
                    'order_id' => $infoArr['orderId'],
                    'price' => $infoArr['price'],
                    'qty' => $infoArr['qty'],
                    'quote_qty' => $infoArr['quoteQty'],
                    'trade_time' => date('Y-m-d H:i:s', $infoArr['time']/1000),
                    'time' => date('Y-m-d H:i:s')
                ];
            }
            $res = self::name('quantify_account_trade_details')->insertAll($insertDataAll);
            if($res) {
                return true;
            }
        }
    }

    /**
     * 获取账户币种交易明细最大Trade ID
     * @author qinlh
     * @since 2023-04-04
     */
    public static function getAccountTradeDetailsMaxTradeId($account_id=0, $currency='') {
        if($account_id && $currency !== '') {
            $sql = 'SELECT MAX(trade_id) AS max_trade_id FROM s_quantify_account_trade_details';
            $res = self::query($sql);
            if($res && count((array)$res) > 0) {
                return $res[0]['max_trade_id'];
            }
        }
        return 0;
    }
}