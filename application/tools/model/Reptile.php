<?php

namespace app\tools\model;

use think\Model;
use RequestService\RequestService;

class Reptile extends Base
{

    /**
     * 获取BSC要爬取的利率数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getBscRateNameList($type='')
    {
        if ($type && $type !== "") {
            $res = self::name("bsc_rate_config")->where(['type'=>$type, 'start'=>1])->field("alias_name")->select();
            if ($res) {
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
    public static function getBscRateIdData($alias_name='', $type='')
    {
        if ($alias_name && $type && $type !== "" && $alias_name !== "") {
            $res = self::name("bsc_rate_config")->where(['alias_name'=>$alias_name, 'type'=>$type])->field("id")->find();
            if ($res) {
                return $res['id'];
            }
        }
        return 0;
    }

    /**
     * 批量更新BSC利率数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function addBscRateBatchData($data=[], $type='', $status=1)
    {
        $user_update_count = 0;
        $updateData = [];
        // p($type);
        foreach ($data as $key => $val) {
            $id = self::getBscRateIdData($val['name'], $type);
            if ($id > 0) {
                if ($status == 1) {
                    $updateData[$key] = [
                        'id' => $id,
                        // 'name' => $val['name'],
                        'apr' => (float)$val['value']/100,
                        'update_time' => date("Y-m-d H:i:s")
                    ];
                } else {
                    $updateData[$key] = [
                        'id' => $id,
                        // 'name' => $val['name'],
                        'apy' => (float)$val['value']/100,
                        'update_time' => date("Y-m-d H:i:s")
                    ];
                }
            }
        }
        // p($updateData);
        $sql_exam = self::batchUpdate("bit_bsc_rate_config", $updateData, 'id');
        // echo $sql_exam;die;
        $user_update_count += self::name("bsc_rate_config")->execute($sql_exam);
        return $user_update_count;
    }

    /**
     * 批量更新Pancake交易费用数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function updateTradingFeesData($data=[], $type='')
    {
        $user_update_count = 0;
        $updateData = [];
        // p($type);
        foreach ($data as $key => $val) {
            $id = self::getBscRateIdData($val['name'], $type);
            if ($id > 0) {
                $updateData[$key] = [
                    'id' => $id,
                    // 'name' => $val['name'],
                    'fees_apr' => (float)$val['fees_apr'],
                    'fees_base_apr' => (float)$val['fees_base_apr'],
                    'update_time' => date("Y-m-d H:i:s")
                ];
            }
        }
        // p($updateData);
        $sql_exam = self::batchUpdate("bit_bsc_rate_config", $updateData, 'id');
        // echo $sql_exam;die;
        $user_update_count += self::name("bsc_rate_config")->execute($sql_exam);
        return $user_update_count;
    }

    /**
     * 获取BSC利率数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getBscRateData($type='', $field='id,name,apr,apy,type,alias_name')
    {
        $where = [];
        $where['start'] = 1;
        if ($type && $type !== "") {
            $where['type'] = $type;
        }
        $res = self::name("bsc_rate_config")->where($where)->field($field)->select();
        if ($res) {
            return $res->toArray();
        }
        return [];
    }

    /**
     * 爬取MDEX APR API 接口数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getMdexRateApiData($type='')
    {
        if ($type && $type !== "") {
            try {
                $mdexKeyData = self::getBscRateNameList($type);
                // $bsc_url = "https://gateway.mdex.cc/v3/mingpool/lps?mdex_chainid=56";
                $mdex_url = "https://gateway.mdex.one/v3/mingpool/lps?mdex_chainid=56";
                $bsc_response_string = file_get_contents($mdex_url);
                $returnBscArray = json_decode($bsc_response_string, true);
                // p($mdexKeyData);
                // $heco_url = "https://gateway.mdex.cc/v2/mingpool/lps?mdex_chainid=128";
                // $heco_response_string = file_get_contents($heco_url);
                // $returnHecoArray = json_decode($heco_response_string, true);
                $returnBscAprData = [];
                // $returnHecoAprData = [];
                //获取BSC链数据
                if ($returnBscArray && $returnBscArray['result'] && count((array)$returnBscArray['result']) > 0) {
                    foreach ($returnBscArray['result'] as $key => $val) {
                        if (isset($val['base_name'])) {
                            foreach ($mdexKeyData as $kk => $vv) {
                                if ($val['base_name'] === $vv['alias_name']) {
                                    $returnBscAprData[$kk]['name'] = $val['base_name'];
                                    $returnBscAprData[$kk]['value'] = sprintf("%.4f", ($val['pool_apy'] + $val['swap_apy']) * 365);
                                }
                            }
                        }
                    }
                }

                // //获取HECO链数据
                // if($returnHecoArray && $returnHecoArray['result'] && count((array)$returnHecoArray['result']) > 0) {
                //     foreach ($returnHecoArray['result'] as $key => $val) {
                //         if(isset($val['base_name'])) {
                //             if($val['base_name'] === "WHT/USDT") { //目前只有 WHT/USDT
                //                 $returnHecoAprData[0]['name'] = $val['base_name'];
                //                 $returnHecoAprData[0]['value'] = sprintf("%.4f", $val['pool_apy'] * 365);
                //             }
                //         }
                //     }
                // }
                // $returnAprData = array_merge($returnBscAprData, $returnHecoAprData);
                // p($returnBscAprData);
                if ($returnBscAprData && count((array)$returnBscAprData) > 0) { //开始更新数据
                    self::addBscRateBatchData($returnBscAprData, $type);
                }
                return true;
            } catch (\Exception $e) {
                logger("R \r\n".$e);
                return false;
            }
        }
        return;
    }

    /**
     * 爬取BZX APR API 接口数据
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getBzxRateApiData($type='')
    {
        if ($type && $type !== "") {
            try {
                $bzxKeyData = self::getBscRateNameList($type);
                $url = "https://api.bzx.network/v1/farming-pools-info?networks=bsc";
                $bsc_response_string = file_get_contents($url);
                $returnBscArray = json_decode($bsc_response_string, true);
                // p($returnBscArray);
                $returnBscAprData = [];
                //获取BSC链数据
                if ($returnBscArray && $returnBscArray['data'] && $returnBscArray['data']['bsc'] && $returnBscArray['data']['bsc']['pools'] && count((array)$returnBscArray['data']['bsc']['pools']) > 0) {
                    foreach ($returnBscArray['data']['bsc']['pools'] as $key => $val) {
                        if (isset($val['lpToken'])) {
                            foreach ($bzxKeyData as $kk => $vv) {
                                // p($vv);
                                if ($val['lpToken'] === $vv['alias_name']) {
                                    $returnBscAprData[$kk]['name'] = $val['lpToken'];
                                    $returnBscAprData[$kk]['value'] = sprintf("%.4f", $val['aprCombined']);
                                }
                            }
                        }
                    }
                }
                // p($returnBscAprData);
                if ($returnBscAprData && count((array)$returnBscAprData) > 0) { //开始更新数据
                    self::addBscRateBatchData($returnBscAprData, $type);
                }
                return true;
            } catch (\Exception $e) {
                logger("R \r\n".$e);
                return false;
            }
        }
        return;
    }
}
