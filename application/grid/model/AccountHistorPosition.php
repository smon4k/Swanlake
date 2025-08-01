<?php
// +----------------------------------------------------------------------
// | 文件说明：用户仓位变更历史记录 
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-08-01
// +----------------------------------------------------------------------
namespace app\grid\model;

use think\Model;
use RequestService\RequestService;

class AccountHistorPosition extends Base
{

    /**
    * 获取用户仓位变更历史记录列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getAccountHistorPositionList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::alias("a")
                    ->join('g_accounts b', 'a.account_id = b.id')
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::alias("a")
                    ->join('g_accounts b', 'a.account_id = b.id')
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*,b.name')
                    ->order("a.id desc")
                    ->select()
                    ->toArray();
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

}
