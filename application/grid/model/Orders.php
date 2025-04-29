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
// | Date: 2025-04-09
// +----------------------------------------------------------------------
namespace app\grid\model;

use think\Model;
use RequestService\RequestService;

class Orders extends Base
{

    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getOrderList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        $lists = self::name("orders")
                    ->alias("a")
                    ->join("g_accounts b", "a.account_id=b.id", "left")
                    ->where($where)
                    ->field('a.*,b.name as account_name')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        $newArrayData = [];
        foreach ($lists as $key => $val) {
            if ($val['position_group_id'] !== '') {
                $newArrayData[$val['position_group_id']][] = $val;
            } else {
                $newArrayData[$val['id']][0] = $val;
                $newArrayData[$val['id']][1] = [];
                // $newArrayData[$val['id']][1]['type_str'] = $val['status'] == 1 ? '等待卖出' : '等待买入';
            }
        }
        // p($newArrayData);
        $resultArray = [];
        foreach ($newArrayData as $key => $val) {
            $resultArray[$key]['timestamp'] = $val[0]['timestamp'];
            $resultArray[$key]['profit'] = $val[0]['profit'] ? $val[0]['profit'] : 0;
            $resultArray[$key]['account_name'] = $val[0]['account_name'];
            $resultArray[$key]['lists'] = $val;
        }
        // p($resultArray);
        $newResultArray = array_values($resultArray);
        // p($newResultArray);
        //总数
        $total = count($newResultArray);
        $allpage = intval(ceil($total / $limits));
        //分页
        $start = ($page - 1) * $limits;
        $returnArr = array_slice($newResultArray, $start, $limits);
        // p($returnArr);
        return ['count'=>$total,'allpage'=>$allpage,'lists'=>$returnArr];
    }

}
