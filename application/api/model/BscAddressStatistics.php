<?php
// +----------------------------------------------------------------------
// | 文件说明：获取链上指定币种地址数量统计
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-10-22
// +----------------------------------------------------------------------
namespace app\api\model;

use think\Model;

class BscAddressStatistics extends Base
{

    /**
     * 每小时写入统计数据
     * @author qinlh
     * @since 2022-10-22
     */
    public static function setBscAddressStatistics($name='', $token='', $data=[]) {
        if($data && count((array)$data) > 0) {
            $date_hour = date('Y-m-d H:00:00');
            $date_max = strtotime(date('Y-m-d H:05:00'));
    
            $time = time();
            $date_hour_now = strtotime($date_hour . "+1 hours");
    
            if($time > $date_max) {
                $date = date("Y-m-d H:00:00", $date_hour_now);
            } else {
                $date = $date_hour;
            }
            $lessRes = self::where(['name' => $name, 'date' => ['<', $date]])->order('date desc')->find()->toArray();
            if($lessRes && count((array)$lessRes) > 0) {
                $res = self::where(['name' => $name, 'date' => $date])->find();
                if($res && count((array)$res) > 0) {
                    $saveRes = self::where(['name' => $name, 'date' => $date])->update([
                        'price' => $data['price'], 
                        'holders' => $data['holders'], 
                        'add_holders' => (float)$data['holders'] - (float)$lessRes['holders'], 
                        'time' => date('Y-m-d H:i:s')
                    ]);
                    if($saveRes !== false) {
                        return true;
                    }   
                } else {
                    $insertData = [
                       'name' => $name,
                       'token' => $token,
                       'price' => $data['price'],
                       'holders' => $data['holders'],
                       'add_holders' => (float)$data['holders'] - (float)$lessRes['holders'], 
                       'date' => $date,
                       'time' => date('Y-m-d H:i:s'),
                    ];
                    // p($insertData);
                    $saveRes = self::insertGetId($insertData);
                    if($saveRes) {
                        return true;
                    }
                }
            } else {
                $insertData = [
                    'name' => $name,
                    'token' => $token,
                    'price' => $data['price'],
                    'holders' => $data['holders'],
                    'add_holders' => 0, 
                    'date' => $date,
                    'time' => date('Y-m-d H:i:s'),
                 ];
                 // p($insertData);
                 $saveRes = self::insertGetId($insertData);
                 if($saveRes) {
                     return true;
                 }
            }
        }
    }

    /**
     * 模拟数据
     * @author qinlh
     * @since 2022-10-22
     */
    public static function AnalogData() {
        $date = "2022-10-29";
        $num = 23;
        // $name = 'Cake';
        $name = 'BNB';
        // $token = '0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82';
        $token = '0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c';
        for ($i=0; $i <= $num; $i++) {
            $res = self::where(['name' => $name, 'date' => ['<', $date . " " . $i . "00:00"]])->order('date desc')->find();
            $date_val = $date . " " . $i . ":00:00";
            if($res && count((array)$res) > 0) {
                $price = 0 + mt_rand()/mt_getrandmax() * (1-0);
                $add_holders = rand(20, 80);
                $insertData = [
                    'name' => $name,
                    'token' => $token,
                    'price' => $res['price'] + $price,
                    'holders' => $res['holders'] + $add_holders,
                    'add_holders' => $add_holders,
                    'date' => $date_val,
                    'time' => date('Y-m-d H:i:s'),
                ];
                // p($insertData);
                self::insertGetId($insertData);
            } else {
                $insertData = [
                    'name' => $name,
                    'token' => $token,
                    'price' => 269.98923355,
                    'holders' => 1717807,
                    'add_holders' => 0,
                    'date' => $date_val,
                    'time' => date('Y-m-d H:i:s'),
                ];
                self::insertGetId($insertData);
            }
        }
    }
    
    /**
     * 获取每小时数据
     * @author qinlh
     * @since 2022-10-23
     */
    public static function getHourDataList($where, $page, $limit, $order='id desc') {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::alias('a')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->field("a.*")
                    ->where($where)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        // p($lists);
        $times = [];
        $prices = [];
        $holders = [];
        $addAddress = [];
        $min_price = $lists[0]['price'];
        $max_price = $lists[0]['price'];
        $min_holders = $lists[0]['holders'];
        $max_holders = $lists[0]['holders'];
        $min_addArddress = $lists[0]['add_holders'];
        $max_addArddress = $lists[0]['add_holders'];
        foreach ($lists as $key => $val) {
            $times[] = date('Y-m-d H:i',strtotime($val['date']));
            $prices[] = $val['price'];
            if($val['price'] < $min_price) {
                $min_price = $val['price'];
            }
            if($val['price'] > $max_price) {
                $max_price = $val['price'];
            }
            $holders[] = $val['holders'];
            if($val['holders'] < $min_holders) {
                $min_holders = $val['holders'];
            }
            if($val['holders'] > $max_holders) {
                $max_holders = $val['holders'];
            }
            $addAddress[] = $val['add_holders'];
            if($val['add_holders'] < $min_addArddress) {
                $min_addArddress = $val['add_holders'];
            }
            if($val['add_holders'] > $max_addArddress) {
                $max_addArddress = $val['add_holders'];
            }
        }
        $dataList = [
            'times' => $times, 
            'prices' => [
                'data' => $prices,
                'min' => $min_price,
                'max' => $max_price,
            ], 
            'holders' => [
                'data' => $holders,
                'min' => $min_holders,
                'max' => $max_holders,
            ], 
            'addAddress' => [
                'data' => $addAddress,
                'min' => $min_addArddress,
                'max' => $max_addArddress,
            ]];
        // p($dataList);
        return $dataList;
    }

    
}
