<?php

namespace app\api\model;

use think\Model;

class ProductOrder extends Base {


    /**
     * 写入订单记录
     * @param [$userId 用户ID]
     * @param [$product_id 产品ID]
     * @param [$quantity 购买数量]
     * @param [$number 份额]
     * @param [$networth 净值]
     * @param [$type 1：购买 2：赎回]
     * @param [$status 1：成功 0：失败]
     * @author qinlh
     * @since 2022-07-10
     */
    public static function setProductOrder($userId='', $product_id=0, $quantity=0, $number=0, $networth=0, $type=0, $status=1) {
        if($userId > 0 && $product_id > 0 && $quantity > 0 && $number > 0 && $networth > 0) {
            try {
                $insertData = [
                    'product_id' => $product_id,
                    'uid' => $userId,
                    'quantity' => $quantity,
                    'number' => $number,
                    'networth' => $networth,
                    'type' => $type,
                    'time' => date('Y-m-d H:i:s'),
                    'status' => $status ? $status : 1,
                ];
                self::insert($insertData);
                $insertId = self::getLastInsID();
                if($insertId) {
                    return true;
                }
                return false;
            } catch ( PDOException $e) {
                return false;
            }
        }
        return false;
    }
}