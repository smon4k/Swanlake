<?php
// +----------------------------------------------------------------------
// | 文件说明：网格管理
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-04-09
// +----------------------------------------------------------------------
namespace app\grid\controller;

use app\grid\model\Orders;
use app\grid\model\Config;
use app\grid\model\Accounts;
use app\grid\model\Signals;
use app\grid\model\Strategy;
use app\grid\model\AccountHistorPosition;
use think\Request;
use think\Controller;
use think\Db;
use think\Loader;
use RequestService\RequestService;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\ClFile;
use ClassLibrary\ClString;
use ClassLibrary\ClHttp;
use ClassLibrary\ClImage;

class GridController extends BaseController
{
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getOrderList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $order_number = $request->request('order_number', '', 'trim');
        $tactics_name = $request->request('strategy_name', '', 'trim');
        $status = $request->request('status', '', 'trim');
        $where = [];
        $where['a.status'] = ['in', ['live', 'filled']];
        if($account_id) {
            $where['a.account_id'] = $account_id;
        } 
        if($order_number && $order_number !== "") {
            $where['a.order_id'] = ['like',"%" . $order_number . "%"];
        }
        if($tactics_name && $tactics_name !== "") {
            $accountIds = Config::getAccountIdByTacticsName($tactics_name);
            $where['a.account_id'] = ['in', $accountIds];
        }
        if($status && $status !== "") {
            $where['a.status'] = $status;
        }
        $data = Orders::getOrderList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        $totalProfit = $data['totalProfit'];
        // p($lists);
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists, 'totalProfit' => $totalProfit]);
    }


    /**
     * 获取机器人配置
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
     */
    public function getRobotConfig(Request $request) { 
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $tactics_name = $request->request('strategy_name', '', 'trim');
        $where = [];
        if($tactics_name && $tactics_name !== "") {
            $accountIds = Config::getAccountIdByTacticsName($tactics_name);
            if (!$accountIds) {
                return $this->as_json(['page'=>$page, 'allpage'=>0, 'count'=>0, 'data'=>[]]);
            }
            $where['a.account_id'] = ['in', (array)$accountIds];
        }
        $data = Config::getConfigList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        // p($lists);
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }


    private function hasConfigValue($value) {
        return !($value === '' || $value === null);
    }

    private function normalizeGridPercentList($gridPercentList) {
        if (!is_array($gridPercentList)) {
            return [];
        }
        return array_values(array_filter($gridPercentList, function ($item) {
            return is_array($item) && !empty($item['direction']);
        }));
    }

    private function normalizeMaxPositionList($maxPositionList, $fallbacks = []) {
        if (!is_array($maxPositionList)) {
            return [];
        }

        $normalizedList = [];
        $seenSymbols = [];
        foreach ($maxPositionList as $item) {
            if (!is_array($item) || empty($item['symbol']) || empty($item['tactics'])) {
                continue;
            }

            $normalizedSymbol = strtoupper(trim($item['symbol']));
            if (substr($normalizedSymbol, -5) === '-SWAP') {
                $normalizedSymbol = substr($normalizedSymbol, 0, -5);
            }
            if ($normalizedSymbol === '') {
                continue;
            }
            $existingIndex = $seenSymbols[$normalizedSymbol] ?? null;

            $itemGridPercentList = $this->normalizeGridPercentList($item['grid_percent_list'] ?? []);
            if (count($itemGridPercentList) <= 0) {
                $itemGridPercentList = $this->normalizeGridPercentList($fallbacks['grid_percent_list'] ?? []);
            }

            $normalizedItem = array_merge($item, [
                'symbol' => $normalizedSymbol,
                'stop_profit_loss' => $this->hasConfigValue($item['stop_profit_loss'] ?? null) ? $item['stop_profit_loss'] : ($fallbacks['stop_profit_loss'] ?? ''),
                'grid_step' => $this->hasConfigValue($item['grid_step'] ?? null) ? $item['grid_step'] : ($fallbacks['grid_step'] ?? ''),
                'commission_price_difference' => $this->hasConfigValue($item['commission_price_difference'] ?? null) ? $item['commission_price_difference'] : ($fallbacks['commission_price_difference'] ?? ''),
                'grid_percent_list' => $itemGridPercentList,
                'max_loss_number' => $this->hasConfigValue($item['max_loss_number'] ?? null) ? $item['max_loss_number'] : ($fallbacks['max_loss_number'] ?? ''),
                'min_loss_ratio' => $this->hasConfigValue($item['min_loss_ratio'] ?? null) ? $item['min_loss_ratio'] : ($fallbacks['min_loss_ratio'] ?? ''),
                'increase_ratio' => $this->hasConfigValue($item['increase_ratio'] ?? null) ? $item['increase_ratio'] : ($fallbacks['increase_ratio'] ?? ''),
                'decrease_ratio' => $this->hasConfigValue($item['decrease_ratio'] ?? null) ? $item['decrease_ratio'] : ($fallbacks['decrease_ratio'] ?? ''),
                'clear_value' => $this->hasConfigValue($item['clear_value'] ?? null) ? $item['clear_value'] : ($fallbacks['clear_value'] ?? ''),
            ]);

            // 同一账户下同一币种只保留最后一次配置，避免后端落库后命中顺序不稳定
            if ($existingIndex !== null && isset($normalizedList[$existingIndex])) {
                $normalizedList[$existingIndex] = $normalizedItem;
                continue;
            }

            $normalizedList[] = $normalizedItem;
            $seenSymbols[$normalizedSymbol] = count($normalizedList) - 1;
        }

        return $normalizedList;
    }

    private function validateRobotConfigPayload($accountId, $multiple, $positionPercent, $totalPosition, $maxPositionList) {
        if ($accountId <= 0 || !$this->hasConfigValue($multiple) || !$this->hasConfigValue($positionPercent) || !$this->hasConfigValue($totalPosition)) {
            return false;
        }

        return count($maxPositionList) > 0;
    }

     /**
     * 添加机器人配置
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
     */
    public function addRobotConfig(Request $request) {
        $account_id = $request->post('account_id', 0, 'intval');
        $multiple = $request->post('multiple', '', 'trim');
        $position_percent = $request->post('position_percent', '', 'trim');
        $total_position = $request->post('total_position', '', 'trim');
        $stop_profit_loss = $request->post('stop_profit_loss', '', 'trim');
        $grid_step = $request->post('grid_step', '', 'trim');
        $commission_price_difference = $request->post('commission_price_difference', '', 'trim');
        $max_loss_number = $request->post('max_loss_number', '', 'trim');
        $min_loss_ratio = $request->post('min_loss_ratio', '', 'trim');
        $increase_ratio = $request->post('increase_ratio', '', 'trim');
        $decrease_ratio = $request->post('decrease_ratio', '', 'trim');
        $clear_value = $request->post('clear_value', '', 'trim');
        $max_position_list = $request->post('max_position_list/a', '', 'trim');
        $grid_percent_list = $request->post('grid_percent_list/a', '', 'trim');

        $fallbacks = [
            'stop_profit_loss' => $stop_profit_loss,
            'grid_step' => $grid_step,
            'commission_price_difference' => $commission_price_difference,
            'grid_percent_list' => $grid_percent_list,
            'max_loss_number' => $max_loss_number,
            'min_loss_ratio' => $min_loss_ratio,
            'increase_ratio' => $increase_ratio,
            'decrease_ratio' => $decrease_ratio,
            'clear_value' => $clear_value,
        ];
        $normalized_max_position_list = $this->normalizeMaxPositionList($max_position_list, $fallbacks);
        $normalized_grid_percent_list = $this->normalizeGridPercentList($grid_percent_list);

        if (!$this->validateRobotConfigPayload($account_id, $multiple, $position_percent, $total_position, $normalized_max_position_list)) {
            return $this->as_json('70001', 'Missing parameters');
        }

        $data = [
            'account_id' => $account_id,
            'multiple' => $multiple,
            'position_percent' => $position_percent,
            'total_position' => $total_position,
            'stop_profit_loss' => $stop_profit_loss,
            'grid_step' => $grid_step,
            'max_position_list' => json_encode($normalized_max_position_list, JSON_UNESCAPED_UNICODE),
            'grid_percent_list' => json_encode($normalized_grid_percent_list, JSON_UNESCAPED_UNICODE), 
            'commission_price_difference' => $commission_price_difference,
            'is_active' => 1,
        ];
        $res = Config::addRobotConfig($data);
        if($res['code'] == 1) {
            return $this->as_json($res);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

     /**
     * 修改机器人配置
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
     */
    public function updateRobotConfig(Request $request) {
        $id = $request->post('id', 0, 'intval');
        $account_id = $request->post('account_id', 0, 'intval');
        $multiple = $request->post('multiple', '', 'trim');
        $position_percent = $request->post('position_percent', '', 'trim');
        $total_position = $request->post('total_position', '', 'trim');
        $stop_profit_loss = $request->post('stop_profit_loss', '', 'trim');
        $grid_step = $request->post('grid_step', '', 'trim');
        $commission_price_difference = $request->post('commission_price_difference', '', 'trim');
        $max_loss_number = $request->post('max_loss_number', '', 'trim');
        $min_loss_ratio = $request->post('min_loss_ratio', '', 'trim');
        $increase_ratio = $request->post('increase_ratio', '', 'trim');
        $decrease_ratio = $request->post('decrease_ratio', '', 'trim');
        $clear_value = $request->post('clear_value', '', 'trim');
        $max_position_list = $request->post('max_position_list/a', '', 'trim');
        $grid_percent_list = $request->post('grid_percent_list/a', '', 'trim');

        $fallbacks = [
            'stop_profit_loss' => $stop_profit_loss,
            'grid_step' => $grid_step,
            'commission_price_difference' => $commission_price_difference,
            'grid_percent_list' => $grid_percent_list,
            'max_loss_number' => $max_loss_number,
            'min_loss_ratio' => $min_loss_ratio,
            'increase_ratio' => $increase_ratio,
            'decrease_ratio' => $decrease_ratio,
            'clear_value' => $clear_value,
        ];
        $normalized_max_position_list = $this->normalizeMaxPositionList($max_position_list, $fallbacks);
        $normalized_grid_percent_list = $this->normalizeGridPercentList($grid_percent_list);

        if ($id <= 0 || !$this->validateRobotConfigPayload($account_id, $multiple, $position_percent, $total_position, $normalized_max_position_list)) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $data = [
            'account_id' => $account_id,
            'multiple' => $multiple,
            'position_percent' => $position_percent,
            'total_position' => $total_position,
            'stop_profit_loss' => $stop_profit_loss,
            'grid_step' => $grid_step,
            'max_position_list' => json_encode($normalized_max_position_list, JSON_UNESCAPED_UNICODE),
            'grid_percent_list' => json_encode($normalized_grid_percent_list, JSON_UNESCAPED_UNICODE), 
            'commission_price_difference' => $commission_price_difference,
            'updated_at' => date('Y-m-d H:i:s'), 
        ];
        $res = Config::updateRobotConfig($id, $data);
        if($res['code'] == 1) {
            return $this->as_json($res); 
        } else {
            return $this->as_json(70001, $res['msg']); 
        }
    }

    /**
     * 删除配置文件
     * @param Request $request
     * @param Request $request
     * @return \think\response\Json
    */
    public function deleteRobotConfig(Request $request) {
        $id = $request->request('id', 0, 'intval');
        if ($id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $res = Config::deleteRobotConfig($id);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']); 
        } else {
            return $this->as_json(70001, $res['msg']);
        } 
    }

    /**
     * 获取账户列表
     * @author qinlh
     * @since 2023-01-31
     */
    public function getAccountList(Request $request) {
        $external = $request->request('external', 0, 'intval');
        $where = [];
        $where['status'] = 1;
        $result = Accounts::getAccountList($where);
        return $this->as_json($result);
    }

    
    /**
     * 获取信号列表
     * @author qinlh
     * @since 2025-07-22
     * @param Request $request
     * @return \think\response\Json
     */
    public function getSignalsList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $tactics_name = $request->request('strategy_name', '', 'trim');
        $symbol = strtoupper($request->request('symbol', '', 'trim'));

        $where = [];
        $where['pair_id'] = ['<>', 0];
        if($tactics_name && $tactics_name !== "") {
            $where['name'] = $tactics_name;
        }
        if($symbol && $symbol !== "") {
            $where['symbol'] = ['like', $symbol . '%'];
        }
        $result = Signals::getSignalsList($page, $where, $limits);
        return $this->as_json($result);
    }

    /**
     * 获取所有策略列表
     * @author qinlh
     * @since 2025-07-22
     * @param Request $request
     * @return \think\response\Json
     */
    public function getAllStrategyList(Request $request)
    {
        $result = Strategy::getAllStrategyList();
        return $this->as_json($result);
    }

    /**
     * 获取策略列表
     * @author qinlh
     * @since 2025-07-22
     * @param Request $request
     * @return \think\response\Json
     */
    public function getStrategyList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $where = [];
        $where['status'] = 1;
        $result = Strategy::getStrategyList($page, $where, $limits);
        if (!empty($result['lists']) && is_array($result['lists'])) {
            foreach ($result['lists'] as &$item) {
                $item['is_referenced'] = Config::isStrategyReferenced($item['name']) ? 1 : 0;
            }
            unset($item);
        }
        return $this->as_json($result);
    }

    /**
     * 修改指定策略最大最小仓位数
     * @author qinlh
     * @since 2025-07-28
     * @param Request $request
     * @return \think\response\Json
     */
    public function updateStrategyMaxMinPosition(Request $request) {
        $id = $request->post('id', 0, 'intval');
        $name = $request->post('name', '', 'trim');
        $max_position = $request->post('max_position', '', 'trim');
        $min_position = $request->post('min_position', '', 'trim');
        $stop_loss_percent = $request->post('stop_loss_percent', '', 'trim');
        $open_coefficient = $request->post('open_coefficient', '', 'trim');
        if ($id <= 0 || $name === '' || $max_position === '' || $min_position === '' || $stop_loss_percent === '' || $open_coefficient === '') {
            return $this->as_json('70001', 'Missing parameters');
        }

        $strategy = Strategy::getStrategyById($id);
        if (!$strategy) {
            return $this->as_json(70001, '该策略不存在');
        }

        if ($name !== $strategy['name']) {
            if (Config::isStrategyReferenced($strategy['name'])) {
                return $this->as_json(70001, '该策略已被机器人配置引用，不能修改名称');
            }
            if (Strategy::existsByName($name, $id)) {
                return $this->as_json(70001, '策略名称已存在');
            }
        }

        $data = [
            'name' => $name,
            'max_position' => $max_position,
            'min_position' => $min_position,
            'stop_loss_percent' => $stop_loss_percent,
            'open_coefficient' => $open_coefficient,
            'updated_at' => date('Y-m-d H:i:s'), 
        ];
        $res = Strategy::updateStrategyMaxMinPosition($id, $data);
        if($res['code'] == 1) {
            return $this->as_json($res); 
        } else {
            return $this->as_json(70001, $res['msg']); 
        }
        return $this->as_json(70001, 'Update failed');
    }

    /**
     * 删除策略
     * @author qinlh
     * @since 2026-06-13
     * @param Request $request
     * @return \think\response\Json
     */
    public function deleteStrategy(Request $request) {
        $id = $request->request('id', 0, 'intval');
        if ($id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }

        $strategy = Strategy::getStrategyById($id);
        if (!$strategy) {
            return $this->as_json(70001, '该策略不存在');
        }

        if (Config::isStrategyReferenced($strategy['name'])) {
            return $this->as_json(70001, '该策略已被机器人配置引用，不能删除');
        }

        $res = Strategy::deleteStrategy($id);
        if($res['code'] == 1) {
            return $this->as_json($res);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
     * 新增策略
     * @author qinlh
     * @since 2026-06-12
     * @param Request $request
     * @return \think\response\Json
     */
    public function addStrategy(Request $request) {
        $name = $request->post('name', '', 'trim');
        $max_position = $request->post('max_position', '', 'trim');
        $min_position = $request->post('min_position', '', 'trim');
        $stop_loss_percent = $request->post('stop_loss_percent', '', 'trim');
        $open_coefficient = $request->post('open_coefficient', '', 'trim');
        if ($name === '' || $max_position === '' || $min_position === '' || $stop_loss_percent === '' || $open_coefficient === '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'name' => $name,
            'max_position' => $max_position,
            'min_position' => $min_position,
            'stop_loss_percent' => $stop_loss_percent,
            'open_coefficient' => $open_coefficient,
            'loss_number' => 0,
            'count_profit_loss' => 0,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $res = Strategy::addStrategy($data);
        if($res['code'] == 1) {
            return $this->as_json($res); 
        } else {
            return $this->as_json(70001, $res['msg']); 
        }
    }

    /**
     * 获取用户仓位变更历史记录列表
     * @author qinlh
     * @since 2025-08-01
     * @param Request $request
     * @return \think\response\Json
     */
    public function getAccountHistorPositionList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 20, 'intval');
        $account_id = $request->request('account_id', 0, 'intval');
        $where = [];
        if ($account_id) {
            $where['a.account_id'] = $account_id;
        }
        $result = AccountHistorPosition::getAccountHistorPositionList($page, $where, $limits);
        return $this->as_json($result);
    }


}
