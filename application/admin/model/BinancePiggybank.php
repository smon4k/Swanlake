<?php

// +----------------------------------------------------------------------
// | 文件说明：币安BIFI-存钱罐-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-11-21
// +----------------------------------------------------------------------

namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;
use app\tools\model\Okx;
use app\tools\model\Binance;

class BinancePiggybank extends Base
{
    /**
    * 获取订单列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getPiggybankOrderList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        // $count = self::name("okx_piggybank")
        //             ->alias("a")
        //             ->where($where)
        //             ->count();//计算总页面
        // p($count);
        // $allpage = intval(ceil($count / $limits));
        $lists = self::name("binance_piggybank")
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
            if ($val['pair'] > 0) {
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
            if (isset($val[1]) && count((array)$val[1]) > 1) {
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
    public static function getPiggybankDateList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("binance_piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("binance_piggybank_date")
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
    public static function getUBPiggybankDateList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("binance_piggybank_currency_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("binance_piggybank_currency_date")
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
    public static function calcDepositAndWithdrawal($product_name='', $direction=0, $amount=0, $remark='')
    {
        $date = date('Y-m-d');
        //总结余
        $balanceDetails = Binance::getTradeValuation($product_name);
        $tradingPrice = $balanceDetails['tradingPrice'];
        $countUstandardPrincipal = 0;
        $countBstandardPrincipal = 0;
        $uPrincipalRes = self::getPiggybankCurrencyPrincipal(1); //获取昨天的U数据
        $bPrincipalRes = self::getPiggybankCurrencyPrincipal(2); //获取昨天的B数据
        $URes = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
        $BRes = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
        $total_balance = self::getInoutGoldTotalBalance(); //出入金总结余
        if (!$amount || $amount == 0) { 
            if(!$URes || empty($URes)) { //今日第一次执行 获取昨日本金
                if(isset($uPrincipalRes['principal']) && $uPrincipalRes['principal'] > 0) {
                    $countUstandardPrincipal = isset($uPrincipalRes['principal']) ? (float)$uPrincipalRes['principal'] : 0;
                } else {
                    $countUstandardPrincipal = $total_balance;
                }
            } else {
                $countUstandardPrincipal = $URes['principal'] > 0 ? $URes['principal'] : $total_balance;
            }
            if(!$BRes || empty($BRes)) {
                if(isset($bPrincipalRes['principal']) && $bPrincipalRes['principal'] > 0) {
                    $countBstandardPrincipal = isset($bPrincipalRes['principal']) ? (float)$bPrincipalRes['principal'] : 0;
                } else {
                    $countBstandardPrincipal = (float)$total_balance / $tradingPrice;
                }
            } else {
                $countBstandardPrincipal = $BRes['principal'] > 0 ? $BRes['principal'] : (float)$total_balance / $tradingPrice;
            }
        } else {
            //本金
            if ($direction == 1) { //入金
                $countUstandardPrincipal = (float)$total_balance + (float)$amount;
                $countBstandardPrincipal = ((float)$total_balance / $tradingPrice) + ((float)$amount / $tradingPrice);
            } else {
                $countUstandardPrincipal = (float)$total_balance - (float)$amount;
                $countBstandardPrincipal = ((float)$total_balance / $tradingPrice) - ((float)$amount / $tradingPrice);
            }
        }

        //获取挂单数据
        $peningOrderList = Binance::getOpenPeningOrders();
        $buyOrderData = $peningOrderList['buy']; //购买挂单数据
        $sellOrderData = $peningOrderList['sell']; //出售挂单数据
        $buy_amount = $buyOrderData['amount'] && $buyOrderData['amount'] > 0 ? $buyOrderData['amount'] : 0; //挂买数量
        $sell_amount = $sellOrderData['amount'] && $sellOrderData['amount'] > 0 ? $sellOrderData['amount'] : 0; //挂卖数量

        $busdValuation = $balanceDetails['busdBalance'] + ($buy_amount * $tradingPrice); //BUSD估值 = 可用余额 + 挂买的数
        $bifiValuation = $balanceDetails['bifiValuation'] + ($sell_amount * $tradingPrice); //BIFI估值 = 可用余额 + 挂卖的数量
        $bifiBalance = $balanceDetails['bifiBalance'] + $sell_amount; //BIFI余额 = 可用余额  + 挂卖的数量）*bifi现价

        $UTotalBalance = $busdValuation + $bifiValuation; //U本位总结余 = BUSD数量+BIFI数量*价格
        $BTotalBalance = $bifiBalance + $busdValuation / $tradingPrice; //币本位结余 = BTC数量+USDT数量/价格

        //总利润 = 总结余 - 本金
        $UProfit = $UTotalBalance - $countUstandardPrincipal;
        $BProfit = $BTotalBalance - $countBstandardPrincipal;

        //总利润率 = 利润 / 本金
        $UProfitRate = $UProfit / $countUstandardPrincipal;
        $BProfitRate = $BProfit / $countBstandardPrincipal;

        //日利润 日利润率
        $dailyUProfit = 0; //昨日U本位利润
        $dailyBProfit = 0; //昨日B本位利润
        $dailyUProfitRate = 0; //昨日B本位利润率
        $dailyBProfitRate = 0; //昨日B本位利润率
        $yestUTotalBalance = isset($uPrincipalRes['total_balance']) ? (float)$uPrincipalRes['total_balance'] : 0;
        $yestBTotalBalance = isset($bPrincipalRes['total_balance']) ? (float)$bPrincipalRes['total_balance'] : 0;
        $depositToday = self::getInoutGoldDepositToday(); //获取今日入金数量
        $dailyUProfit = $UTotalBalance - $yestUTotalBalance - $depositToday; //U本位日利润 = 今日的总结余-昨日的总结余-今日入金数量
        $dailyBProfit = $BTotalBalance - $yestBTotalBalance - ($depositToday / $tradingPrice); //币本位日利润 = 今日的总结余-昨日的总结余-今日入金数量
        $dailyUProfitRate = $yestUTotalBalance > 0 ? $dailyUProfit / $yestUTotalBalance * 100 : 0;
        $dailyBProfitRate = $yestBTotalBalance > 0 ? $dailyBProfit / $yestBTotalBalance * 100 : 0;
        $UaverageDayRate = self::name('binance_piggybank_currency_date')->where('standard', 1)->whereNotIn('date', $date)->avg('daily_profit_rate'); //获取U本位平均日利率
        $UaverageYearRate = $UaverageDayRate * 365; //平均年利率 = 平均日利率 * 365
        $BaverageDayRate = self::name('binance_piggybank_currency_date')->where('standard', 2)->whereNotIn('date', $date)->avg('daily_profit_rate'); //获取B本位平均日利率
        $BaverageYearRate = $BaverageDayRate * 365; //平均年利率 = 平均日利率 * 365
        self::startTrans();
        try {
            // $URes = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
            if ($URes && count((array)$URes) > 0) {
                $upDataU = [
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'daily_profit' => $dailyUProfit,
                    'daily_profit_rate' => $dailyUProfitRate,
                    'average_day_rate' => $UaverageDayRate,
                    'average_year_rate' => $UaverageYearRate,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'price' => $tradingPrice,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update($upDataU);
            } else {
                $insertDataU = [
                    'product_name' => $product_name,
                    'standard' => 1,
                    'date' => $date,
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'daily_profit' => $dailyUProfit,
                    'daily_profit_rate' => $dailyUProfitRate,
                    'average_day_rate' => $UaverageDayRate,
                    'average_year_rate' => $UaverageYearRate,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'price' => $tradingPrice,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('binance_piggybank_currency_date')->insertGetId($insertDataU);
            }
            if ($saveUres !== false) {
                // $BRes = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
                if ($BRes && count((array)$BRes) > 0) {
                    $upDataB = [
                        'principal' => $countBstandardPrincipal,
                        'total_balance' => $BTotalBalance,
                        'daily_profit' => $dailyBProfit,
                        'daily_profit_rate' => $dailyBProfitRate,
                        'average_day_rate' => $BaverageDayRate,
                        'average_year_rate' => $BaverageYearRate,
                        'profit' => $BProfit,
                        'profit_rate' => $BProfitRate,
                        'price' => $tradingPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update($upDataB);
                } else {
                    $insertDataB = [
                        'product_name' => $product_name,
                        'standard' => 2,
                        'date' => $date,
                        'principal' => $countBstandardPrincipal,
                        'total_balance' => $BTotalBalance,
                        'daily_profit' => $dailyBProfit,
                        'daily_profit_rate' => $dailyBProfitRate,
                        'average_day_rate' => $BaverageDayRate,
                        'average_year_rate' => $BaverageYearRate,
                        'profit' => $BProfit,
                        'profit_rate' => $BProfitRate,
                        'price' => $tradingPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('binance_piggybank_currency_date')->insertGetId($insertDataB);
                }
                if ($saveBres !== false) {
                    if ($amount > 0) {
                        $isIntOut = self::setInoutGoldRecord($amount, $tradingPrice, $direction, $remark);
                        if ($isIntOut) {
                            self::commit();
                            return true;
                        }
                    } else {
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
     * 实时更新今日总结余 币价 作废
     * @author qinlh
     * @since 2022-09-05
     */
    public static function saveUpdateDayTotalBalance($product_name='') {
        $date = date('Y-m-d');
        //总结余
        $balanceDetails = Okx::getTradeValuation($product_name);
        $btcPrice = $balanceDetails['btcPrice'];
        $UTotalBalance = $balanceDetails['usdtBalance'] + $balanceDetails['btcValuation']; //U本位总结余 = USDT数量+BTC数量*价格
        $BTotalBalance = $balanceDetails['btcBalance'] + $balanceDetails['usdtBalance'] / $btcPrice; //币本位结余 = BTC数量+USDT数量/价格
        
        $URes = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
        if($URes) {
            self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update(['total_balance' => $UTotalBalance, 'price' => $btcPrice]);
        }
        $BRes = self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
        if($BRes) {
            self::name('binance_piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update(['total_balance' => $BTotalBalance, 'price' => $btcPrice]);
        }
        return true;
    }

    /**
     * 获取U本位币本位累计本金
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getPiggybankStandard($standard=0)
    {
        if ($standard > 0) {
            $date = date('Y-m-d');
            $total = self::name('binance_piggybank_currency_date')->where(['standard' => $standard, 'date' => ['<>', $date]])->sum('principal');
            if ($total) {
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
    public static function getUStandardProfit($product_name='', $date='')
    {
        $where = [];
        $where['pair'] = ['>', 0];
        $where['product_name'] = $product_name;
        if ($date && $date !== '') {
            $start_date = $date . ' 00:00:00';
            $end_date = $date . ' 23:59:59';
            $where['time'] = ['between time', [$start_date, $end_date]];
        }
        $data = self::name('binance_piggybank')->where($where)->select()->toArray();
        if ($data) {
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
    public static function setInoutGoldRecord($amount='', $price, $type=0, $remark='')
    {
        if ($amount !== 0 && $type > 0) {
            $total_balance = 0;
            if($type == 1) {
                $amount_num = $amount;
                $total_balance = self::getInoutGoldTotalBalance() + (float)$amount;
            } else {
                $amount_num = $amount *= -1;
                $total_balance = self::getInoutGoldTotalBalance() - (float)$amount;
            }
            $insertData = [
                'amount' => $amount_num,
                // 'price' => $price,
                'type' => $type,
                'total_balance' => $total_balance,
                'remark' => $remark,
                'time' => date('Y-m-d H:i:s'),
            ];
            $res = self::name('binance_inout_gold')->insertGetId($insertData);
            if ($res) {
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
    public static function getInoutGoldTotalBalance()
    {
        $count = self::name('binance_inout_gold')->sum('amount');
        if ($count !== 0) {
            return $count;
        }
        return 0;
    }

    /**
     * 获取今日入金总数量
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getInoutGoldDepositToday()
    {
        $amount = self::name('binance_inout_gold')->whereTime('time', 'today')->sum('amount');
        if ($amount !== 0) {
            return $amount;
        }
        return 0;
    }

    /**
     * 获取昨天累计本金
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getPiggybankCurrencyPrincipal($standard=0)
    {
        if ($standard > 0) {
            $date = date("Y-m-d", strtotime("-1 day")); //获取昨天的时间
            $res = self::name('binance_piggybank_currency_date')->where(['date' => $date, 'standard' => $standard])->find();
            if ($res && count((array)$res) > 0) {
                return $res;
            } else {
                $res = self::name('binance_piggybank_currency_date')->where(['standard' => $standard])->order('date desc')->find();
                return $res;
            }
        }
        return [];
    }

    /**
    * 获取出入金记录
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getInoutGoldList($where, $page, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("binance_inout_gold")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("binance_inout_gold")
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
