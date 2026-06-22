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
                    ->where($baseimage.pngWhere)
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

    /**
    * 获取当前“仍在持仓中的信号”ID 列表
    * 规则：按 策略 + 币种 + 实际持仓方向 分组，只取每组最新一条；
    * 若最新一条是开仓信号(size != 0)，则视为当前仍在持仓中的信号。
    * 这样不会把同策略较早、实际上已被后续信号覆盖的旧开仓腿继续展示出来。
    * @param array $where
    * @return array
    */
    public static function getCurrentOpenSignalIds($where = [])
    {
        $baseWhere = $where;
        $validStrategyNames = self::getValidSignalStrategyNames();
        if (empty($validStrategyNames)) {
            return [];
        }
        $rows = self::name("signals")
                    ->alias("a")
                    ->where($baseWhere)
                    ->field('a.id,a.pair_id,a.name,a.symbol,a.direction,a.size,a.position_at,a.status')
                    ->order("a.id desc")
                    ->select()
                    ->toArray();

        if (!$rows) {
            return [];
        }

        $latestByGroup = [];
        foreach ($rows as $row) {
            if (!in_array(strval($row['name']), $validStrategyNames, true)) {
                continue;
            }
            $positionSide = self::normalizePositionSide($row);
            if ($positionSide === '') {
                continue;
            }
            $groupKey = implode('|', [
                strval($row['name']),
                strval($row['symbol']),
                $positionSide,
            ]);
            if (isset($latestByGroup[$groupKey])) {
                continue;
            }
            $latestByGroup[$groupKey] = $row;
        }

        $signalIds = [];
        foreach ($latestByGroup as $row) {
            $status = strtolower(strval(isset($row['status']) ? $row['status'] : ''));
            if (floatval($row['size']) != 0 && $status !== 'failed' && intval($row['pair_id']) > 0) {
                $signalIds[] = intval($row['id']);
            }
        }

        return $signalIds;
    }

    /**
    * 获取有效的策略名称白名单
    * 范围：启用中的策略 + 当前机器人配置仍在引用的策略
    * @return array
    */
    protected static function getValidSignalStrategyNames()
    {
        $names = [];

        $strategyList = Strategy::getAllStrategyList();
        if (is_array($strategyList)) {
            foreach ($strategyList as $strategy) {
                if (!empty($strategy['name'])) {
                    $names[] = strval($strategy['name']);
                }
            }
        }

        $configs = Config::select();
        foreach ($configs as $config) {
            $maxPositionList = json_decode($config['max_position_list'], true);
            if (!is_array($maxPositionList)) {
                continue;
            }

            foreach ($maxPositionList as $item) {
                if (!empty($item['tactics'])) {
                    $names[] = strval($item['tactics']);
                }
            }
        }

        $names = array_values(array_unique(array_filter($names)));
        return $names;
    }

    /**
    * 统一推导信号对应的实际持仓方向
    * 开仓：size > 0 => long, size < 0 => short
    * 平仓：direction=long => 平空 => short, direction=short => 平多 => long
    * @param array $row
    * @return string
    */
    protected static function normalizePositionSide($row)
    {
        $size = floatval(isset($row['size']) ? $row['size'] : 0);
        $direction = strtolower(strval(isset($row['direction']) ? $row['direction'] : ''));

        if ($size > 0) {
            return 'long';
        }
        if ($size < 0) {
            return 'short';
        }
        if ($direction === 'long') {
            return 'short';
        }
        if ($direction === 'short') {
            return 'long';
        }

        return '';
    }

}
