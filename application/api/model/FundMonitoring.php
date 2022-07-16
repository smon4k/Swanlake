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
            $countBTCBalance = 0;
            $countUSDTBalance = 0;
            if($result && $result['status'] === 'ok') { 
                foreach ($result['data'] as $vkey => $val) {
                    if(isset($val['currency']) ) {
                        if($val['currency'] === 'btc' && $val['balance'] > 0) {
                            $countBTCBalance += $val['balance'];
                        }
                        if($val['currency'] === 'usdt' && $val['balance'] > 0) {
                            $countUSDTBalance += $val['balance'];
                        }
                    } 
                }
                $result = ['huobi_btc_balance' => $countBTCBalance, 'huobi_usdt_balance' => $countUSDTBalance, 'time' => date('Y-m-d H:i:s')];
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
            $countBTCBalance = 0;
            $countUSDTBalance = 0;
            // $subaccountBalancesDetails = $exchange->fetch_account_subaccount_balance(['subAcct'=>'smon4k03']);
            // p($subaccountBalancesDetails);
            if($result) {
                foreach ($result as $key => $val) {
                    $subaccountBalancesDetails = $exchange->fetch_account_subaccount_balance(['subAcct'=>$val['subAcct']]);
                    if($subaccountBalancesDetails && count((array)$subaccountBalancesDetails) > 0 && isset($subaccountBalancesDetails[0]['details'])) {
                        $btcBalance = 0;
                        $usdtBalance = 0;
                        foreach ($subaccountBalancesDetails[0]['details'] as $k => $v) {
                            if(isset($v['eq'])) {
                                if($v['ccy'] === 'BTC' || $v['ccy'] === 'USDT') {
                                    if($v['ccy'] === 'BTC' && (float)$v['eq'] > 0) {
                                        $btcBalance += (float)$v['eq'];
                                    }
                                    if($v['ccy'] === 'USDT' && (float)$v['eq'] > 0) {
                                        $usdtBalance += (float)$v['eq'];
                                    }
                                }
                            }
                        }
                        // echo $val['subAcct'] . "&" . $currencyBalance;
                        // echo "\r\n";
                        $countBTCBalance += $btcBalance;
                        $countUSDTBalance += $usdtBalance;
                    }
                    // p($subaccountBalances);
                }
                $result = ['okex_btc_balance' => $countBTCBalance, 'okex_usdt_balance' => $countUSDTBalance, 'time' => date('Y-m-d H:i:s')];
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
     * 获取今日数据
     * @author qinlh
     * @since 2022-07-16
     */
    public static function getDateDetails() {
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
            $insertData = [
                'okex_btc_balance' => 0,
                'okex_usdt_balance' => 0,
                'huobi_btc_balance' => 0,
                'huobi_usdt_balance' => 0,
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
}