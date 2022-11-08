<?php
// +----------------------------------------------------------------------
// | 文件说明：产出数据 市场 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-06-05
// +----------------------------------------------------------------------
namespace app\hashpower\model;

use think\Model;

class Output extends Base
{

    /**
    * 获取昨日产出数据
    * @author qinlh
    * @since 2022-06-05
    */
    public static function getOutputYesterday()
    {
        $date = date('Y-m-d');
        $yester_date = date('Y-m-d', strtotime('-1 day'));
        $result = [];
        $result['yester_output'] = 0;
        $result['to_output'] = 0;
        $result['count_output'] = 0;
        $yester_output = self::where('date', $yester_date)->find();
        if ($yester_output && count($yester_output) > 0) {
            $result['yester_output'] = $yester_output['output'];
        }
        $to_output = self::where('date', $date)->find();
        if ($to_output && count($to_output) > 0) {
            $result['to_output'] = $to_output['output'];
        }
        $result['count_output'] = self::sum('output');
        return $result;
    }
}
