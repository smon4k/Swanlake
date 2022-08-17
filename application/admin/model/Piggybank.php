<?php
// +----------------------------------------------------------------------
// | 文件说明：H2O-存钱罐-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-17
// +----------------------------------------------------------------------
namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;

class Piggybank extends Base
{   
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankOrderList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("okx_piggybank")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("okx_piggybank")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 获取每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankDateList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("okx_piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("okx_piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
}
