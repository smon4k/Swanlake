<?php
// +----------------------------------------------------------------------
// | 文件说明：量化账户监控数据统计
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2023 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2023-01-30
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\api\model\QuantifyAccount;
use app\api\model\User;
use think\Request;
use think\Db;
use think\Controller;
use ClassLibrary\ClFile;
use ClassLibrary\CLUpload;
use ClassLibrary\CLFfmpeg;
use lib\Filterstring;

error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '-1');
class QuantifyaccountController extends BaseController
{
     /**
     * 记录量化数据
     * @author qinlh
     * @since 2022-07-08
     */
    public function setTradePairBalance(Request $request) {
        $result = QuantifyAccount::calcQuantifyAccountData();
        return $this->as_json($result);
    }
}