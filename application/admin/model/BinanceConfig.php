<?php

// +----------------------------------------------------------------------
// | 文件说明：币安配置
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2022 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-12-05
// +----------------------------------------------------------------------

namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;
use app\tools\model\Okx;
use app\tools\model\Binance;

class BinanceConfig extends Base
{   
    /**
     * 获取配置信息
     * @author qinlh
     * @since 2022-12-05
     */
    public static function getBinanceConfig() {
        $data = self::where('id', 1)->find();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }

    /**
     * 获取涨跌比例
     * @author qinlh
     * @since 2022-12-05
     */
    public static function getChangeRatio() {
        $data = self::where('id', 1)->find();
        if($data && count((array)$data) > 0) {
            return $data['change_ratio'];
        }
        return 2;
    }

    /**
     * 修改涨跌比例
     * @author qinlh
     * @since 2022-12-05
     */
    public static function setChangeRatio($ratio=1) {
        if($ratio) {
            $res = self::where('id', 1)->update(['change_ratio' => $ratio, 'time' => date('Y-m-d H:i:s')]);
            if($res !== false) {
                return true;
            }
        }
        return false;
    }
}