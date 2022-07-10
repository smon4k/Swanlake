<?php

namespace app\tools\model;

use think\Model;
use RequestService\RequestService;

class MaticReptile extends Base {

    /**
     * 获取BSC要爬取的利率数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getMaticRateNameList($type='', $fields="alias_name",$order="id") {
        if($type && $type !== "") {
            $res = self::name("matic_rate_config")->where("type", $type)->field($fields)->order($order)->select();
            if($res) {
                return $res->toArray();
            }
        }
        return [];
    }

    /**
     * 获取BSC要爬取的利率数据ID
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getMaticRateIdData($alias_name='', $type='') {
        if($alias_name && $type && $type !== "" && $alias_name !== "") {
            $res = self::name("matic_rate_config")->where(['alias_name'=>$alias_name, 'type'=>$type])->field("id")->find();
            if($res) {
                return $res['id'];
            }
        }
        return 0;
    }

    /**
     * 批量更新Matic利率数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function addMaticRateBatchData($data=[], $type='', $apry='apr') {
        $user_update_count = 0;
        $updateData = [];
        // p($data);
        foreach ($data as $key => $val) {
            $id = self::getMaticRateIdData($val['name'], $type);
            if($id > 0) {
                if($apry == 'apr') {
                    $num = $val['value'];
                    if(strpos($num, "%") >= 0) {
                        $num = str_replace('%', '', $num);
                    }
                    if(strpos($num, ",") >= 0) {
                        $num = str_replace(',', '', $num);
                    }
                    $updateData[$key] = [
                        'id' => $id,
                        // 'name' => $val['name'],
                        'apr' => $num/100,
                        'update_time' => date("Y-m-d H:i:s")
                    ];
                } else {
                    $num = $val['value'];
                    if(strpos($num, "%") >= 0) {
                        $num = str_replace('%', '', $num);
                    }
                    if(strpos($num, ",") >= 0) {
                        $num = str_replace(',', '', $num);
                    }
                    $updateData[$key] = [
                        'id' => $id,
                        // 'name' => $val['name'],
                        'apy' => $num/100,
                        'update_time' => date("Y-m-d H:i:s")
                    ];
                }
            }
        }
        $sql_exam = self::batchUpdate("bit_matic_rate_config", $updateData, 'id');
        $user_update_count += self::name("matic_rate_config")->execute($sql_exam);
        return $user_update_count;
    }

    /**
     * 获取BSC利率数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getMaticRateData($type='') {
        $where = [];
        $where['start'] = 1;
        if($type && $type !== "") {
            $where['type'] = $type;
        }
        $res = self::name("matic_rate_config")->where($where)->field("id,name,apr,apy")->select();
        if($res) {
            return $res->toArray();
        }
        return [];
    }
}