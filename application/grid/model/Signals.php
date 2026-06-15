<?php
// +----------------------------------------------------------------------
// | 文件说明：订单管理 
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-07-22
// +----------------------------------------------------------------------
namespace app\grid\model;

use think\Model;
use RequestService\RequestService;

class Signals extends Base
{

    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getSignalsList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("signals")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("signals")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("a.pair_id desc, a.id desc")
                    ->select()
                    ->toArray();
        // $newArrayData = [];
        // foreach ($lists as $key => $val) {
        //     if($val['pair_id'] <= 0) {
        //         continue;
        //     }
        //     if ($val['pair_id'] !== '') {
        //         $newArrayData[$val['pair_id']][] = $val;
        //     } else {
        //         $newArrayData[$val['id']][0] = $val;
        //         $newArrayData[$val['id']][1] = [];
        //     }
        // }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 获取当前仍在持仓中的信号 pair_id 列表
    * 规则：取每个 pair_id 最新一条信号，size != 0 视为当前仍在持仓
    * @param array $where
    * @return array
    */
    public static function getOpenPositionPairIds($where = [])
    {
        $baseWhere = array_merge(['pair_id' => ['<>', 0]], $where);
        $rows = self::name("signals")
                    ->alias("a")
                    ->where($baseWhere)
                    ->field('a.pair_id,a.size,a.id')
                    ->order("a.pair_id desc, a.id desc")
                    ->select()
                    ->toArray();

        if (!$rows) {
            return [];
        }

        $latestByPair = [];
        foreach ($rows as $row) {
            $pairId = intval($row['pair_id']);
            if ($pairId <= 0 || isset($latestByPair[$pairId])) {
                continue;
            }
            $latestByPair[$pairId] = $row;
        }

        $pairIds = [];
        foreach ($latestByPair as $pairId => $row) {
            if (floatval($row['size']) != 0) {
                $pairIds[] = $pairId;
            }
        }

        return $pairIds;
    }

}
