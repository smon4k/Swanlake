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
use cache\Rediscache;

error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '-1');
class QuantifyaccountController extends QuantifybaseController
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
        // $where['state'] = 1;
        if($account_id) {
            $where['account_id'] = $account_id;
        }
        $data = QuantifyAccount::getQuantifyAccountDateList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

     /**
    * 获取每日统计数据 所有账户
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getQuantifyAccountDateAllList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $order_number = $request->request('order_number', '', 'trim');
        // $standard = $request->request('standard', 0, 'intval');
        $where = [];
        // $where['state'] = 1;
        $data = QuantifyAccount::getQuantifyAccountDateAllList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 获取出入金列表数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getInoutGoldList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $where = [];
        $where['account_id'] = $account_id;
        $data = QuantifyAccount::getInoutGoldList($where, $page, $limits);
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
        $key = "Swanlake:calcDepositAndWithdrawal:".$account_id.":Lock";
        Rediscache::getInstance()->set($key, 1); 
        $result = QuantifyAccount::calcQuantifyAccountData($account_id, $direction, $amount, $remark);
        if($result) {
            Rediscache::getInstance()->del($key); 
            return $this->as_json('ok');
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取账户列表
     * @author qinlh
     * @since 2023-01-31
     */
    public function getAccountList(Request $request) {
        $external = $request->request('external', 0, 'intval');
        $user_id = $request->request('user_id', 0, 'intval');
        $where = [];
        $where['state'] = 1;
        if($external) {
            $where['external'] = 1;
        }
        if($user_id) {
            $where['user_id'] = $user_id;
        }
        $result = QuantifyAccount::getAccountList($where);
        return $this->as_json($result);
    }

    /**
     * 获取账户列表
     * @author qinlh
     * @since 2023-01-31
     */
    public function getQuantifyAccountDetails(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $currency = $request->request('currency', '', 'trim');
        $date = $request->request('date', '', 'trim');
        if(!$account_id || $account_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $where['account_id'] = $account_id;
        $where['balance'] = ['>', 0];
        if($currency && $currency !== '') {
            $where['currency'] = $currency;
        }
        if($date && $date !== '') {
            $where['date'] = $date;
        }
        $result = QuantifyAccount::getQuantifyAccountDetails($where, $page, $limits);
        return $this->as_json($result);
    }

    /**
    * 获取账户币种交易明细列表数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getAccountCurrencyDetailsList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $currency = $request->request('currency', '', 'trim');
        // $standard = $request->request('standard', 0, 'intval');
        $where = [];
        // $where['state'] = 1;
        $where['account_id'] = $account_id;
        if($currency && $currency !== '') {
            $where['currency'] = $currency;
        }
        $data = QuantifyAccount::getAccountCurrencyDetailsList($account_id, $where, $page, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

     /**
     * 分润记录-计算
     * @author qinlh
     * @since 2022-08-20
     */
    public function calcDividendRecord(Request $request) {
        $account_id = $request->request('account_id', '', 'intval');
        $amount = $request->request('amount', '', 'trim');
        $remark = $request->request('remark', '', '');
        if($amount == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = QuantifyAccount::calcQuantifyAccountData($account_id, 0, 0, null, $amount, $remark);
        if($result) {
            return $this->as_json('ok');
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

     /**
    * 获取分润记录数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getDividendRecordList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $where = [];
        $where['account_id'] = $account_id;
        $data = QuantifyAccount::getDividendRecordList($where, $page, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
     * 获取币种列表
     * @author qinlh
     * @since 2023-04-23
     */
    public function getQuantifyAccountCurrencyList(Request $request) {
        $account_id = $request->request('account_id', 0, 'intval');
        if(!$account_id || $account_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = QuantifyAccount::getQuantifyAccountCurrencyList($account_id);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取币种持仓信息
     * @author qinlh
     * @since 2023-04-23
     */
    public function getAccountCurrencyPositionsList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $currency = $request->request('currency', '', 'trim');
        if(!$account_id || $account_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $where['account_id'] = $account_id;
        $where['currency'] = $currency;
        $result = QuantifyAccount::getAccountCurrencyPositionsList($where, $page, $limits);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 获取币种持仓信息
     * 最大最小收益率历史记录
     * @author qinlh
     * @since 2023-04-23
     */
    public function getMaxMinUplRateData(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $currency = $request->request('currency', '', 'trim');
        if(!$account_id || $account_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $where['account_id'] = $account_id;
        $where['currency'] = $currency;
        $result = QuantifyAccount::getMaxMinUplRateData($account_id, $currency, $page, $limits);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * 添加账户
     * @author qinlh
     * @since 2024-10-14
     */
    public function addQuantityAccount(Request $request) {
        $name = $request->post('name', '', 'trim');
        $api_key = $request->post('api_key', '', 'trim');
        $secret_key = $request->post('secret_key', '', 'trim');
        $pass_phrase = $request->post('pass_phrase', '', 'trim');
        $type = $request->post('type', 0, 'intval');
        $is_position = $request->post('is_position', 0, 'intval');
        if ($api_key == '' || $name == '' || $secret_key == '' || $pass_phrase == '' || $type <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = QuantifyAccount::addQuantityAccount($name, $api_key, $secret_key, $pass_phrase, $type, $is_position, self::$_uid);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, '创建失败');
        }
    }

    // public function test(Request $request) {
    //     $accountInfo = QuantifyAccount::getAccountInfo(19);
    //     QuantifyAccount::getTransferHistory($accountInfo);
    // }
}