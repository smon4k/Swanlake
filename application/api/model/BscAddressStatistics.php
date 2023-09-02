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
     * 获取币种地址列表
     * @author qinlh
     * @since 2022-11-04
     */
    public static function getTokensList() {
        $data = self::name('bsc_tokens')->where('start', 1)->order('sort asc')->select()->toArray();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

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
            $lessRes = self::where(['name' => $name, 'date' => ['<=', $date]])->order('date desc')->find();
            if($lessRes && count((array)$lessRes) > 0) {
                // p($lessRes);
                $res = self::where(['name' => $name, 'date' => $date])->find();
                // p($res);
                if($res && count((array)$res) > 0) {
                    $saveRes = self::where(['name' => $name, 'date' => $date])->update([
                        'price' => $data['price'], 
                        'holders' => $data['holders'], 
                        'holders_two' => $data['holders_two'], 
                        'add_holders' => (float)$data['holders'] - (float)$lessRes['holders'], 
                        'balance' => $data['balance'],
                        'add_balance' => $lessRes['balance'] == 0 ? 0 : (float)$data['balance'] - (float)$lessRes['balance'],
                        'value' => $data['value'],
                        'other_data' => $data['other_data'],
                        'time' => date('Y-m-d H:i:s')
                    ]);
                    if($saveRes !== false) {
                        $setDateDataRes = self::setBscAddressDateStatistics($name, $token, $data);
                    }   
                } else {
                    $insertData = [
                        'name' => $name,
                        'token' => $token,
                        'price' => $data['price'],
                        'holders' => $data['holders'],
                        'holders_two' => $data['holders_two'],
                        'add_holders' => (float)$data['holders'] - (float)$lessRes['holders'], 
                        'balance' => $data['balance'],
                        'add_balance' => $lessRes['balance'] == 0 ? 0 : (float)$data['balance'] - (float)$lessRes['balance'],
                        'value' => $data['value'],
                        'other_data' => $data['other_data'],
                        'date' => $date,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                    // p($insertData);
                    $saveRes = self::insertGetId($insertData);
                    if($saveRes) {
                        $setDateDataRes = self::setBscAddressDateStatistics($name, $token, $data);
                    }
                }
            } else {
                $insertData = [
                    'name' => $name,
                    'token' => $token,
                    'price' => $data['price'],
                    'holders' => $data['holders'],
                    'holders_two' => $data['holders_two'],
                    'add_holders' => 0, 
                    'balance' => $data['balance'],
                    'add_balance' => 0,
                    'value' => $data['value'],
                    'other_data' => $data['other_data'],
                    'date' => $date,
                    'time' => date('Y-m-d H:i:s'),
                 ];
                //  p($insertData);
                 $saveRes = self::insertGetId($insertData);
                 if($saveRes) {
                    $setDateDataRes = self::setBscAddressDateStatistics($name, $token, $data);
                 }
            }
            if($setDateDataRes) {
                return true;
            }
        }
    }

    /**
     * 每天写入统计数据
     * @author qinlh
     * @since 2022-10-22
     */
    public static function setBscAddressDateStatistics($name='', $token='', $data=[]) {
        if($data && count((array)$data) > 0) {
            $date = date("Y-m-d");
            $yerstDate = date('Y-m-d', strtotime("-1 days", strtotime($date)));

            // p($date);
            $lessRes = self::name('bsc_address_statistics_date')->where(['name' => $name, 'date' => $yerstDate])->order('date desc')->find();
            if($lessRes && count((array)$lessRes) > 0) {
                $res = self::name('bsc_address_statistics_date')->where(['name' => $name, 'date' => $date])->find();
                if($res && count((array)$res) > 0) {
                    $saveRes = self::name('bsc_address_statistics_date')->where(['name' => $name, 'date' => $date])->update([
                        'price' => $data['price'], 
                        'holders' => $data['holders'], 
                        'holders_two' => $data['holders_two'],
                        'add_holders' => (float)$data['holders'] - (float)$lessRes['holders'], 
                        'balance' => $data['balance'],
                        'add_balance' => $lessRes['balance'] == 0 ? 0 : (float)$data['balance'] - (float)$lessRes['balance'],
                        'value' => $data['value'],
                        'other_data' => $data['other_data'],
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
                        'holders_two' => $data['holders_two'],
                        'add_holders' => (float)$data['holders'] - (float)$lessRes['holders'], 
                        'balance' => $data['balance'],
                        'add_balance' => $lessRes['balance'] == 0 ? 0 : (float)$data['balance'] - (float)$lessRes['balance'],
                        'value' => $data['value'],
                        'other_data' => $data['other_data'],
                        'date' => $date,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                    // p($insertData);
                    $saveRes = self::name('bsc_address_statistics_date')->insertGetId($insertData);
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
                    'holders_two' => $data['holders_two'],
                    'add_holders' => 0, 
                    'balance' => $data['balance'],
                    'add_balance' => 0,
                    'value' => $data['value'],
                    'other_data' => $data['other_data'],
                    'date' => $yerstDate,
                    'time' => date('Y-m-d H:i:s'),
                 ];
                 // p($insertData);
                 $saveRes = self::name('bsc_address_statistics_date')->insertGetId($insertData);
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
     * 总地址和新增地址
     * @author qinlh
     * @since 2022-10-23
     */
    public static function getHourDataList($name='', $time_range='', $this_year='', $start_time='', $end_time='') {
        // p($time_range);
        $sql = "";
        if($time_range && $time_range !== '') {
            if($time_range === '1 day') {
                // $sql .= " AND to_days( `time`) = to_days(NOW())";
                $sql .= "SELECT * FROM s_bsc_address_statistics WHERE `name`='$name' AND time >= (NOW() - interval 24 hour)";
            } else {
                $sql .= "SELECT * FROM s_bsc_address_statistics_date WHERE `name`='$name' AND DATE_SUB( curdate(), INTERVAL $time_range ) <= `time`";
            }
        } else {
            $sql .= "SELECT * FROM s_bsc_address_statistics_date WHERE `name`='$name'";
        }
        if($this_year && $this_year !== '') {
            $sql .= " AND YEAR(`time`) = YEAR(NOW())";
        }
        if($start_time !== '' && $end_time !== '') {
            $start_time = $start_time . " 00:00:00";
            $end_time = $end_time . " 23:59:59";
            $sql .= " AND `time` >= '$start_time' AND `time` <= '$end_time'";
        }
        $sql .= " ORDER BY time asc";
        $lists = self::query($sql);
        if (!$lists || count((array)$lists) <= 0) {
            return [];
        }
        // p($lists);
        $times = [];
        $prices = [];
        $holders = [];
        $holdersTwo = [];
        $min_holders_two = $lists[0]['holders_two'];
        $max_holders_two = $lists[0]['holders_two'];
        $addAddress = [];
        $otherData = [];
        $min_price = $lists[0]['price'];
        $max_price = $lists[0]['price'];
        $min_holders = $lists[0]['holders'];
        $max_holders = $lists[0]['holders'];
        $min_addArddress = $lists[0]['add_holders'];
        $max_addArddress = $lists[0]['add_holders'];
        $addHolders = 0; //新增地址数
        $count = count((array)$lists);
        foreach ($lists as $key => $val) {
            if($time_range === '1 day') {
                $times[] = date('Y-m-d H:i',strtotime($val['date']));
            } else {
                $times[] = date('Y-m-d',strtotime($val['date']));
            }
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
            $holdersTwo[] = $val['holders_two'];
            if($val['holders_two'] < $min_holders_two) {
                $min_holders_two = $val['holders_two'];
            }
            if($val['holders_two'] > $max_holders_two) {
                $max_holders_two = $val['holders_two'];
            }
            if($val['holders'] > $max_holders) {
                $max_holders = $val['holders'];
            }
            $addAddress[] = $val['add_holders'];
            $addHolders += (float)$val['add_holders'];
            if($val['add_holders'] < $min_addArddress) {
                $min_addArddress = $val['add_holders'];
            }
            if($val['add_holders'] > $max_addArddress) {
                $max_addArddress = $val['add_holders'];
            }
            $other_data_arr = json_decode($val['other_data'], true);
            if($other_data_arr && count((array)$other_data_arr) > 0) {
                foreach ($other_data_arr as $k => $v) {
                    $otherData[$k][] = $v;
                    if($v < $min_holders_two) {
                        $min_holders_two = $v;
                    }
                }
            }
            // $otherData[] = json_decode($val['other_data'], true);
        }
        // p($otherData);
        // $formerlyHolders = (float)$lists[0]['holders'] - (float)$lists[0]['add_holders']; //计算原来的地址数量
        $formerlyHolders = (float)$lists[$count - 1]['holders'] - (float)$lists[0]['holders']; //计算原来的地址数量
        // $addPercentage = $addHolders / $formerlyHolders * 100; //计算新增百分比
        $addPercentage = $formerlyHolders / (float)$lists[0]['holders'] * 100; //计算新增百分比
        
        // p($addPercentage);
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
                'add_holders' => $formerlyHolders,
                'add_percentage' => $addPercentage,
            ], 
            'holders_two' => [
                'data' => $holdersTwo,
                'min' => $min_holders_two,
                'max' => $max_holders_two,
                'add_holders' => 0,
                'add_percentage' => 0,
            ], 
            'addAddress' => [
                'data' => $addAddress,
                'min' => $min_addArddress,
                'max' => $max_addArddress,
            ],
            'other_data' => $otherData,
        ];
        // p($dataList);
        return $dataList;
    }

    /**
     * 获取每小时数据
     * 销毁数
     * @author qinlh
     * @since 2022-10-23
     */
    public static function getDestructionDataList($name='', $time_range='', $this_year='', $start_time='', $end_time='') {
        // p($time_range);
        $sql = "";
        if($time_range && $time_range !== '') {
            if($time_range === '1 day') {
                // $sql .= " AND to_days( `time`) = to_days(NOW())";
                $sql .= "SELECT * FROM s_bsc_address_statistics WHERE `name`='$name' AND time >= (NOW() - interval 24 hour)";
            } else {
                $sql .= "SELECT * FROM s_bsc_address_statistics_date WHERE `name`='$name' AND DATE_SUB( curdate(), INTERVAL $time_range ) <= `time`";
            }
        } else {
            $sql .= "SELECT * FROM s_bsc_address_statistics_date WHERE `name`='$name'";
        }
        if($this_year && $this_year !== '') {
            $sql .= " AND YEAR(`time`) = YEAR(NOW())";
        }
        if($start_time !== '' && $end_time !== '') {
            $start_time = $start_time . " 00:00:00";
            $end_time = $end_time . " 00:00:00";
            $sql .= " AND `time` >= '$start_time' AND `time` <= '$end_time'";
        }
        $sql .= " ORDER BY time asc";
        // p($sql);
        $lists = self::query($sql);
        if (!$lists || count((array)$lists) <= 0) {
            return [];
        }
        // p($lists);
        $times = [];

        //销毁统计
        $balances = [];
        $addBalances = [];
        $values = [];
        $prices = [];
        $min_price = $lists[0]['price'];
        $max_price = $lists[0]['price'];
        $min_balance = $lists[0]['balance'];
        $max_balance = $lists[0]['balance'];
        $min_addBalance = $lists[0]['add_balance'];
        $max_addBalance = $lists[0]['add_balance'];
        $min_value = $lists[0]['value'];
        $max_value = $lists[0]['value'];
        $addDestroy = 0; //新增销毁数
        $count = count((array)$lists);
        foreach ($lists as $key => $val) {
            if($time_range === '1 day') {
                $times[] = date('Y-m-d H:i',strtotime($val['date']));
            } else {
                $times[] = date('Y-m-d',strtotime($val['date']));
            }

            //销毁统计
            $prices[] = $val['price'];
            if($val['price'] < $min_price) {
                $min_price = $val['price'];
            }
            if($val['price'] > $max_price) {
                $max_price = $val['price'];
            }
            $balances[] = $val['balance'];
            if($val['balance'] < $min_balance) {
                $min_balance = $val['balance'];
            }
            if($val['balance'] > $max_balance) {
                $max_balance = $val['balance'];
            }
            $addBalances[] = $val['add_balance'];
            $addDestroy += (float)$val['add_balance'];
            if($val['add_balance'] < $min_addBalance) {
                $min_addBalance = $val['add_balance'];
            }
            if($val['add_balance'] > $max_addBalance) {
                $max_addBalance = $val['add_balance'];
            }
            $values[] = $val['value'];
            if($val['value'] < $min_value) {
                $min_value = $val['value'];
            }
            if($val['value'] > $max_value) {
                $max_value = $val['value'];
            }
        }
        $bnbNewValues = 0; //BNB最新的总销毁量
        $autoDestruction = 41891077; //BNB自动销毁量
        // if($name === 'BNB') {
            $bnbNewValues = $lists[count((array)$lists) - 1]['balance']; //
        // }

        // $formerlyHolders = (float)$lists[0]['holders'] - (float)$lists[0]['add_holders']; //计算原来的地址数量
        $formerlyHolders = (float)$lists[$count - 1]['balance'] - (float)$lists[0]['balance']; //计算原来的地址数量
        // $addPercentage = $addHolders / $formerlyHolders * 100; //计算新增百分比
        $addPercentage = (float)$lists[0]['balance'] > 0 ? $formerlyHolders / (float)$lists[0]['balance'] * 100 : 0; //计算新增百分比
        $dataList = [
            'times' => $times, 
            'prices' => [
                'data' => $prices,
                'min' => $min_price,
                'max' => $max_price,
            ], 
            'balances' => [
                'data' => $balances,
                'min' => $min_balance,
                'max' => $max_balance,
                'add_destroy' => $formerlyHolders,
                'add_percentage' => $addPercentage,
            ],
            'addBalances' => [
                'data' => $addBalances,
                'min' => $min_addBalance,
                'max' => $max_addBalance,
            ],
            'values' => [
                'data' => $values,
                'min' => $min_value,
                'max' => $max_value,
            ],
            'bnbNewValues' => $bnbNewValues,
            'autoDestruction' => $autoDestruction,
        ];
        // p($dataList);
        return $dataList;
    }

    public static function delTimeData() {
        // $data = self::name('bsc_address_statistics_date')->select()->toArray();
        self::startTrans();
        // //首先删除数据
        // foreach ($data as $key => $val) {
        //     $arr = explode(' ', $val['date']);
        //     if($arr[1] !== '00:00:00') {
        //         $delRes = self::name('bsc_address_statistics_date')->where('id', $val['id'])->delete();
        //         if($delRes) {
        //             echo "删除【".$val['id'] . "-" . $val['date']."】成功\r\n";
        //         }
        //     }
        // }
        // self::commit();
        // die;

        // $dataArr2 = self::name('bsc_address_statistics_date')->select()->toArray();
        // // 修改日期
        // foreach ($dataArr2 as $key => $val) {
        //     $date = $val['date'];
        //     $yerstDate = date('Y-m-d', strtotime("-1 days", strtotime($date)));
        //     $saveRes = self::name('bsc_address_statistics_date')->where('id', $val['id'])->setField('date', $yerstDate);
        //     if($saveRes) {
        //         echo "修改【".$val['id'] . "-" . $val['date']."】成功\r\n";
        //     }
        // }
        // self::commit();
        // die;

        $dataArr3 = self::name('bsc_address_statistics_date')->select()->toArray();
        // 修改每日新增量
        $newArray = [];
        foreach ($dataArr3 as $key => $val) {
            $newArray[$val['name']][] = $val;
        }
        p($newArray);
        foreach ($newArray as $key => $val) {
            $add_holders = 0;
            $add_balance = 0;
            $holders = 0;
            $balance = 0;
            foreach ($val as $k => $v) {
                if($k == 0) {
                    $where = [];
                    $where['date'] = ['like',"%" . $v['date'] . "%"];
                    $where['name'] = $v['name'];
                    $serverAddHolders = self::name('bsc_address_statistics')->where($where)->sum('add_holders');
                    $serverAddBalance = self::name('bsc_address_statistics')->where($where)->sum('add_balance');
                    
                    //获取0点数据 数据丢失的情况下 去源数据中获取
                    // $add1_add_holders = 0;
                    // $add1_add_balance = 0;
                    // $add1Date = date('Y-m-d 00:00:00', strtotime("+1 days", strtotime($v['date'])));
                    // $add1Holders = self::name('bsc_address_statistics')->where(['date' => $add1Date, 'name' => $v['name']])->find();
                    // if($add1Holders) {
                    //     $add1_add_holders = $add1Holders['add_holders'];
                    //     $add1_add_balance = $add1Holders['add_balance'];
                    // }
                    // $add_holders = $serverAddHolders + (float)$add1_add_holders;
                    // $add_balance = $serverAddBalance + (float)$add1_add_balance;

                    $add_holders = $serverAddHolders + (float)$v['add_holders'];
                    $add_balance = $serverAddBalance + (float)$v['add_balance'];
                    $saveRes = self::name('bsc_address_statistics_date')->where('id', $v['id'])->update(['add_holders' => $add_holders, 'add_balance' => $add_balance]);
                    if($saveRes) {
                        echo "修改第一个【".$v['id'] . "】成功\r\n";
                    }
                } else {
                    $add_holders = (float)$v['holders'] - (float)$holders;
                    $add_balance = (float)$v['balance'] - (float)$balance;
                    $saveRes = self::name('bsc_address_statistics_date')->where('id', $v['id'])->setField(['add_holders' => $add_holders, 'add_balance' => $add_balance]);
                    if($saveRes !== false) {
                        echo "修改【".$v['id'] . "】成功\r\n";
                    }
                }
                $holders = $v['holders'];
                $balance = $v['balance'];
            }
        }
        self::commit();
    }
}
