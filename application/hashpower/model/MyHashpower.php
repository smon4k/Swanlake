<?php

namespace app\hashpower\model;

use think\Model;
use app\api\model\User;

class MyHashpower extends Base {


    /**
     * 开始质押
     * @param [$address 钱包地址]
     * @param [$hash_id 算力币id]
     * @param [$number 份额]
     * @param [$type 1：投注 2：赎回]
     * @author qinlh
     * @since 2022-07-08
     */
    public static function startInvestNow($address='', $hash_id=0, $number=0, $type=0) {
        if($address !== '' || $hash_id > 0 || $number > 0 && $type > 0) {
            $userId = User::getUserAddress($address);
            self::startTrans();
            try {
                if($userId) {
                    $poolBtcData = Hashpower::getPoolBtc();
                    $price = $poolBtcData['currency_price']; 
                    $buy_number = 0; //usdt 数量
                    $buy_number = (float)$price > 0 ? (float)$number * (float)$price : (float)$number; //计算购买数量
                    $myHashpower = self::getMyHashpower($hash_id, $userId);
                    $insertId = 0;
                    if ($myHashpower && count((array)$myHashpower) > 0) { //已存在 进行更新数据
                        // $insertId = $myProduct['id'];
                        if($type == 1) { //投注
                            $update = [
                                'total_invest' => (float)$myHashpower['total_invest'] + (float)$buy_number,
                                'total_number' => (float)$myHashpower['total_number'] + (float)$number,
                                'up_time' => date('Y-m-d H:i:s')
                            ];
                            if($myHashpower['buy_price'] <= 0) {
                                $update['buy_price'] = $price;
                            }
                        } else { //赎回
                            $update = [
                                'total_invest' => (float)$myHashpower['total_invest'] - (float)$buy_number,
                                'total_number' => (float)$myHashpower['total_number'] - (float)$number,
                                'up_time' => date('Y-m-d H:i:s')
                            ];
                        }
                        $res = self::where('id', $myHashpower['id'])->update($update);
                    } else {  //不存在 添加
                        if($type == 2) {
                            return false;
                        }
                        $insertData = [
                            'hash_id' => $hash_id,
                            'uid' => $userId,
                            'hash_balance' => 0,
                            'total_invest' => $buy_number,
                            'total_number' => $number,
                            'buy_price' => $price,
                            'time' => date('Y-m-d H:i:s'),
                            'up_time' => date('Y-m-d H:i:s'),
                        ];
                        self::insert($insertData);
                        $res = self::getLastInsID();
                    }
                    if($res) {
                        $orderLog = HashpowerOrder::setHashpowerOrder($userId, $hash_id, $buy_number, $number, $price, $type);
                        if($orderLog) {
                            self::commit();
                            return true;
                        }
                    }
                }
                self::rollback();
                return false;
                } catch ( PDOException $e) {
                    self::rollback();
                    return false;
                }
        }
        return false;
    }

    /**
     * 修改我的算力币购买余额
     * @author qinlh
     * @since 2022-11-06
     */
    public static function setHashBalance($hash_id=0, $address='', $number=0) {
        if($hash_id && $address !== '' && $number) {
            $userId = User::getUserAddress($address);
            $myHashpower = self::getMyHashpower($hash_id, $userId);
            if ($myHashpower && count((array)$myHashpower) > 0) { //已存在 进行更新数据
                $res = self::where('id', $myHashpower['id'])->setInc('hash_balance', $number);
            } else {
                $insertData = [
                    'hash_id' => $hash_id,
                    'uid' => $userId,
                    'hash_balance' => $number,
                    'total_invest' => 0,
                    'total_number' => 0,
                    'buy_price' => 0,
                    'time' => date('Y-m-d H:i:s'),
                    'up_time' => date('Y-m-d H:i:s'),
                ];
                self::insert($insertData);
                $res = self::getLastInsID();
            }
            if($res) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取我的质押数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getMyHashpowerList($where, $page, $limit, $order='id desc', $userId=0)
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::alias('a')->join('hp_hashpower b', 'a.hash_id = b.id')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->join('hp_hashpower b', 'a.hash_id = b.id')
                    ->field("a.*,b.name,b.currency")
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        $poolBtcData = Hashpower::getPoolBtc();
        // p($poolBtcData);
        $price = isset($poolBtcData['currency_price']) ? $poolBtcData['currency_price'] : 0; 
        $incomeArr = Hashpower::calcBtcIncome(); //获取收益
        foreach ($lists as $key => $val) {
            //获取是否今天刚投注
            $lists[$key]['daily_income'] = $incomeArr['dailyIncome']; //日收益
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 获取用户对应产品是否已投注
     * @author qinlh
     * @since 2022-07-09
     */
    public static function getMyHashpower($hash_id=0, $uid=0) {
        if($hash_id > 0 && $uid > 0) {
            $res = self::where(['hash_id'=>$hash_id, 'uid'=>$uid])->find();
            if($res && count((array)$res) > 0) {
                return $res;
            } else {
                return false;
            }
        }
        return false;
    }  

    /**
     * 获取产品总的投资数量
     * @author qinlh
     * @since 2022-09-03
     */
    public static function getSumProductTotalInvest($hash_id=0) {
        $num = 0;
        if($hash_id) {
            $num = self::where('hash_id', $hash_id)->sum('total_invest');
        } else {
            $num = self::sum('total_invest');
        }
        return $num;
    }
}