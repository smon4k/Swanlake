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
class BscaddressStatisticsController extends BaseController
{
    /**
     * 获取每小时数据
     * 总地址和新增地址
     * @author qinlh
     * @since 2022-10-23
     */
    public function getHourDataList(Request $request) {
        $name = $request->request('name', '', 'trim');
        $this_year = $request->request('this_year', "", 'trim');
        $time_range = $request->request('time_range', "", 'trim');
        $start_time = $request->request('start_time', "", 'trim');
        $end_time = $request->request('end_time', "", 'trim');
        $result = BscAddressStatistics::getHourDataList($name, $time_range, $this_year, $start_time, $end_time);
        return $this->as_json($result);
    }

    /**
     * 获取每小时数据
     * 销毁数
     * @author qinlh
     * @since 2022-10-25
     */
    public function getDestructionDataList(Request $request) {
        $name = $request->request('name', '', 'trim');
        $this_year = $request->request('this_year', "", 'trim');
        $time_range = $request->request('time_range', "", 'trim');
        $start_time = $request->request('start_time', "", 'trim');
        $end_time = $request->request('end_time', "", 'trim');
        $result = BscAddressStatistics::getDestructionDataList($name, $time_range, $this_year, $start_time, $end_time);
        return $this->as_json($result);
    }
}