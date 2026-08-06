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
                    ->field('a.id,a.pair_id,a.name,a.symbol,a.direction,a.size,a.position_at,a.status,a.success_accounts')
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
                if (self::isSignalStillOpen($row)) {
                    $signalIds[] = intval($row['id']);
                }
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
    * 开仓：direction=long 且 size>0 => long；direction=short 且 size!=0 => short
    * 平仓：direction=long => 平空 => short, direction=short => 平多 => long
    * @param array $row
    * @return string
    */
    protected static function normalizePositionSide($row)
    {
        $size = floatval(isset($row['size']) ? $row['size'] : 0);
        $direction = strtolower(strval(isset($row['direction']) ? $row['direction'] : ''));

        if ($size > 0 && $direction === 'long') {
            return 'long';
        }
        if ($size != 0 && $direction === 'short') {
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

    /**
     * 只把真实仍在持仓的信号展示到“信号持仓”视图。
     * 优先按 success_accounts 里的账户去查实时 OKX 持仓；查不到时宁可保守保留，
     * 避免把刚开仓但接口暂时抖动的信号误杀。
     */
    protected static function isSignalStillOpen($row): bool
    {
        $signalId = intval($row['id'] ?? 0);
        if ($signalId <= 0) {
            return false;
        }

        $symbol = trim((string)($row['symbol'] ?? ''));
        $positionSide = self::normalizePositionSide($row);
        if ($symbol === '' || $positionSide === '') {
            return true;
        }

        $accountIds = self::extractSignalAccountIds($row);
        if (empty($accountIds)) {
            return true;
        }

        foreach ($accountIds as $accountId) {
            $accountInfo = self::getSignalAccountInfo($accountId);
            if (empty($accountInfo)) {
                continue;
            }

            $positions = self::getOkxPositions($accountInfo, $symbol);
            if ($positions === null) {
                return true;
            }

            foreach ($positions as $position) {
                $instId = trim((string)($position['instId'] ?? $position['symbol'] ?? ''));
                if ($instId !== '' && strcasecmp($instId, $symbol) !== 0) {
                    continue;
                }

                $pos = floatval($position['pos'] ?? $position['contracts'] ?? 0);
                $posSide = strtolower(strval($position['posSide'] ?? $position['side'] ?? ''));
                if ($pos > 0 && $posSide === $positionSide) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 读取信号成功账户列表。
     */
    protected static function extractSignalAccountIds($row): array
    {
        $accountIds = [];

        $successAccounts = $row['success_accounts'] ?? null;
        if (is_string($successAccounts) && $successAccounts !== '') {
            $successAccounts = json_decode($successAccounts, true);
        }

        if (is_array($successAccounts)) {
            foreach ($successAccounts as $accountId) {
                $accountId = intval($accountId);
                if ($accountId > 0) {
                    $accountIds[] = $accountId;
                }
            }
        }

        return array_values(array_unique($accountIds));
    }

    /**
     * 读取账户信息，用于实时查询 OKX 持仓。
     */
    protected static function getSignalAccountInfo(int $accountId): array
    {
        $account = self::name('accounts')
            ->where('id', $accountId)
            ->where('status', 1)
            ->find();

        if (empty($account)) {
            return [];
        }

        return is_array($account) ? $account : $account->toArray();
    }

    /**
     * 查询实时 OKX 持仓。
     * 返回 null 表示接口异常，调用方应保守保留信号。
     */
    protected static function getOkxPositions(array $accountInfo, string $symbol): ?array
    {
        try {
            $url = config('okx_uri') . "/api/okex/get_positions?instType=SWAP&instId=" . urlencode($symbol);
            $params = [
                'api_key' => $accountInfo['api_key'],
                'secret_key' => $accountInfo['api_secret'],
                'passphrase' => $accountInfo['api_passphrase'],
            ];
            $responseString = RequestService::doJsonCurlPost($url, json_encode($params));
            $responseArr = json_decode($responseString, true);
            if (!$responseArr || ($responseArr['status'] ?? '') !== 'success') {
                return null;
            }

            $data = $responseArr['data'] ?? [];
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return null;
        }
    }

}
