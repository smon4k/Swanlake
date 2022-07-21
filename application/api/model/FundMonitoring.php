<?php
// +----------------------------------------------------------------------
// | 文件说明：获取资金监控 火币账户 or okex账户
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-07-16
// +----------------------------------------------------------------------
namespace app\api\model;

use think\Model;
use hbdm\hbdm;
use RequestService\RequestService;

class FundMonitoring extends Base
{
    /**
     * 获取火币账户余额
     * @author qinlh
     * @since 2022-06-29
     */
    public static function getHuobiAccountBalance() {
        try {  
            $rpc = new hbdm('a36c5b20-qv2d5ctgbn-cb3de46a-f3ce8', '3d2322fc-a5919d1d-18dc22e8-527e9');
            $result = $rpc->get_subuser_aggregate_balance();
            // p($result);
            // $countBTCBalance = 0;
            $countBalance = 0;
            if($result && $result['status'] === 'ok') { 
                foreach ($result['data'] as $vkey => $val) {
                    if(isset($val['currency']) ) {
                        // if($val['currency'] === 'btc' && $val['balance'] > 0) {
                        //     $countBTCBalance += $val['balance'];
                        // }
                        if($val['currency'] === 'usdt' && $val['balance'] > 0) {
                            $countBalance += $val['balance'];
                        }
                    } 
                }
                date_default_timezone_set("Etc/GMT-8");
                $result = ['huobi_balance' => $countBalance, 'time' => date('Y-m-d H:i:s')];
                // p($result);
                $res = self::updateDataDetail($result);
                if($res) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
            // return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取Okex账户余额
     * @author qinlh
     * @since 2022-06-29
     */
    public static function getOkexAccountBalance() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array(
            'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
            'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
            'password' => 'Zx112211@',
        ));

        try {  
            $result = $exchange->fetch_users_subaccount_list();
            // $result = $exchange->fetch_balance();
            // $countBTCBalance = 0;
            $countBalance = 0;
            // $subaccountBalancesDetails = $exchange->fetch_account_subaccount_balance(['subAcct'=>'smon4k03']);
            // p($subaccountBalancesDetails);
            // die;
            // p($subaccountBalancesDetails);
            if($result) {
                // p($result);
                foreach ($result as $key => $val) {
                    $subaccountBalancesDetails = $exchange->fetch_account_subaccount_balance(['subAcct'=>$val['subAcct']]);
                    if($subaccountBalancesDetails && count((array)$subaccountBalancesDetails) > 0) {
                        // $btcBalance = 0;
                        // $usdtBalance = 0;
                        if($subaccountBalancesDetails[0] && $subaccountBalancesDetails[0]['totalEq']) {
                            $countBalance = $subaccountBalancesDetails[0]['totalEq'];
                        }
                        // foreach ($subaccountBalancesDetails[0]['details'] as $k => $v) {
                        //     if(isset($v['eq'])) {
                        //         if($v['ccy'] == 'BTC' || $v['ccy'] == 'USDT') {
                        //             if($v['ccy'] == 'BTC' && (float)$v['eq'] > 0) {
                        //                 print_r($v);
                        //                 $btcBalance += (float)$v['eq'];
                        //             }
                        //             if($v['ccy'] == 'USDT' && (float)$v['eq'] > 0) {
                        //                 $usdtBalance += (float)$v['eq'];
                        //             }
                        //         }
                        //     }
                        // }
                        // echo $val['subAcct'] . "&" . $currencyBalance;
                        // echo "\r\n";
                        // $countBTCBalance += $btcBalance;
                        // $countUSDTBalance += $usdtBalance;
                    }
                    // p($countBTCBalance);
                }
                        // p($countBTCBalance);
                // p($countBalance);
                date_default_timezone_set("Etc/GMT-8");
                $result = ['okex_balance' => $countBalance, 'time' => date('Y-m-d H:i:s')];
                // p($result);
                $res = self::updateDataDetail($result);
                if($res) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            // return ['code' => 0, 'message' => $e->getMessage()];
            return false;
        }
    }

    /**
     * 获取Okex 火币账户余额
     * @author qinlh
     * @since 2022-06-29
     */
    public static function getAccountBalance() {
        try {  
            $rpc = new hbdm('a36c5b20-qv2d5ctgbn-cb3de46a-f3ce8', '3d2322fc-a5919d1d-18dc22e8-527e9');
            // $result = $rpc->get_account_accounts($uid);
            $result = $rpc->post_swap_balance_valuation();
            // $countBTCBalance = 0;
            $countHuobiBalance = 0;//火币账户余额
            $countOkexBalance = 0;//Okex账户余额
                // p($result);
            if($result && $result['status'] === 'ok') { 
                $countHuobiBalance = isset($result['data'][0]['balance']) &&  $result['data'][0]['balance'] !== 0 ?  $result['data'][0]['balance'] : 0; //获取账户余额
            }
            
            $vendor_name = "ccxt.ccxt";
            Vendor($vendor_name);
            $className = "\ccxt\\okex5";
            $exchange  = new $className(array(
                'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
                'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
                'password' => 'Zx112211@',
            ));
            $subaccountBalancesDetails = $exchange->fetch_account_subaccount_balance(['subAcct'=>'smon4k06']);
            if($subaccountBalancesDetails[0] && $subaccountBalancesDetails[0]['totalEq']) {
                $countOkexBalance = $subaccountBalancesDetails[0]['totalEq'];
            }
            return ['huobi_balance' => $countHuobiBalance, 'okex_balance' => $countOkexBalance];
        } catch (\Exception $e) {
            return false;
            // return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * 修改统计数据
     * @author qinlh
     * @since 2022-07-17
     */
    public static function saveStatisticsData() {
        self::startTrans();
        try {
            date_default_timezone_set("Etc/GMT-8");
            $date = date('Y-m-d');
            $details = self::where('date', $date)->find();
            $summary = (float)$details['okex_balance'] + (float)$details['huobi_balance']; //计算今日汇总数据
            $yestDetails = self::where('date', '<', $date)->order('date desc')->find()->toArray(); //获取昨天的数据
            $daily = 0; //日增
            $daily_rate = 0; //日增率
            if($yestDetails && count((array)$yestDetails) > 0) {
                $daily = (float)$summary - (float)$yestDetails['summary'];
                $daily_rate = (float)$yestDetails['summary'] > 0 ? ($daily / (float)$yestDetails['summary']) * 100 : 0;
                $res = self::updateDataDetail(['summary' => $summary, 'daily' => $daily, 'daily_rate' => $daily_rate, 'time' => date('Y-m-d H:i:s')]);
                if(false !== $res) {
                    self::commit();
                    return true;
                }
            } else {
                self::commit();
                return true;
            }
            self::rollback();
            return false;
        } catch (\Exception $e) {
            // p($e->getMessage());
            self::rollback();
            return false;
        }
    }

    /**
     * 获取今日数据
     * @author qinlh
     * @since 2022-07-16
     */
    public static function getDateDetails() {
        date_default_timezone_set("Etc/GMT-8");
        $date = date('Y-m-d');
        $details = self::where('date', $date)->find();
        if ($details && count((array)$details) > 0) {
            return $details->toArray();
        } else {
            // $userinfo = self::insertUserData($address, $invite_address);
            $details = self::insertMonitoringData();
            return $details;
        }
    }

     /**
     * 开始添加今日数据
     * @author qinlh
     * @since 2022-03-18
     */
    public static function insertMonitoringData()
    {
        self::startTrans();
        try {
            date_default_timezone_set("Etc/GMT-8");
            $insertData = [
                'okex_balance' => 0,
                'okex_balance' => 0,
                'summary' => 0,
                'daily' => 0,
                'daily_rate' => 0,
                'date' => date('Y-m-d'),
                'time' => date('Y-m-d H:i:s'),
            ];
            $userId = self::insertGetId($insertData);
            if($userId > 0) {
                self::commit();
                $insertData['id'] = $userId;
                return $insertData;
            }
            self::rollback();
            return false;
        } catch ( PDOException $e) {
            self::rollback();
            return false;
        }
    }

    /**
     * 更新数据
     * @author qinlh
     * @since 2022-03-18
     */
    public static function updateDataDetail($update=[])
    {
        self::startTrans();
        try {
            $res = self::getDateDetails();
            if($res) {
                date_default_timezone_set("Etc/GMT-8");
                $date = date('Y-m-d');
                $isUpRes = self::where('date', $date)->update($update);
                if(false !== $isUpRes) {
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
     * 获取资金监控数据
     * @author qinlh
     * @since 2022-07-16
     */
    public static function getFundMonitoringList($where, $page, $limit, $order='id desc')
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
        // $url = "https://www.h2ofinance.pro/getPoolBtc";
        // $poolBtc = 
        // $params = [];
        // $response_string = RequestService::doCurlGetRequest($url, $params);
        // $btc_currency_price = 0;
        // if($response_string && $response_string[0]) {
        //     $btc_currency_price = $response_string[0]['currency_price'] ? (float)$response_string[0]['currency_price'] : 1;
        // }
        // $newArray = [];
        // foreach ($lists as $key => $val) {
        //     $newArray[$key]['okex_balance'] = ((float)$val['okex_btc_balance'] * $btc_currency_price) + (float)$val['okex_usdt_balance'];
        //     $newArray[$key]['huobi_balance'] = ((float)$val['huobi_btc_balance'] * $btc_currency_price) + (float)$val['huobi_usdt_balance'];
        //     $newArray[$key]['date'] = $val['date'];
        //     $newArray[$key]['time'] = $val['time'];
        // }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
}