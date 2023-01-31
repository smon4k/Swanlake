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

    /**
    * 获取每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getQuantifyAccountDateList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $order_number = $request->request('order_number', '', 'trim');
        // $standard = $request->request('standard', 0, 'intval');
        $where = [];
        $where['state'] = 1;
        $where['account_id'] = $account_id;
        $data = QuantifyAccount::getQuantifyAccountDateList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
     * 出入金计算
     * @author qinlh
     * @since 2022-08-20
     */
    public function calcDepositAndWithdrawal(Request $request) {
        $account_id = $request->request('account_id', '', 'intval');
        $direction = $request->request('direction', 0, 'intval');
        $amount = $request->request('amount', '', 'trim');
        $remark = $request->request('remark', '', '');
        if($direction <= 0 || $amount == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = QuantifyAccount::calcQuantifyAccountData($account_id, $direction, $amount, $remark);
        if($result) {
            return $this->as_json('ok');
        } else {
            return $this->as_json(70001, 'Error');
        }
    }
}