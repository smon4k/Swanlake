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
use cache\Rediscache;
use RequestService\RequestService;
use think\Config;

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
    public static function calcQuantifyAccountData($account_id=0, $direction=0, $amount=0, $remark='', $profit_amount=0, $profit_remark='') {
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
                    $totalBalance = !empty($balanceList['usdtBalance']) ? $balanceList['usdtBalance'] + 900 : 0; //总结余
                } else {
                    $totalBalance = !empty($balanceList['usdtBalance']) ? $balanceList['usdtBalance'] : 0; //总结余
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
                $dayProfit = self::getDayProfit($account_id, $date); //获取今日分润
                $dailyProfit = $totalBalance - $yestTotalBalance - $depositToday + $dayProfit; //日利润 = 今日的总结余-昨日的总结余-今日入金数量+今日分润
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
                $profit = $totalBalance - $countStandardPrincipal;//利润 = 总结余 - 本金
                
                $totalShareProfit = self::getTotalProfitBalance($account_id); //总分润 = 所有分润累计
                if($profit_amount) {
                    $totalShareProfit += $profit_amount;
                }
                $totalProfit = $profit + $totalShareProfit; //总利润 = 当前利润 + 总分润；
                $profitRate = $countStandardPrincipal > 0 ? $totalProfit / $countStandardPrincipal : 0;//利润率 = 总利润 / 本金
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
                        'total_share_profit' => $totalShareProfit,
                        'total_profit' => $totalProfit,
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
                        'total_share_profit' => $totalShareProfit,
                        'total_profit' => $totalProfit,
                        'price' => $tradingPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveUres = self::name('quantify_equity_monitoring')->insertGetId($insertData);
                }
                $isTrue = false;
                if ($saveUres !== false) {
                    if ($amount > 0) {
                        $isIntOut = self::setInoutGoldRecord($account_id, $amount, $tradingPrice, $direction, $remark);
                        if ($isIntOut) {
                            $isTrue = true;
                            // self::commit();
                            // return true;
                        }
                    } else {
                        $isTrue = true;
                        // self::commit();
                        // return true;
                    }
                }
                if($isTrue) {
                    if($profit_amount > 0) { //开始写入分润记录表
                        $IsDividendRec = self::setDividendRecord($account_id, $profit_amount, $profit_remark);
                        if($IsDividendRec) {
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
                                @self::updateQuantifyAccountDetails($accountInfo['id'], $v['asset'], (float)$v['free'], (float)$v['free'], 1);
                            }
                            if($v['asset'] == 'BIFI' || $v['asset'] == 'GMX' || $v['asset'] == 'BTC' || $v['asset'] == 'ETH' || $v['asset'] == 'BNB' || $v['asset'] == 'TON' || $v['asset'] == 'SAND' || $v['asset'] == 'ARB' || $v['asset'] == 'OKB') {
                                if((float)$v['free'] >= 0) {
                                    $prices = $exchange->fetch_ticker_price($v['asset'] . 'USDT');
                                    $valuation = (float)$v['free'] * (float)$prices['price'];
                                    $usdtBalance += $valuation;
                                    @self::updateQuantifyAccountDetails($accountInfo['id'], $v['asset'], (float)$v['free'], $valuation, (float)$prices['price']);

                                    //开始写入每个交易对交易明细数据
                                    $maxTradeId = self::getBinanceAccountTradeDetailsMaxTradeId($accountInfo['id'], $v['asset'].'USDT');
                                    if($maxTradeId) {
                                        $tradesList = $exchange->fetch_my_trades($v['asset'].'USDT', null, null, ['fromId' => $maxTradeId]);
                                    } else {
                                        $tradesList = $exchange->fetch_my_trades($v['asset'].'USDT');
                                    }
                                    // p($tradesList);
                                    $setAccountTradeDetailsRes = self::setBinanceAccountTradeDetails($accountInfo['id'], $v['asset'], $tradesList, $maxTradeId);
                                }
                            }
                        }
                    }
                }
                // self::commit();
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
                // self::rollback();
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
        // $vendor_name = "ccxt.ccxt";
        // Vendor($vendor_name);
        // $className = "\ccxt\\okex5";
        // $exchange  = new $className(array( //子账户
        //     'apiKey' => $accountInfo['api_key'],
        //     'secret' => $accountInfo['secret_key'],
        //     'password' => $accountInfo['pass_phrase'],
        // ));
        try {
            // $tradesList = $exchange->fetch_my_trades('GMX-USDT', null, null, ['before' => '563842929979478016']);
            // p($tradesList);
            // $balanceDetails = $exchange->fetch_account_balance();
            // p($balance);
            $url = Config('okx_uri') . "/api/okex/get_account_balances";
            $balanceDetails = self::getOkxRequesInfo($accountInfo, $url);
            $btcBalance = 0;
            $usdtBalance = 0;
            if(empty($balanceDetails['details']) || count($balanceDetails) <= 0) {
                echo "【" . $accountInfo['id'] . "】没有余额\r\n";
                return;
            }
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['ccy'])) {
                    if($v['ccy'] == 'USDT' || $v['ccy'] == 'BIFI' || $v['ccy'] == 'GMX' || $v['ccy'] == 'BTC' || $v['ccy'] == 'ETH' || $v['ccy'] == 'SAND') {
                        if((float)$v['cashBal'] >= 0) {
                            // $prices = $exchange->fetch_ticker($v['ccy'].'USDT'); //获取交易BTC价格
                            // $valuation = (float)$v['eq'] * (float)$prices['price'];
                            $price = 1;
                            $currencyUsd = 1;
                            if($v['ccy'] !== 'USDT') {
                                $url = Config('okx_uri') . "/api/okex/get_market_ticker?instId=" . $v['ccy'].'-USDT';
                                $prices = self::getOkxRequesInfo($accountInfo, $url);
                                // $prices = $exchange->fetch_ticker($v['ccy'].'-USDT'); //获取交易BTC价格
                                $price = $prices['last'];
                                $currencyUsd = (float)$v['cashBal'] * $price;
                                $usdtBalance += (float)$currencyUsd;
                            } else {
                                $currencyUsd =  (float)$v['eqUsd'];
                                $usdtBalance += (float)$currencyUsd;
                            }
                            @self::updateQuantifyAccountDetails($accountInfo['id'], $v['ccy'], (float)$v['cashBal'], $currencyUsd, $price);

                            //开始写入每个交易对交易明细数据
                            if($v['ccy'] !== 'USDT') {
                                $maxBillId = self::getOkxAccountTradeDetailsMaxTradeId($accountInfo['id'], $v['ccy'].'-USDT');
                                // p($maxBillId);
                                if($maxBillId) {
                                    $url = Config('okx_uri') . "/api/okex/get_fills_history?instType=SPOT&instId=" . $v['ccy'].'-USDT' . '&before=' . $maxBillId;
                                    $tradesList = self::getOkxRequesInfo($accountInfo, $url, true);
                                    // $tradesList = $exchange->fetch_my_trades($v['ccy'].'-USDT', null, null, ['before' => $maxBillId]);
                                } else {
                                    $url = Config('okx_uri') . "/api/okex/get_fills_history?instType=SPOT&instId=" . $v['ccy'].'-USDT' . '&before=';
                                    $tradesList = self::getOkxRequesInfo($accountInfo, $url, true);
                                    // $tradesList = $exchange->fetch_my_trades($v['ccy'].'-USDT');
                                }
                                // p($tradesList);
                                $setAccountTradeDetailsRes = self::setOkxAccountTradeDetails($accountInfo['id'], $v['ccy'], $tradesList, $maxBillId);
                            }
                        }
                    }
                }
            }
            //获取GMXUSDT持仓信息
            // if($accountInfo['id'] == 7 || $accountInfo['id'] == 9) {
            //     $positionsList = $exchange->fetch_positions('GMX-USDT', ['type' => 'SWAP']);
            //     if($positionsList) {
            //         // @self::updateQuantifyAccountPositionsDetails($accountInfo['id'], 'GMX', $positionsList[0]['info'], $exchange);
            //         @self::updateQuantifyAccountPositionsDetailsAll($accountInfo['id'], 'GMX', $positionsList, $exchange);
            //     }
            // }
            if($accountInfo['is_position'] == 1) {
                $url = Config('okx_uri') . "/api/okex/get_positions?instType=SWAP&instId=BTC-USDT-SWAP";
                $positionsList = self::getOkxRequesInfo($accountInfo, $url, true);
                if($positionsList) {
                    // @self::updateQuantifyAccountPositionsDetails($accountInfo['id'], 'GMX', $positionsList[0]['info'], $exchange);
                    self::updateQuantifyAccountPositionsDetailsAll($accountInfo['id'], 'BTC', $positionsList);
                }
            }
            $returnArray = ['usdtBalance' => $usdtBalance];
            return $returnArray;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ], JSON_UNESCAPED_UNICODE);
                // self::rollback();
                echo $error_msg . "\r\n";
                return false;
        }
    }

    /**
     * 获取OKX账户余额信息
     * @param array $accountInfo OKX账户信息
     * @param string $url 请求URL
     * @return bool|array 返回false表示失败，否则返回账户余额信息数组
     * @author qinlh
     * @since 2023-04-23
     */
    public static function getOkxRequesInfo($accountInfo, $url, $isList=false) {
        $params = [
            "api_key" => $accountInfo['api_key'],
            "secret_key" => $accountInfo['secret_key'],
            "passphrase" => $accountInfo['pass_phrase'],
        ];
        $response_string = RequestService::doJsonCurlPost($url, json_encode($params));
        $response_arr = json_decode($response_string, true);
        // p($response_arr);
        if($response_arr && $response_arr['status'] === 'success') {
            if(!$isList) {
                return $response_arr['data'][0];
            } else {
                return $response_arr['data'];
            }
        }
        return false;
    }

    /**
     * 更新账户币种持仓信息
     * @author qinlh
     * @since 2023-04-23
     */
    public static function updateQuantifyAccountPositionsDetails($account_id=0, $currency='', $info = [], $exchange=null) {
        if($account_id && $currency) {
            $date = date('Y-m-d');
            $res = self::name('quantify_account_positions')->where(['account_id' => $account_id, 'currency' => $currency, 'date' => $date])->find();
            // if((float)$info['uplRatio'] > (float)$res['max_upl_rate']) {
                //     $max_upl_rate = (float)$info['uplRatio'];
                // } 
                // if((float)$info['uplRatio'] < (float)$res['min_upl_rate']) {
                    //     $min_upl_rate = (float)$info['uplRatio'];
                    // }
            if($res && count((array)$res) > 0) {
                $last_pos_side = $res['pos_side'];
                $max_upl_rate = $res['max_upl_rate'];
                $min_upl_rate = $res['min_upl_rate'];
                if($last_pos_side === $info['posSide']) { //如果方向没有变的话
                    $setRateRes = self::setYieldHistoryList($account_id, $currency, $info['posSide'], $info['uplRatio'], $info['tradeId'], $info['uTime'], $info['cTime'], $info['markPx'], $info['avgPx'], $info['posId'], $info['upl']);
                } else { //方向改变的话
                    $positionsHistoryList = $exchange->fetch_positions_history('GMX-USDT', ['type' => 'SWAP', 'before' => $res['u_time']]);
                    $count = count((array)$positionsHistoryList);
                    if($count > 0) {
                        $insertData = $positionsHistoryList[$count - 1];
                        $setRateRes01 = self::setYieldHistoryList($account_id, $currency, $insertData['direction'], $insertData['pnlRatio'], $res['trade_id'], $insertData['uTime'], $insertData['cTime'], $insertData['closeAvgPx'], $insertData['openAvgPx'], $insertData['posId'], $insertData['upl']);
                    } else {
                        $setRateRes01 = true;
                    }
                    if($setRateRes01) {
                        $setRateRes = self::setYieldHistoryList($account_id, $currency, $info['posSide'], $info['uplRatio'],  $info['tradeId'], $info['uTime'], $info['cTime'], $info['markPx'], $info['avgPx'], $info['posId'], $info['upl']);
                    }
                }
                // $rate_average = ($max_upl_rate + $min_upl_rate) / 2;
                if($setRateRes) {
                    $max_main_upl_arr = self::getPosIdYieldHistory($account_id, $currency, $info['tradeId']);
                    $max_upl_rate = (float)$max_main_upl_arr['max_rate'];
                    $min_upl_rate = (float)$max_main_upl_arr['min_rate'];
                    $rate_average = ($max_upl_rate + $min_upl_rate) / 2;
                }
                $saveRes = self::name('quantify_account_positions')->where('id', $res['id'])->update([
                    'trade_id' => $info['tradeId'],
                    'mgn_mode' => $info['mgnMode'],
                    'pos_side' => $info['posSide'],
                    'pos' => $info['pos'],
                    'avg_px' => $info['avgPx'],
                    'mark_px' => $info['markPx'],
                    'margin_balance' => $info['mgnMode'] === 'cross' ? $info['imr'] : $info['margin'],
                    'margin_ratio' => $info['mgnRatio'],
                    'upl' => $info['upl'],
                    'upl_ratio' => $info['uplRatio'],
                    'max_upl_rate' => $max_upl_rate,
                    'min_upl_rate' => $min_upl_rate,
                    'rate_average' => $rate_average,
                    'u_time' => $info['uTime'],
                    'c_time' => $info['cTime'],
                    'time' => date('Y-m-d H:i:s')
                ]);
            } else {
                $res = self::name('quantify_account_positions')->where(['account_id' => $account_id, 'currency' => $currency])->order('id desc')->find(); //获取昨天最新的数据
                $last_pos_side = $res['pos_side'];
                $max_upl_rate = $res['max_upl_rate'];
                $min_upl_rate = $res['min_upl_rate'];
                if($last_pos_side === $info['posSide']) { //如果方向没有变的话
                    $setRateRes = self::setYieldHistoryList($account_id, $currency, $info['posSide'], $info['uplRatio'], $info['tradeId'], $info['uTime'], $info['cTime'], $info['markPx'], $info['avgPx'], $info['posId'], $info['upl']);
                } else { //方向改变的话
                    $positionsHistoryList = $exchange->fetch_positions_history('GMX-USDT', ['type' => 'SWAP', 'before' => $res['u_time']]);
                    $count = count((array)$positionsHistoryList);
                    if($count > 0) {
                        $insertData = $positionsHistoryList[$count - 1];
                        $setRateRes01 = self::setYieldHistoryList($account_id, $currency, $insertData['direction'], $insertData['pnlRatio'], $res['trade_id'], $insertData['uTime'], $insertData['cTime'], $insertData['closeAvgPx'], $insertData['openAvgPx'], $insertData['posId'], $insertData['upl']);
                    } else {
                        $setRateRes01 = true;
                    }
                    if($setRateRes01) {
                        $setRateRes = self::setYieldHistoryList($account_id, $currency, $info['posSide'], $info['uplRatio'], $info['tradeId'], $info['uTime'], $info['cTime'], $info['markPx'], $info['avgPx'], $info['posId'], $info['upl']);
                    }
                }
                if($setRateRes) {
                    $max_main_upl_arr = self::getPosIdYieldHistory($account_id, $currency, $info['tradeId']);
                    $max_upl_rate = (float)$max_main_upl_arr['max_rate'];
                    $min_upl_rate = (float)$max_main_upl_arr['min_rate'];
                    $rate_average = ($max_upl_rate + $min_upl_rate) / 2;
                }
                // if((float)$info['uplRatio'] > (float)$res['max_upl_rate']) {
                //     $max_upl_rate = (float)$info['uplRatio'];
                // } 
                // if((float)$info['uplRatio'] < (float)$res['min_upl_rate']) {
                //     $min_upl_rate = (float)$info['uplRatio'];
                // }
                $saveRes = self::name('quantify_account_positions')->insertGetId([
                    'account_id' => $account_id,
                    'currency' => $currency,
                    'trade_id' => $info['tradeId'],
                    'mgn_mode' => $info['mgnMode'],
                    'pos_side' => $info['posSide'],
                    'pos' => $info['pos'],
                    'avg_px' => $info['avgPx'],
                    'mark_px' => $info['markPx'],
                    'margin_balance' => $info['mgnMode'] === 'cross' ? $info['imr'] : $info['margin'],
                    'margin_ratio' => $info['mgnRatio'],
                    'upl' => $info['upl'],
                    'upl_ratio' => $info['uplRatio'],
                    'max_upl_rate' => $max_upl_rate,
                    'min_upl_rate' => $min_upl_rate,
                    'rate_average' => $rate_average,
                    'date' => $date,
                    'u_time' => $info['uTime'],
                    'c_time' => $info['cTime'],
                    'time' => date('Y-m-d H:i:s'),
                    'state' => 1,
                ]);
            }
            if($saveRes) {
                return true;
                // //判断持仓方向是否更换 如果更换统计最大收益率和最小收益率
                // if($last_pos_side === $info['posSide']) {
                //     $setRateRes = self::saveYieldHistoryList($max_upl_rate, $min_upl_rate);
                //     if($setRateRes) {
                //         return true;
                //     }
                // } else {
                //     $setRateRes = self::setYieldHistoryList($account_id, $currency, $info['posId'], $info['posSide'], $max_upl_rate, $min_upl_rate);
                //     if($setRateRes) {
                //         return true;
                //     }
                // }
            }
        }
        return false;
    }

    /**
     * 更新账户币种持仓信息 - 支持多条仓位
     * @author qinlh
     * @since 2023-05-05
     */
    public static function updateQuantifyAccountPositionsDetailsAll($account_id=0, $currency='', $infos = [], $exchange=null) {
        if($account_id && $currency) {
            $date = date('Y-m-d');
            if($infos && count((array)$infos) > 0) {
                //监听是否有平仓
                // self::getPositionsClosedPosition($account_id, $currency, $exchange);
                foreach ($infos as $key => $val) {
                    // $element = $val['info'];
                    $element = $val;
                    $setRateRes = self::setYieldHistoryList($account_id, $currency, $element['posSide'], $element['uplRatio'], $element['tradeId'], $element['uTime'], $element['cTime'], '', $element['avgPx'], $element['markPx'], $element['posId'], $element['upl']);
                    if($setRateRes) {
                        $positionsRes = self::name('quantify_account_positions')->where(['account_id' => $account_id, 'currency' => $currency, 'trade_id' => $element['tradeId']])->find();
                        if($positionsRes && count((array)$positionsRes) > 0) {
                            // $last_pos_side = $positionsRes['pos_side'];
                            $max_upl_rate = $positionsRes['max_upl_rate'];
                            $min_upl_rate = $positionsRes['min_upl_rate'];
                            $max_main_upl_arr = self::getPosIdYieldHistory($account_id, $currency, $element['tradeId']);
                            if($max_main_upl_arr && count($max_main_upl_arr) > 0) {
                                $max_upl_rate = (float)$max_main_upl_arr['max_rate'];
                                $min_upl_rate = (float)$max_main_upl_arr['min_rate'];
                            }
                            $rate_average = ($max_upl_rate + $min_upl_rate) / 2;
                            $savePositionsRes = self::name('quantify_account_positions')->where('id', $positionsRes['id'])->update([
                                'mgn_mode' => $element['mgnMode'],
                                'pos_side' => $element['posSide'],
                                'pos' => $element['pos'],
                                'avg_px' => $element['avgPx'],
                                'mark_px' => $element['markPx'],
                                'margin_balance' => $element['mgnMode'] === 'cross' ? $element['imr'] : $element['margin'],
                                'margin_ratio' => $element['mgnRatio'],
                                'upl' => $element['upl'] ? $element['upl'] : 0,
                                'upl_ratio' => $element['uplRatio'],
                                'max_upl_rate' => $max_upl_rate,
                                'min_upl_rate' => $min_upl_rate,
                                'rate_average' => $rate_average,
                                'u_time' => $element['uTime'],
                                'c_time' => $element['cTime'],
                                'time' => date('Y-m-d H:i:s')
                            ]);
                        } else {
                            $savePositionsRes = self::name('quantify_account_positions')->insertGetId([
                                'account_id' => $account_id,
                                'currency' => $currency,
                                'trade_id' => $element['tradeId'],
                                'mgn_mode' => $element['mgnMode'],
                                'pos_side' => $element['posSide'],
                                'pos' => $element['pos'],
                                'avg_px' => $element['avgPx'],
                                'mark_px' => $element['markPx'],
                                'margin_balance' => $element['mgnMode'] === 'cross' ? $element['imr'] : $element['margin'],
                                'margin_ratio' => $element['mgnRatio'],
                                'upl' => $element['upl'] ? $element['upl'] : 0,
                                'upl_ratio' => $element['uplRatio'],
                                'max_upl_rate' => $element['uplRatio'],
                                'min_upl_rate' => $element['uplRatio'],
                                'rate_average' => $element['uplRatio'],
                                'date' => $date,
                                'u_time' => $element['uTime'],
                                'c_time' => $element['cTime'],
                                'time' => date('Y-m-d H:i:s'),
                                'state' => 1,
                                'type' => 1,
                            ]);
                        }
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * 获取仓位未平仓数据 检测历史持仓是否平仓
     * @author qinlh
     * @since 2023-05-05
     */
    public static function getPositionsClosedPosition($account_id=7, $currency='GMX') {
        $accountInfo = self::getAccountInfo($account_id);
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => $accountInfo['api_key'],
            'secret' => $accountInfo['secret_key'],
            'password' => $accountInfo['pass_phrase'],
        ));
        if($account_id && $currency) {
            $data = self::name('quantify_account_positions')->where(['account_id' => $account_id, 'type' => 1])->select();
            if($data && count((array)$data) > 0) {
                foreach ($data as $key => $val) {
                    $positionsHistoryList = $exchange->fetch_positions_history('GMX-USDT', ['type' => 'SWAP', 'before' => $val['u_time']]);
                    foreach ($positionsHistoryList as $k => $v) {
                        if($val['c_time'] == $v['cTime']) { //已平仓
                            $saveTypeRes = self::name('quantify_account_positions')->where('id', $val['id'])->setField('type', 2);
                            if($saveTypeRes) {
                                $make_price = 0;
                                if($v['triggerPx']) {
                                    $make_price = $v['triggerPx'];
                                } else {
                                    $make_price = $val['mark_px'];
                                }
                                self::setYieldHistoryList($account_id, $currency, $v['direction'], $v['pnlRatio'], $val['trade_id'], $v['uTime'], $v['cTime'], $v['closeAvgPx'], $v['openAvgPx'], $make_price, $v['posId'], $v['pnl']);
                            }
                        }
                    }
                    sleep(15);
                }
            }
            return;
        }
    }

    /**
     * 持仓信息
     * 记录最大最小收益率记录
     * @author qinlh
     * @since 2023-05-03
     */
    public static function setYieldHistoryList($account_id=0, $currency='', $pos_side='', $uplRatio=0, $trade_id='', $u_time='', $c_time='', $avg_price=0, $opening_price=0, $mark_price=0, $pos_id=0, $upl=0) {
        if($account_id && $currency) {
            // $max_upl_rate = self::name('quantify_account_positions')->where(['account_id' => $account_id, 'currency' => $currency])->max('upl_ratio');
            // $min_upl_rate = self::name('quantify_account_positions')->where(['account_id' => $account_id, 'currency' => $currency])->min('upl_ratio');
            // $rate_average = ($max_upl_rate + $min_upl_rate) / 2;
            $insertId = self::name('quantify_account_positions_rate')->insertGetId([
                'account_id' => $account_id,
                'currency' => $currency,
                'pos_side' => $pos_side,
                'upl' => $upl,
                'rate_num' => $uplRatio,
                'avg_price' => $avg_price,
                'opening_price' => $opening_price,
                'mark_price' => $mark_price,
                'trade_id' => $trade_id,
                'pos_id' => $pos_id,
                'u_time' => $u_time,
                'c_time' => $c_time,
                'time' => date('Y-m-d H:i:s')
            ]);
            if($insertId) {
                return true;
            }
        }
        return false;
    }

    /**
     * 持仓信息
     * 更新最大最小收益率记录
     * @author qinlh
     * @since 2023-05-03
     */
    // public static function saveYieldHistoryList($max_upl_rate=0, $min_upl_rate=0) {
    //     if($max_upl_rate && $min_upl_rate) {
    //         $res = self::name('quantify_account_positions_rate')->order('id desc')->find(); //获取昨天最新的数据
    //         $rate_average = ($max_upl_rate + $min_upl_rate) / 2;
    //         $updateRes = self::name('quantify_account_positions_rate')->where('id', $res['id'])->update([
    //             'max_rate' => $max_upl_rate,
    //             'min_rate' => $min_upl_rate,
    //             'rate_average' => $rate_average,
    //             'time' => date('Y-m-d H:i:s')
    //         ]);
    //         if($updateRes !== false) {
    //             return true;
    //         }
    //     }
    //     return false;
    // }

    /**
     * 获取持仓id下最大最小收益率记录
     * @author qinlh
     * @since 2023-05-04
     */
    public static function getPosIdYieldHistory($account_id=0, $currency='', $trade_id=0) {
        if($trade_id) {
            $max_rate = self::name('quantify_account_positions_rate')->where(['account_id' => $account_id, 'currency' => $currency, 'trade_id' => $trade_id])->max('rate_num');
            $min_rate = self::name('quantify_account_positions_rate')->where(['account_id' => $account_id, 'currency' => $currency, 'trade_id' => $trade_id])->min('rate_num');
            if($max_rate && $min_rate) {
                return ['max_rate' => $max_rate, 'min_rate' => $min_rate];
            }
        }
    }

    /**
     * 持仓信息
     * 获取最新的最大最小收益率
     * @author qinlh
     * @since 2023-05-03
     */
    public static function getNewPositionsRate($account_id=0, $currency='') {
        if($account_id && $currency) {
            $data = self::name('quantify_account_positions_rate')->where(['account_id' => $account_id, 'currency' => $currency])->order('id desc')->find();
            if($data) {
                return $data->toArray();
            }
        }
        return [];
    }

    /**
     * 更新账户币种余额明细
     * @author qinlh
     * @since 2023-02-23
     */
    public static function updateQuantifyAccountDetails($account_id=0, $currency='', $balance=0, $valuation=0, $price=0) {
        if($account_id && $currency) {
            $date = date('Y-m-d');
            $res = self::name('quantify_account_details')->where(['account_id' => $account_id, 'currency' => $currency, 'date' => $date])->find();
            if($res && count((array)$res) > 0) {
                $saveRes = self::name('quantify_account_details')->where('id', $res['id'])->update([
                    'balance' => $balance,
                    'valuation' => $valuation,
                    'price' => $price,
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
                    'price' => $price,
                    'date' => $date,
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
            $amount = self::name('quantify_inout_gold')->where(['account_id' => $account_id])->whereTime('time', 'between', [$start_time, $end_time])->sum('amount');
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
    public static function getQuantifyAccountDetails($where, $page, $limits=0) {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("quantify_account_details")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("quantify_account_details")
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
     * Binance
     * 获取账户币种交易明细
     * @author qinlh
     * @since 2023-04-04
     */
    public static function setBinanceAccountTradeDetails($account_id=0, $currency='', $list=[], $maxTradeId=0) {
        if($account_id && $currency && count((array)$list) > 0) {
            $insertDataAll = [];
            $infoArr = [];
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
                    'quote_total_price' => (float)$infoArr['qty'] * (float)$infoArr['price'],
                    'side' => $infoArr['isBuyer'] ? 'buy' : 'sell',
                    'trade_time' => date('Y-m-d H:i:s', $infoArr['time']/1000),
                    'time' => date('Y-m-d H:i:s')
                ];
            }
            $res = self::name('quantify_account_trade_binance_details')->insertAll($insertDataAll);
            if($res) {
                return true;
            }
        }
        return true;
    }
    
    /**
     * Okx
     * 获取账户币种交易明细
     * @author qinlh
     * @since 2023-04-04
     */
    public static function setOkxAccountTradeDetails($account_id=0, $currency='', $list=[], $maxTradeId=0) {
        if($account_id && $currency && count((array)$list) > 0) {
            $insertDataAll = [];
            $infoArr = [];
            foreach ($list as $key => $val) {
                if($maxTradeId > 0 && $key == 0) {
                    continue;
                }
                $infoArr = $val;
                $insertDataAll[] = [
                    'account_id' => $account_id,
                    'currency' => $currency,
                    'symbol' => $infoArr['instId'],
                    'trade_id' => $infoArr['tradeId'],
                    'order_id' => $infoArr['ordId'],
                    'price' => $infoArr['fillPx'],
                    'qty' => $infoArr['fillSz'],
                    'quote_total_price' => (float)$infoArr['fillSz'] * (float)$infoArr['fillPx'],
                    'quote_qty' => '',
                    'side' => $infoArr['side'],
                    'bill_id' => $infoArr['billId'],
                    'trade_time' => date('Y-m-d H:i:s', $infoArr['fillTime']/1000),
                    'time' => date('Y-m-d H:i:s')
                ];
            }
            $res = self::name('quantify_account_trade_okx_details')->insertAll($insertDataAll);
            if($res) {
                return true;
            }
        }
        return true;
    }

    /**
     * BINANCE
     * 获取账户币种交易明细最大Trade ID
     * @author qinlh
     * @since 2023-04-04
     */
    public static function getBinanceAccountTradeDetailsMaxTradeId($account_id=0, $symbol='') {
        if($account_id && $symbol !== '') {
            $sql = "SELECT MAX(trade_id) AS max_trade_id FROM s_quantify_account_trade_binance_details WHERE account_id = '$account_id' AND symbol = '$symbol'";
            $res = self::query($sql);
            if($res && count((array)$res) > 0) {
                return $res[0]['max_trade_id'];
            }
        }
        return 0;
    }

    /**
     * OKX
     * 获取账户币种交易明细最大Trade ID
     * @author qinlh
     * @since 2023-04-04
     */
    public static function getOkxAccountTradeDetailsMaxTradeId($account_id=0, $symbol='') {
        if($account_id && $symbol !== '') {
            $sql = "SELECT MAX(bill_id) AS max_bill_id FROM s_quantify_account_trade_okx_details WHERE account_id = '$account_id' AND symbol = '$symbol'";
            $res = self::query($sql);
            if($res && count((array)$res) > 0) {
                return $res[0]['max_bill_id'];
            }
        }
        return 0;
    }

    /**
     * 获取币种交易明细列表数据
     * @author qinlh
     * @since 2023-04-05
     */
    public static function getAccountCurrencyDetailsList($account_id=0, $where, $page, $limit) {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $accountInfo = self::getAccountInfo($account_id);
        $table = '';
        if($accountInfo['type'] == 1) {
            $table = 'quantify_account_trade_binance_details';
            $order = 'trade_id desc';
        } else {
            $table = 'quantify_account_trade_okx_details';
            $order = 'bill_id desc';
        }
        $count = self::name($table)->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::name($table)
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }



    /**
     * 记录分润记录
     * @author qinlh
     * @since 2023-01-31
     */
    public static function setDividendRecord($account_id=0, $amount='', $remark='')
    {
        if ($account_id && $amount !== 0) {
            $total_profit = self::getTotalProfitBalance($account_id) + (float)$amount;
            $insertData = [
                'account_id' => $account_id,
                'amount' => $amount,
                'total_profit' => $total_profit,
                'remark' => $remark,
                'time' => date('Y-m-d H:i:s'),
            ];
            $res = self::name('quantify_dividend_record')->insertGetId($insertData);
            if ($res) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取总分润
     * @author qinlh
     * @since 2023-04-17
     */
    public static function getTotalProfitBalance($account_id) {
        if($account_id) {
            $count = self::name('quantify_dividend_record')->where('account_id', $account_id)->sum('amount');
            if ($count !== 0) {
                return $count;
            }
        }
        return 0;
    }

    /**
    * 获取分润记录
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getDividendRecordList($where, $page, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("quantify_dividend_record")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("quantify_dividend_record")
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
     * 获取今天的分润
     * @author qinlh
     * @since 2023-04-17
     */
    public static function getDayProfit($account_id=0, $date='') {
        if($account_id) {
            $start_time = $date . "00:00:00";
            $end_time = $date . "23:59:59";
            $amount = self::name('quantify_dividend_record')->where('account_id', $account_id)->whereTime('time', 'between', [$start_time, $end_time])->sum('amount');
            if ($amount !== 0) {
                return $amount;
            }
        }
        return 0;
    }

    /**
     * 获取账户余额币种列表
     * @author qinlh
     * @since 2023-04-23
     */
    public static function getQuantifyAccountCurrencyList($account_id=0) {
        if($account_id) {
            $data = self::name('quantify_account_details')->where(['account_id' => $account_id, 'balance' => ['>', 0]])->group('currency')->field('currency')->select();
            if($data && count((array)$data) > 0) {
                $newArray = array_column($data->toArray(), 'currency');
                return $newArray;
            }
        }
        return [];
    }

    /**
     * 获取币种持仓信息
     * @author qinlh
     * @since 2023-04-23
     */
    public static function getAccountCurrencyPositionsList($where, $page, $limits=0) {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("quantify_account_positions")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("quantify_account_positions")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // foreach ($lists as $key => $val) {
        //     $maxMinRateArr = self::getNewPositionsRate($val['account_id'], $val['currency']);
        //     $lists[$key]['max_upl_rate'] = 0;
        //     $lists[$key]['min_upl_rate'] = 0;
        //     if($maxMinRateArr && count((array)$maxMinRateArr) > 0) {
        //         $lists[$key]['max_upl_rate'] = $maxMinRateArr['max_rate'];
        //         $lists[$key]['min_upl_rate'] = $maxMinRateArr['min_rate'];
        //     }
        // }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 获取币种持仓信息
     * 获取仓位下最大最小收益率数据
     * @author qinlh
     * @since 2023-04-23
     */
    public static function getMaxMinUplRateData($account_id=0, $currency='', $page, $limits=0) {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // // p($where);
        // $count = self::name("quantify_account_positions_rate")
        //             ->where($where)
        //             ->count();//计算总页面
        // // p($count);
        // $allpage = intval(ceil($count / $limits));
        // $lists = self::name("quantify_account_positions_rate")
        //             ->where($where)
        //             ->page($page, $limits)
        //             ->field('*')
        //             ->order("id desc")
        //             ->select()
        //             ->toArray();
        $begin = ($page - 1) * $limits;
        $count_sql = "SELECT `account_id`,`currency`,`trade_id`,max(`rate_num`) AS max_rate, min(`rate_num`) AS min_rate, max(`mark_price`) AS max_make_price, min(NULLIF(IF(mark_price = 0, NULL, mark_price), 0)) AS min_make_price, `time`, `pos_side` FROM s_quantify_account_positions_rate WHERE `account_id` = {$account_id} GROUP BY `trade_id`";
        $countRes = self::query($count_sql);
        $count = count((array)$countRes);
        $allpage = intval(ceil($count / $limits));
        $sql = "SELECT `account_id`,`currency`,`trade_id`,max(`rate_num`) AS max_rate, min(`rate_num`) AS min_rate, max(`mark_price`) AS max_make_price, min(NULLIF(IF(mark_price = 0, NULL, mark_price), 0)) AS min_make_price, `time`, `pos_side` FROM s_quantify_account_positions_rate WHERE `account_id` = {$account_id} GROUP BY `trade_id` ORDER BY `time` DESC LIMIT {$begin},{$limits}";
        $lists = self::query($sql);
        foreach ($lists as $key => $val) {
            $lists[$key]['rate_average'] = ($val['max_rate'] + $val['min_rate']) / 2;
            $closingYieldRes = self::name('quantify_account_positions_rate')->where('trade_id', $val['trade_id'])->order('time desc')->find();
            $lists[$key]['closing_yield'] = $closingYieldRes['rate_num'];
            $lists[$key]['avg_price'] = $closingYieldRes['avg_price'];
            $lists[$key]['opening_price'] = $closingYieldRes['opening_price'];
            $lists[$key]['upl'] = $closingYieldRes['upl'];
            $lists[$key]['u_time'] = date('Y-m-d H:i:s', (float)$closingYieldRes['u_time'] / 1000);
            $lists[$key]['c_time'] = date('Y-m-d H:i:s', (float)$closingYieldRes['c_time'] / 1000);
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 添加账户数据
     * @since 2024-10-14
     */
    public static function addQuantityAccount($name, $api_key, $secret_key, $pass_phrase, $type, $is_position) {
        try {
            $IsResData = self::where('name', $name)->find();
            if($IsResData && count((array)$IsResData) > 0) {
                $updateData = [
                    'api_key' => $api_key,
                    'secret_key' => $secret_key,
                    'pass_phrase' => $pass_phrase,
                    'state' => 1,
                    'external' => $IsResData['external'],
                    'type' => $type,
                    'time' => date('Y-m-d H:i:s'),
                    'is_position' => $is_position
                ];
                $res = self::where('id', $IsResData['id'])->update($updateData);
                if($res) {
                    return true;
                }
            } else {
                $insertData = [
                    'name' => $name,
                    'api_key' => $api_key,
                    'secret_key' => $secret_key,
                    'pass_phrase' => $pass_phrase,
                    'state' => 1,
                    'external' => 0,
                    'type' => $type,
                    'time' => date('Y-m-d H:i:s'),
                    'is_position' => $is_position
                ];
                self::insert($insertData);
                $insertId = self::getLastInsID();
                if($insertId) {
                    return true;
                }
            }
            return false;
        } catch ( PDOException $e) {
            return false;
        }
    }
}