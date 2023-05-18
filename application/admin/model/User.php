<?php
// +----------------------------------------------------------------------
// | 文件说明：用户-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2023-04-08
// +----------------------------------------------------------------------
namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;
use app\api\model\FillingRecord;

class User extends Base
{   
    /**
    * 获取用户列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getUserList($page, $where, $limits=0, $order='id desc') {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::alias('a')->join('d_user_balance b', 'a.id=b.user_id')->where($where)->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::alias('a')
                    ->join('d_user_balance b', 'a.id=b.user_id')
                    ->where($where)
                    ->field('a.*,(b.`local_balance` + b.`wallet_balance` + `trial_balance`) AS total_balance,b.currency,b.trial_balance')
                    ->page($page, $limits)
                    ->order($order)
                    ->select()
                    ->toArray();
        foreach ($lists as $key => $val) {
            $frozenBalance = FillingRecord::getUserFrozenBalance($val['address'], $val['currency']); //获取提取冻结资金
            $lists[$key]['total_balance'] = $val['total_balance'] ? $val['total_balance'] - $frozenBalance : 0 - $frozenBalance;
        }
        // p($lists);
        return ['count'=>$count, 'allpage'=>$allpage, 'lists'=>$lists];
    }
}
