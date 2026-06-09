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
use think\Db;
use RequestService\RequestService;

class Orders extends Base
{
    /**
     * 统一生成订单分组键。
     * 有 position_group_id 的订单按组归并；没有分组的订单按自身 id 单独成组。
     *
     * @param array $order 订单数据
     * @return string
     */
    private static function buildOrderGroupKey($order)
    {
        $positionGroupId = isset($order['position_group_id']) ? trim((string)$order['position_group_id']) : '';
        if ($positionGroupId !== '') {
            return 'group:' . $positionGroupId;
        }
        return 'single:' . (string)$order['id'];
    }

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
        // 先按“订单组”分页，避免把全部订单一次性加载进 PHP 内存。
        $groupExpr = "CASE WHEN a.position_group_id IS NOT NULL AND a.position_group_id <> '' THEN CONCAT('group:', a.position_group_id) ELSE CONCAT('single:', a.id) END";

        $groupBaseQuery = self::name("orders")
            ->alias("a")
            ->where($where)
            ->field("{$groupExpr} as group_key, MAX(a.id) as latest_id");

        $groupRows = clone $groupBaseQuery;
        $groupRows = $groupRows
            ->group($groupExpr)
            ->order("latest_id desc")
            ->page($page, $limits)
            ->select()
            ->toArray();

        // ThinkPHP 5.0.24 对 count("DISTINCT CASE WHEN ...") 兼容性不好，
        // 这里改成先生成分组子查询，再在外层做 count(*)。
        $groupCountSql = clone $groupBaseQuery;
        $groupCountSql = $groupCountSql
            ->group($groupExpr)
            ->buildSql();
        $total = Db::table($groupCountSql . ' tp_group_count')->count();

        if (empty($groupRows)) {
            $allpage = intval(ceil($total / $limits));
            $totalProfit = self::name("orders")
                ->alias("a")
                ->where($where)
                ->sum('profit');
            return ['count' => $total, 'allpage' => $allpage, 'lists' => [], 'totalProfit' => $totalProfit];
        }

        $groupIds = [];
        $singleIds = [];
        $groupOrder = [];
        foreach ($groupRows as $row) {
            $groupKey = $row['group_key'];
            $groupOrder[] = $groupKey;
            if (strpos($groupKey, 'group:') === 0) {
                $groupIds[] = substr($groupKey, 6);
            } elseif (strpos($groupKey, 'single:') === 0) {
                $singleIds[] = intval(substr($groupKey, 7));
            }
        }

        $detailQuery = self::name("orders")
            ->alias("a")
            ->join("g_accounts b", "a.account_id=b.id", "left")
            ->where($where)
            ->field('a.*,b.name as account_name');

        $detailQuery->where(function ($query) use ($groupIds, $singleIds) {
            if (!empty($groupIds) && !empty($singleIds)) {
                $query->where('a.position_group_id', 'in', $groupIds)
                    ->whereOr('a.id', 'in', $singleIds);
            } elseif (!empty($groupIds)) {
                $query->where('a.position_group_id', 'in', $groupIds);
            } elseif (!empty($singleIds)) {
                $query->where('a.id', 'in', $singleIds);
            }
        });

        $lists = $detailQuery
            ->order("a.id desc")
            ->select()
            ->toArray();

        $newArrayData = [];
        foreach ($lists as $val) {
            $groupKey = self::buildOrderGroupKey($val);
            $newArrayData[$groupKey][] = $val;
        }

        $resultArray = [];
        foreach ($groupOrder as $groupKey) {
            if (empty($newArrayData[$groupKey])) {
                continue;
            }
            $val = array_values($newArrayData[$groupKey]);
            $resultArray[$groupKey]['timestamp'] = $val[0]['timestamp'];
            $price = 0;
            $profit = $val[0]['profit'];
            if (isset($val[1])) {
                $price = abs($val[0]['price'] -  $val[1]['price']);
            }
            $resultArray[$groupKey]['price'] = $price;
            $resultArray[$groupKey]['profit'] = $profit ? $profit : 0;
            $resultArray[$groupKey]['account_name'] = $val[0]['account_name'];
            $resultArray[$groupKey]['lists'] = $val;
        }

        $allpage = intval(ceil($total / $limits));
        $totalProfit = self::name("orders")
            ->alias("a")
            ->where($where)
            ->sum('profit');
        return ['count' => $total, 'allpage' => $allpage, 'lists' => array_values($resultArray), 'totalProfit' => $totalProfit];
    }

}
