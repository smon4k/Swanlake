<?php

namespace app\hashpower\model;

use think\Model;

class HashpowerOrder extends Base {

     /**
     * 获取我的订单列表
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getHashpowerOrderList($where, $page, $limit, $order='date desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::alias('a')->join('s_ b', 'a.hash_id=b.id')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->field("a.*")
                    ->join('hp_hashpower b', 'a.hash_id=b.id')
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->field('a.*,b.name')
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 写入订单记录
     * @param [$userId 用户ID]
     * @param [$hash_id 产品ID]
     * @param [$quantity 购买数量]
     * @param [$number 份额]
     * @param [$price 净值]
     * @param [$type 1：购买 2：赎回]
     * @param [$status 1：成功 0：失败]
     * @author qinlh
     * @since 2022-07-10
     */
    public static function setHashpowerOrder($userId='', $hash_id=0, $quantity=0, $number=0, $price=0, $type=0, $status=1) {
        if($userId > 0 && $hash_id > 0 && $quantity > 0 && $number > 0 && $price > 0) {
            try {
                $insertData = [
                    'hash_id' => $hash_id,
                    'uid' => $userId,
                    'quantity' => $quantity,
                    'number' => $number,
                    'price' => $price,
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