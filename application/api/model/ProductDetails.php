<?php

namespace app\api\model;

use think\Model;

class ProductDetails extends Base {


    /**
     * 获取产品净值数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getProductDetailsList($where, $page, $limit, $order='date desc')
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
            return ['count'=>0, 'allpage'=>0, 'lists'=>[]];
        }
        // p($lists);
        return ['count'=>$count, 'allpage'=>$allpage, 'lists'=>$lists];
    }

    /**
     * 写入用户产品统计数据
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
    public static function setProductDetails($data=[]) {
        if($data) {
            try {
                $date = date('Y-m-d');
                $IsResData = self::where(['product_id'=>$data['product_id'], 'uid' => $data['uid'], 'date' => $date])->find();
                if($IsResData && count((array)$IsResData) > 0) {
                    $updateData = [
                        'account_balance' => $data['account_balance'],
                        'total_revenue' => $data['total_revenue'],
                        'daily_income' => $data['daily_income'],
                        'daily_rate_return' => $data['daily_rate_return'],
                        'total_revenue_rate' => $data['total_revenue_rate'],
                        'daily_arg_rate' => $data['daily_arg_rate'],
                        'daily_arg_annualized' => $data['daily_arg_annualized'],
                        'time' => date('Y-m-d H:i:s'),
                    ];
                    $res = self::where(['product_id'=>$data['product_id'], 'uid' => $data['uid'], 'date' => $date])->update($updateData);
                    if($res) {
                        return true;
                    }
                } else {
                    self::insert($data);
                    $insertId = self::getLastInsID();
                    if($insertId) {
                        return true;
                    }
                }
                return false;
            } catch ( PDOException $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * 获取日均收益率
     * @author qinlh
     * @since 2022-07-11
     */
    public static function getAverageDailyRate($product_id=0, $todayRate=0) {
        if($product_id > 0) {
            // $avgNum = self::where(['product_id' => $product_id])->avg('daily_rate_return');
            $data = self::where(['product_id' => $product_id])->field('daily_rate_return')->select()->toArray();
            $arr = array_column($data, 'daily_rate_return');
            $avgNum = count($arr) > 0 ? (array_sum($arr) + (float)$todayRate) / count($arr) : $todayRate / 1;
            if($avgNum > 0 ) {
                return $avgNum;
            }
        }
        return 0;
    }


}