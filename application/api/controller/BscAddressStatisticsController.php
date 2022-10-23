<?php
// +----------------------------------------------------------------------
// | 文件说明：bsc 币种统计
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-10-23
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\api\model\BscAddressStatistics;
use think\Request;
use think\Db;
use think\Controller;
use ClassLibrary\ClFile;
use ClassLibrary\CLUpload;
use ClassLibrary\CLFfmpeg;
use lib\Filterstring;

error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '-1');
class BscAddressStatisticsController extends BaseController
{
    /**
     * 获取每小时数据
     * @author qinlh
     * @since 2022-10-23
     */
    public function getHourDataList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 1000000, 'intval');
        $name = $request->request('name', '', 'trim');
        $where = [];
        if($name && $name !== '') {
            $where['a.name'] = $name;
        }
        $order = 'a.date asc';
        $result = BscAddressStatistics::getHourDataList($where, $page, $limit, $order);
        return $this->as_json($result);
    }
}