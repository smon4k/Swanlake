<?php
// +----------------------------------------------------------------------
// | 文件说明：H2O-存钱罐-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-17
// +----------------------------------------------------------------------
namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;
use app\tools\model\Okx;

class Piggybank extends Base
{   
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankOrderList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        // $count = self::name("okx_piggybank")
        //             ->alias("a")
        //             ->where($where)
        //             ->count();//计算总页面
        // p($count);
        // $allpage = intval(ceil($count / $limits));
        $lists = self::name("okx_piggybank")
                    ->alias("a")
                    ->where($where)
                    // ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        $newArrayData = [];
        foreach ($lists as $key => $val) {
            if($val['pair'] > 0) {
                $newArrayData[$val['pair']][] = $val;
            } else {
                $newArrayData[$val['id']][0] = $val;
                $newArrayData[$val['id']][1] = [];
                $newArrayData[$val['id']][1]['type_str'] = $val['type'] == 1 ? '等待卖出' : '等待买入';
            }
        }
        // p($newArrayData);
        $resultArray = [];
        foreach ($newArrayData as $key => $val) {
            $resultArray[$key]['time'] = $val[0]['time'];
            $price = 0;
            $profit = $val[0]['profit'];
            if(isset($val[1]) && count((array)$val[1]) > 1) {
                $price = abs($val[0]['price'] -  $val[1]['price']);
            } else {
                $price = $val[0]['price'];
            }
            $resultArray[$key]['price'] = $price;
            $resultArray[$key]['profit'] = $profit;
            $resultArray[$key]['lists'] = $val;
        }
        // p($resultArray);
        $newResultArray = array_values($resultArray);
        // p($newResultArray);
        //总数
        $total = count($newResultArray);
        $allpage = intval(ceil($total / $limits));
        //分页
        $start = ($page - 1) * $limits;
        $returnArr = array_slice($newResultArray, $start, $limits);
        // p($returnArr);
        return ['count'=>$total,'allpage'=>$allpage,'lists'=>$returnArr];
    }

    /**
    * 获取存钱罐每日统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankDateList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("okx_piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("okx_piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 获取U币本位统计数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getUBPiggybankDateList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("okx_piggybank_currency_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("okx_piggybank_currency_date")
                    ->alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
    
    /**
     * 出入金计算
     * @author qinlh
     * @since 2022-08-20
     */
    public static function calcDepositAndWithdrawal($product_name='', $direction=0, $amount=0, $remark='') {
        $date = date('Y-m-d');
        //总结余
        $balanceDetails = Okx::getTradeValuation($product_name);
        $btcPrice = $balanceDetails['btcPrice'];
        
        //本金
        $total_balance = self::getInoutGoldTotalBalance(); //出入金总结余
        if($direction == 1) {
            // $countUstandardPrincipal = (float)$UstandardPrincipal + (float)$amount;
            // $countBstandardPrincipal = (float)$UstandardPrincipal + ((float)$amount / $btcPrice);
            $countUstandardPrincipal = (float)$total_balance + (float)$amount;
            $countBstandardPrincipal = ((float)$total_balance / $btcPrice) + ((float)$amount / $btcPrice);
        } else {
            // $countUstandardPrincipal = (float)$UstandardPrincipal - (float)$amount;
            // $countBstandardPrincipal = (float)$UstandardPrincipal - ((float)$amount / $btcPrice);
            $countUstandardPrincipal = (float)$total_balance - (float)$amount;
            $countBstandardPrincipal = ((float)$total_balance / $btcPrice) - ((float)$amount / $btcPrice);
        }
        
        $UTotalBalance = $balanceDetails['usdtBalance'] + $balanceDetails['btcValuation']; //U本位总结余 = USDT数量+BTC数量*价格
        $BTotalBalance = $balanceDetails['btcBalance'] + $balanceDetails['usdtBalance'] / $btcPrice; //币本位结余 = BTC数量+USDT数量/价格
        
        //利润 = 总结余 - 本金
        $UProfit = $UTotalBalance - $countUstandardPrincipal;
        $BProfit = $BTotalBalance - $countBstandardPrincipal;

        //利润率 = 利润 / 本金
        $UProfitRate = $UProfit / $countUstandardPrincipal;
        $BProfitRate = $BProfit / $countBstandardPrincipal;

        self::startTrans();
        try { 
            $URes = self::name('okx_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
            if($URes && count((array)$URes) > 0) {
                $upDataU = [
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('okx_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update($upDataU);
            } else {
                $insertDataU = [
                    'product_name' => $product_name,
                    'standard' => 1,
                    'date' => $date,
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('okx_piggybank_currency_date')->insertGetId($insertDataU);
            }
            if($saveUres !== false) {
                $BRes = self::name('okx_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
                if($BRes && count((array)$BRes) > 0) {
                    $upDataB = [
                        'principal' => $countBstandardPrincipal,
                        'total_balance' => $BTotalBalance,
                        'profit' => $BProfit,
                        'profit_rate' => $BProfitRate,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('okx_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update($upDataB);
                } else {
                    $insertDataB = [
                        'product_name' => $product_name,
                        'standard' => 2,
                        'date' => $date,
                        'principal' => $countBstandardPrincipal,
                        'total_balance' => $BTotalBalance,
                        'profit' => $BProfit,
                        'profit_rate' => $BProfitRate,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('okx_piggybank_currency_date')->insertGetId($insertDataB);
                }
                if($saveBres !== false) {
                    $isIntOut = self::setInoutGoldRecord($amount, $direction, $remark);
                    if($isIntOut) {
                        self::commit();
                        return true;
                    }
                }
            }
            self::rollback();
            return false;
        } catch (\Exception $e) {
            self::rollback();
            return false;
        }
    }

    /**
     * 获取U本位币本位累计本金
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getPiggybankStandard($standard=0) {
        if($standard > 0) {
            $date = date('Y-m-d');
            $total = self::name('okx_piggybank_currency_date')->where(['standard' => $standard, 'date' => ['<>', $date]])->sum('principal');
            if($total) {
                return $total;
            }
        }
        return 0;
    }

    /**
     * 获取总的利润或者今日利润
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getUStandardProfit($product_name='', $date='') {
        $where = [];
        $where['pair'] = ['>', 0];
        $where['product_name'] = $product_name;
        if($date && $date !== '') {
            $start_date = $date . ' 00:00:00';
            $end_date = $date . ' 23:59:59';
            $where['time'] = ['between time', [$start_date, $end_date]];
        }
        $data = self::name('okx_piggybank')->where($where)->select()->toArray();
        if($data) {
            $newArray = [];
            foreach ($data as $key => $val) {
                $newArray[$val['pair']] = $val;
            }
            $countProfit = 0;
            foreach ($newArray as $key => $val) {
                $countProfit += $val['profit'];
            }
            return $countProfit;
        }
        return 0;
    }

    /**
     * 记录出入金记录
     * @author qinlh
     * @since 2022-08-20
     */
    public static function setInoutGoldRecord($amount='', $type=0, $remark='') {
        if($amount !== 0 && $type > 0) {
            $insertData = [
                'amount' => $amount,
                'type' => $type,
                'total_balance' => self::getInoutGoldTotalBalance() + (float)$amount,
                'remark' => $remark,
                'time' => date('Y-m-d H:i:s'),
            ];
            $res = self::name('okx_inout_gold')->insertGetId($insertData);
            if($res) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取出入金总结余
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getInoutGoldTotalBalance() {
        $count = self::name('okx_inout_gold')->sum('amount');
        if($count !== 0) {
            return $count;
        }
        return 0;
    }

    /**
    * 获取出入金记录
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getInoutGoldList($where, $page, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("okx_inout_gold")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("okx_inout_gold")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
}
