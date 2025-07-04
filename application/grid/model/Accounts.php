<?php
// +----------------------------------------------------------------------
// | 文件说明：账户管理
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-04-11
// +----------------------------------------------------------------------
namespace app\grid\model;

use think\Model;
use RequestService\RequestService;

class Accounts extends Base
{
    /**
     * 获取指定账户列表数据
     * @author qinlh
     * @since 2023-01-31
     */
    public static function getAccountList($where=[]) {
        $data = self::where($where)->select();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }

    /**
     * 添加账户数据
     * @since 2024-10-14
     */
    public static function addQuantityAccount($name, $api_key, $secret_key, $api_passphrase, $exchange, $is_position, $user_id=0) {
        try {
            $IsResData = self::where('name', $name)->find();
            if($IsResData && count((array)$IsResData) > 0) {
                $updateData = [
                    'api_key' => $api_key,
                    'api_secret' => $secret_key,
                    'api_passphrase' => $api_passphrase,
                    'status' => 1,
                    'exchange' => $exchange,
                    'add_time' => date('Y-m-d H:i:s'),
                    'is_position' => $is_position
                ];
                $res = self::where('id', $IsResData['id'])->update($updateData);
                if($res) {
                    return true;
                }
            } else {
                $insertData = [
                    'name' => $name,
                    'api_key' => $api_key,
                    'api_secret' => $secret_key,
                    'api_passphrase' => $api_passphrase,
                    'status' => 1,
                    'exchange' => $exchange,
                    'add_time' => date('Y-m-d H:i:s'),
                    'is_position' => $is_position
                ];
                self::insert($insertData);
                $insertId = self::getLastInsID();
                if($insertId) {
                    $balanceList = QuantifyAccount::getOkxTradePairBalance($insertData);
                    if($balanceList && count((array)$balanceList) > 0) {
                        QuantifyAccount::calcQuantifyAccountData($insertId, 1, $balanceList['usdtBalance'], '第一笔入金');
                    }
                    return true;
                }
            }
            return false;
        } catch ( PDOException $e) {
            return false;
        }
    }


    /**
     * 删除账户 修改账户状态
     * @since 2024-10-14
     */
    public static function delAccount($id) {
        $res = self::where('id', $id)->update(['status' => 0]);
        if($res) {
            return true;
        }
        return false;
    }

    /**
     * 只获取status为1的用户数据
     * @since 2025-7-3
     */
    public static function getAccountStatus1List() {
        $data = self::where('status', 1)->select();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }
    
    
}