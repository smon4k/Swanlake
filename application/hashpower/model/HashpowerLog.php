<?php
// +----------------------------------------------------------------------
// | 文件说明：H2O-算力币日志 市场 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-03-07
// +----------------------------------------------------------------------
namespace app\hashpower\model;

use think\Model;

class HashpowerLog extends Base
{

    /**
     * 获取算力购买记录列表数据
     * @author qinlh
     * @since 2022-03-07
     */
    public static function getHashPowerLogList($where, $page, $limit, $order='time desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::name('hashpower_log')
                    ->where($where)
                    ->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('hashpower_log')
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->field("*")
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 记录购买记录
     * @author qinlh
     * @since 2022-03-13
     */
    public static function setPurchaseLog($hashId=0, $address='', $amount=0, $hash='')
    {
        if ($hashId > 0 && $address !== '' && $amount > 0) {
            $insertData = [
                'hash_id' => $hashId,
                'address' => $address,
                'amount' => $amount,
                'ip' => getRealIp(),
                'hash' => $hash,
                'time' => date('Y-m-d H:i:s'),
                'status' => 1
            ];
            self::insert($insertData);
            $insertId = self::getLastInsID();
            if ($insertId >= 0) {
                return true;
            }
        }
        return false;
    }
}
