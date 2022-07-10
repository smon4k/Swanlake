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
        $rpc = new hbdm('806eacdf-84f76c82-cdgs9k03f3-03c14', 'd4d6c964-629ba4e7-b9bd0c11-cc963');
        $data = $rpc->account_accounts(272749571);
        p($data);
    }
}