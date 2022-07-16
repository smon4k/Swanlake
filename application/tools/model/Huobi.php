<?php
// +----------------------------------------------------------------------
// | 文件说明：火币 业务逻辑 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-06-29
// +----------------------------------------------------------------------
namespace app\tools\model;

use think\Model;
use RequestService\RequestService;
use hbdm\hbdm;

class Huobi extends Base
{
    /**
     * 获取账户余额
     * @author qinlh
     * @since 2022-06-29
     */
    public static function getAccountBalance() {
        $rpc = new hbdm('a36c5b20-qv2d5ctgbn-cb3de46a-f3ce8', '3d2322fc-a5919d1d-18dc22e8-527e9');
        $data = $rpc->get_account_valuation();
        p($data);
    }

    /**
     * 获取账户余额
     * @author qinlh
     * @since 2022-06-29
     */
    public static function getCcxtAccountBalance() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);

        $className = "\ccxt\\okex5";

        $exchange  = new $className(array(
            'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
            'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
            'password' => 'Zx112211',
        ));

        //$this->market
        try {  
            // $result = $exchange->fetch_users_subaccount_list();
            $result = $exchange->fetch_balance();
            p($result);
        } catch (\Exception $e) {
            //$this->error($e->getMessage());
            return array(0, $e->getMessage());
        }
    }
    
}