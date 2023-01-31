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
     * 计算量化账户数据
     * @author qinlh
     * @since 2023-01-31
     */
    public static function calcQuantifyAccountData() {
        try {
            $accountList = self::getAccountList();
            $account_id = 0;
            foreach ($accountList as $key => $val) {
                $account_id = $val['id'];
                $accountInfo = self::getAccountInfo($account_id);
                $balanceList = self::getTradePairBalance();
                $totalBalance = $balanceList['usdtBalance']; //总资产
                $daily = 0; //日增
                $daily_rate = 0; //日增率
                $yestDetails = [];
                $yestDetailsRes = self::name('quantify_equity_monitoring')->whereTime('date', 'yesterday')->find(); //获取昨天的数据
                if($yestDetailsRes && count((array)$yestDetailsRes) > 0) {
                    $yestDetails = $yestDetailsRes->toArray();
                }
                if ($yestDetails && count((array)$yestDetails) > 0) {
                    $daily = (float)$usdtBalance - (float)$yestDetails['balance'];
                    $daily_rate = (float)$yestDetails['balance'] > 0 ? ($daily / (float)$yestDetails['balance']) * 100 : 0;
                }
                // p($daily);
                date_default_timezone_set("Etc/GMT-8");
                $date = date('Y-m-d');
                $data = Db::name('quantify_equity_monitoring')->where(['account_id' => $account_id, 'date' => $date])->find();
                if($data && count((array)$data) > 0) {
                    $upData = [
                        'count_market_value'=>$totalBalance, 
                        // 'grid_spread' => $countProfit,
                        // 'grid_spread_rate' => $countProfitRate,
                        // 'grid_day_spread' => $dayProfit,
                        // 'grid_day_spread_rate' => $dayProfitRate,
                        // 'average_day_rate' => $averageDayRate,
                        // 'average_year_rate' => $averageYearRate,
                        // 'up_time' => date('Y-m-d H:i:s')
                    ];
                    $res = Db::name('quantify_equity_monitoring')->where(['product_name' => $transactionCurrency, 'date' => $date])->update($upData);
                } else {
                    $insertData = [
                        'account_id' => $account_id, 
                        'date'=>$date, 
                        'count_market_value'=>$totalBalance, 
                        // 'grid_spread' => $countProfit,
                        // 'grid_spread_rate' => $countProfitRate,
                        // 'grid_day_spread' => $dayProfit,
                        // 'grid_day_spread_rate' => $dayProfitRate,
                        // 'average_day_rate' => $averageDayRate,
                        // 'average_year_rate' => $averageYearRate,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $res = Db::name('quantify_equity_monitoring')->insertGetId($insertData);
                }
                if($res !== false) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            return false;
        }
    }

     /**
     * 获取余额
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradePairBalance() {
        // $assetArr = explode('-', $transactionCurrency);
        // $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
        // $dataJson = self::getCache($cache_params);
        // $data = json_decode($dataJson, true);
        // if($data && count((array)$data) > 0) {
        //     return $data;
        // }
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $balanceDetails = $exchange->fetch_balance([]);
            // p($balanceDetails);
            $usdtBalance = 0;
            if(isset($balanceDetails['info']['balances'])) {
                foreach ($balanceDetails['info']['balances'] as $k => $v) {
                    if(isset($v['asset'])) {
                        if($v['asset'] == 'USDT') {
                            if((float)$v['free'] > 0) {
                                $usdtBalance += (float)$v['free'];
                            }
                        }
                        if($v['asset'] == 'BIFI' || $v['asset'] == 'GMX') {
                            if((float)$v['free'] > 0) {
                                $prices = $exchange->fetch_ticker_price($v['asset'] . 'USDT');
                                $usdtBalance += (float)$v['free'] * (float)$prices['price'];
                            }
                        }
                    }
                }
            }
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
            echo $error_msg . "\r\n";
            return false;
        }
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
    public static function getAccountList() {
        $data = self::name('quantify_account')->where('state', 1)->find();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }
}