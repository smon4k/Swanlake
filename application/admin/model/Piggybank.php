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
                    ->select()
                    ->toArray();
        // p($lists);
        $newArrayData = [];
        foreach ($lists as $key => $val) {
            if($key < count((array)$lists) - 1) {
                $supData = $lists[$key + 1];
                if($val['type'] !== $supData['type']) { //组对
                    $newArrayData[$val['time']][0] = $val;
                    $newArrayData[$val['time']][1] = $supData;
                } else {
                    $newArrayData[$val['time']][] = $val;
                }
            }
        }
        $resultArray = [];
        foreach ($newArrayData as $key => $val) {
            $resultArray[$key]['time'] = $key;
            $price = 0;
            $profit = $val[0]['profit'];
            if(isset($val[1])) {
                $price = $val[0]['price'] -  $val[1]['price'];
            } else {
                $price = $val[0]['price'];
            }
            $resultArray[$key]['price'] = $price;
            $resultArray[$key]['profit'] = $profit;
            $resultArray[$key]['lists'] = $val;
        }
        $newResultArray = array_values($resultArray);
        // p($newResultArray);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$newResultArray    ];
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
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
}
