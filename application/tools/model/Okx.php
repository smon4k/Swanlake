<?php
// +----------------------------------------------------------------------
// | 文件说明：Okx 交易 业务逻辑 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-15
// +----------------------------------------------------------------------
namespace app\tools\model;

use think\Model;
use RequestService\RequestService;
use hbdm\hbdm;
use okex\okv5;

class Okx extends Base
{
    /**
     * 下单
     * @author qinlh
     * @since 2022-06-29
     */
    public static function tradeOrder() {
        // $vendor_name = "ccxt.ccxt";
        // Vendor($vendor_name);

        // $className = "\ccxt\\okex5";
        // $exchange  = new $className(array(
        //     'apiKey' => 'a5e6eaf0-59ad-4fd6-abe1-7b48c18ed1a8',
        //     'secret' => '91C03B9A3F134598FB3BA801A248D9AF',
        //     'password' => 'Zx112211@',
        // ));
        // try {  
        //     $result = $exchange->create_order('BTC/USD', 'market', 'sell', 1, 1);
        //     p($result);
        // } catch (\Exception $e) {
        //     return array(0, $e->getMessage());
        // }

        $rpc = new okv5(array(
            'apiKey' => 'a5e6eaf0-59ad-4fd6-abe1-7b48c18ed1a8',
            'apiSecret' => '91C03B9A3F134598FB3BA801A248D9AF',
            'passphrase' => 'Zx112211@',
        ));
        $params = [
            'instId' => 'BTC-USDT',
            'clOrdId' => '202208151011007',
            'tdMode' => 'cash',
            'side' => 'buy',
            'ordType' => 'market',
            'sz' => '100',
        ];
        try {  
            $result = $rpc->request('/api/v5/trade/order', $params, 'POST');
            var_dump($result);
        } catch (\Exception $e) {
            return array(0, $e->getMessage());
        }
    }
    
}