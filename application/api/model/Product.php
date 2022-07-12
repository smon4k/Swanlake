<?php

namespace app\api\model;

use think\Model;

class Product extends Base
{

    /**
     * 获取产品列表数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getProductList($where, $page, $limit, $order='id desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::alias('a')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->field("a.*")
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        $yestDayDate = date("Y-m-d", strtotime("-1 day"));
        $toDayDate = date("Y-m-d");
        foreach ($lists as $key => $val) {
            $NewsBuyAmount = MyProduct::getNewsBuyAmount($val['id']);
            $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($val['id']);
            $toDayNetworth = $NewTodayYesterdayNetworth['toDayData']; //今日最新净值
            $yestDayNetworth = $NewTodayYesterdayNetworth['yestDayData']; //昨日净值
            $lists[$key]['yest_income'] = 0;
            $lists[$key]['yest_income_rate'] = 0;
            if ($NewsBuyAmount['count_buy_number'] && $toDayNetworth && $yestDayNetworth) { //昨日收益
                $yest_income = ((float)$toDayNetworth - (float)$yestDayNetworth) * $NewsBuyAmount['count_buy_number'];
                $lists[$key]['yest_income'] = $yest_income;
                $lists[$key]['yest_income_rate'] = ((float)$toDayNetworth - (float)$yestDayNetworth) / (float)$yestDayNetworth * 100;
            }
            $annualizedIncome = DayNetworth::getCountAnnualizedIncome($val['id']); //获取年化收益
            $lists[$key]['networth'] = $toDayNetworth; //今日净值
            $lists[$key]['annualized_income'] = $annualizedIncome;
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 根据笔记id查询详情数据
     * @author qinlh
     * @since 2022-02-27
     */
    public static function getProductDetail($product_id=0, $address='')
    {
        if ($product_id > 0) {
            $data = self::where('id', $product_id)->find();
            if ($data) {
                $DayNetworth = DayNetworth::getDayNetworth($product_id);
                $data['networth'] = 0;
                if ($DayNetworth) {
                    $data['networth'] = $DayNetworth['networth'];
                }
                $userInfo = User::getUserAddressInfo($address);
                $data['balance'] = 0;
                if ($userInfo && count((array)$userInfo) > 0) {
                    $data['balance'] = (float)$userInfo['wallet_balance'] + (float)$userInfo['local_balance'];
                }
                $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($product_id);
                $toDayNetworth = (float)$NewTodayYesterdayNetworth['toDayData']; //今日最新净值
                $yestDayNetworth = (float)$NewTodayYesterdayNetworth['yestDayData']; //昨日净值
                $amountRes = MyProduct::getNewsBuyAmount($product_id);
                $data['daily_income'] = 0;
                if ($amountRes) {
                    $data['daily_income'] = ($toDayNetworth - $yestDayNetworth) * (float)$amountRes['count_buy_number'];
                }
                $UserTotalInvest = MyProduct::getUserTotalInvest($userInfo['id']);
                $data['total_invest'] = 0;
                $data['total_number'] = 0;
                if($UserTotalInvest) {
                    $data['total_invest'] = $UserTotalInvest['total_invest'];
                    $data['total_number'] = $UserTotalInvest['total_number'];
                }
                $annualizedIncome = DayNetworth::getCountAnnualizedIncome($product_id); //获取年化收益
                $data['annualized_income'] = $annualizedIncome;
                // $isBet = MyProduct::getMyProduct($product_id, $userId);
                // if($isBet) {
                //     $data['is_bet'] = 1;
                // } else {
                //     $data['is_bet'] = 0;
                // }
                return $data;
            }
        }
        return [];
    }

    /**
     * 修改总规模
     * @params address 用户地址
     * @params amount 数量
     * @params type 1：加 2：减
     * @author qinlh
     * @since 2022-06-14
     */
    public static function setTotalSizeBalance($product_id='', $amount=0, $type=0)
    {
        if ($product_id && $product_id > 0 && $amount > 0 && $type > 0) {
            if($type == 1) {
                $res = self::where('id', $product_id)->setInc('total_size', $amount);
            }
            if($type == 2) {
                $res = self::where('id', $product_id)->setDec('total_size', $amount);
            }
            if($res) {
                return true;
            }
        }
        return false;
    }
}
