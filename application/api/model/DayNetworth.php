<?php

namespace app\api\model;

use think\Model;

class DayNetworth extends Base
{
    
    /**
     * 获取今日净值
     * @author qinlh
     * @since 2022-07-08
     */
    public static function getDayNetworth($product_id=0, $date='')
    {
        if ($product_id > 0) {
            if ($date && $date !== '') {
                $data = self::where(['date'=>$date, 'product_id'=>$product_id])->find();
            } else {
                $yestDayDate = date("Y-m-d", strtotime("-1 day"));
                $toDayDate = date("Y-m-d");
                $data = self::where(['date'=>$toDayDate, 'product_id'=>$product_id])->find();
                if (!$data || count((array)$data) <= 0) {
                    $data = self::where(['date'=>$yestDayDate, 'product_id'=>$product_id])->find();
                }
            }
            if ($data && count((array)$data) > 0) {
                return $data->toArray();
            }
        }
        return [];
    }

    /**
     * 获取今日、昨日最新净值
     * 最近两天最新净值
     * @author qinlh
     * @since 2022-07-10
     */
    public static function getNewTodayYesterdayNetworth($product_id=0)
    {
        if ($product_id > 0) {
            $toDayData = 1;
            $yestDayData = 1;
            $yestDayProfit = 0;
            $data = self::where('product_id', $product_id)->order('date desc')->limit(2)->select()->toArray();
            $toDayData = $data[0]['networth']; //今日净值
            $yestDayProfit = $data[0]['profit']; //今日利润
            if ($data && count((array)$data) == 2) {
                $yestDayData = $data[1]['networth']; //昨日净值
            }
            return ['toDayData' => $toDayData, 'yestDayData' => $yestDayData, 'yestDayProfit' => $yestDayProfit];
        }
    }

    /**
     * 更新今日最新净值
     * @author qinlh
     * @since 2022-07-09
     */
    public static function saveDayNetworth($address='', $profit=0, $product_id=1, $channel_fee=0, $management_fee=0)
    {
        if ($profit >= 0) {
            $date = date("Y-m-d");
            $yestDate = date("Y-m-d",strtotime("-1 day",strtotime($date)));
            $dayNetWorth = MyProduct::calcNewsNetWorth($profit, $product_id, $date, $yestDate);
            $newDayNetWorth = valueInterceptStr($dayNetWorth, 6);
            // if ($dayNetWorth > 0) {
                $res = self::where('date', $date)->find();
                if ($res && count((array)$res) > 0) {
                    $res = self::where('date', $date)->update(['profit'=>$profit, 'networth'=>$newDayNetWorth, 'time'=>date('Y-m-d H:i:s')]);
                    if ($res !== false) {
                        $command = 'app\api\model\MyProduct::saveUserProductData(' . "'" . $date . "'" . "," . "'" . $yestDate . "'" . ');';
                        $command1 = 'app\api\model\MyProduct::saveProductListData(' . "'" . $date . "'" . "," . "'" . $yestDate . "'" . ');';
                        // p($command);
                        $desc = '更新用户产品历史净值';
                        $desc1 = '更新产品历史净值';
                        Task::addTaskData($address, $command, $desc);
                        Task::addTaskData($address, $command1, $desc1);
                        //更新资金账户监控 管理费 手续费
                        
                        return true;
                    }
                } else {
                    $buyNumber = MyProduct::getNewsBuyAmount($product_id);
                    self::insert([
                        'product_id' => $product_id,
                        'profit' => $profit,
                        'balance' => $buyNumber['yest_count_balance'],
                        'number' => $buyNumber['count_buy_number'],
                        'networth' => $newDayNetWorth,
                        'date' => $date,
                        'time' => date('Y-m-d H:i:s')
                    ]);
                    $insertId = self::getLastInsID();
                    if ($insertId > 0) {
                        $command = 'app\api\model\MyProduct::saveUserProductData(' . "'" . $date . "'" . "," . "'" . $yestDate . "'" . ');';
                        $command1 = 'app\api\model\MyProduct::saveProductListData(' . "'" . $date . "'" . "," . "'" . $yestDate . "'" . ');';
                        // p($command);
                        $desc = '更新历史净值';
                        $desc1 = '更新产品历史净值';
                        Task::addTaskData($address, $command, $desc);
                        Task::addTaskData($address, $command1, $desc1);
                        //更新资金账户监控 管理费 手续费
                        
                        return true;
                    }
                }
            // }
        }
        return false;
    }

    /**
     * 获取总的净值
     * @author qinlh
     * @since 2022-07-10
     */
    public static function getCountNetworth($product_id=0, $iscalcToday=true)
    {
        $count = 0;
        if ($product_id) {
            if ($iscalcToday) { //全部查询
                $count = self::where('product_id', $product_id)->sum('networth');
            } else { //不查询今天的净值
                $date = date('Y-m-d');
                $count = self::where('product_id', $product_id)->whereNotIn('date', $date)->sum('networth');
            }
        }
        return $count;
    }
    
    /**
     * 获取产品总的年化收益
     * @author qinlh
     * @since 2022-07-11
     */
    public static function getCountAnnualizedIncome($product_id=0)
    {
        $total_annualized_income = 0;
        if ($product_id > 0) {
            // $countNetworth = self::getCountNetworth($product_id); //获取总的净值
            $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($product_id);
            $toDayNetworth = (float)$NewTodayYesterdayNetworth['toDayData']; //今日最新净值
            $dayNum = self::where('product_id', $product_id)->count();
            $total_annualized_income = $dayNum > 0 ? (($toDayNetworth - 1) / $dayNum) * 365 * 100 : ($toDayNetworth - 1) * 365 * 100;
        }
        return $total_annualized_income;
    }

    /**
     * 获取今日净值是否设置
     * @author qinlh
     * @since 2022-07-31
     */
    public static function getTodayIsNetWorth() {
        $date = date("Y-m-d");
        $res = self::where('date', $date)->find();
        if($res && count((array)$res) > 0) {
            return true;
        } else {
            return false;
        }
    }
}
