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
     * 获取余额
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradePairBalance($transactionCurrency='USDT') {
        $assetArr = explode('-', $transactionCurrency);
        $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
        $dataJson = self::getCache($cache_params);
        $data = json_decode($dataJson, true);
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
            p($balanceDetails);
            $busdBalance = 0;
            $bifiBalance = 0;
            if(isset($balanceDetails['info']['balances'])) {
                foreach ($balanceDetails['info']['balances'] as $k => $v) {
                    if(isset($v['asset'])) {
                        if($v['asset'] == $assetArr[1] || $v['asset'] == $assetArr[0]) {
                            if($v['asset'] == $assetArr[1] && (float)$v['free'] > 0) {
                                $busdBalance += (float)$v['free'];
                            }
                            if($v['asset'] == $assetArr[0] && (float)$v['free'] > 0) {
                                $bifiBalance += (float)$v['free'];
                            }
                        }
                    }
                }
            }
            $returnArray = ['busdBalance' => $busdBalance, 'bifiBalance' => $bifiBalance];
            $dataJson = json_encode($returnArray);
            self::setCache($cache_params, $dataJson);
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
}