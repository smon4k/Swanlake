<?php

// +----------------------------------------------------------------------
// | 文件说明：BTC-存钱罐-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-06-23
// +----------------------------------------------------------------------

namespace app\piggybank\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;
use RequestService\RequestService;

class Piggybank extends Base
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
        $lists = self::name("piggybank")
                    ->alias("a")
                    ->where($where)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
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
        $count = self::name("piggybank_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("piggybank_date")
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
        $count = self::name("piggybank_currency_date")
                    ->alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("piggybank_currency_date")
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
    public static function calcDepositAndWithdrawal($product_name='', $direction=0, $amount=0, $remark='', $currency_id=0)

    {
        $date = date('Y-m-d');
        //总结余
        $balanceDetails = self::getTradeValuation($product_name);
        $currencyData = self::getTradingPairId($product_name);
        $currency_id = $currencyData['id'];
        $btcPrice = $balanceDetails['btcPrice'];
        $countUstandardPrincipal = 0;
        $countBstandardPrincipal = 0;
        $uPrincipalRes = self::getPiggybankCurrencyPrincipal(1, $currency_id); //获取昨天的U数据
        $bPrincipalRes = self::getPiggybankCurrencyPrincipal(2, $currency_id); //获取昨天的B数据
        $URes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
        $BRes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
        $total_balance = self::getInoutGoldTotalBalance(); //出入金总结余
        if (!$amount || $amount == 0) {
            // $countUstandardPrincipal = isset($uPrincipalRes['principal']) ? $uPrincipalRes['principal'] : 0;
            // $countBstandardPrincipal = isset($bPrincipalRes['principal']) ? $bPrincipalRes['principal'] : 0;
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
                    $countBstandardPrincipal = (float)$total_balance / $btcPrice;
                }
            } else {
                $countBstandardPrincipal = $BRes['principal'] > 0 ? $BRes['principal'] : (float)$total_balance / $btcPrice;
            }
        } else {
            //本金
            if ($direction == 1) {
                $countUstandardPrincipal = (float)$total_balance + (float)$amount;
                $countBstandardPrincipal = ((float)$total_balance / $btcPrice) + ((float)$amount / $btcPrice);
            } else {
                $countUstandardPrincipal = (float)$total_balance - (float)$amount;
                $countBstandardPrincipal = ((float)$total_balance / $btcPrice) - ((float)$amount / $btcPrice);
            }
        }


        $UTotalBalance = $balanceDetails['usdtBalance'] + $balanceDetails['btcValuation']; //U本位总结余 = USDT数量+BTC数量*价格
        $BTotalBalance = $balanceDetails['btcBalance'] + $balanceDetails['usdtBalance'] / $btcPrice; //币本位结余 = BTC数量+USDT数量/价格

        //总利润 = 总结余 - 本金
        $UProfit = $UTotalBalance - $countUstandardPrincipal;
        $BProfit = $BTotalBalance - $countBstandardPrincipal;

        //总利润率 = 利润 / 本金
        $UProfitRate = $countUstandardPrincipal > 0 ? $UProfit / $countUstandardPrincipal : 0;
        $BProfitRate = $countBstandardPrincipal > 0 ? $BProfit / $countBstandardPrincipal : 0;

        //日利润 日利润率
        $dailyUProfit = 0; //昨日U本位利润
        $dailyBProfit = 0; //昨日B本位利润
        $dailyUProfitRate = 0; //昨日B本位利润率
        $dailyBProfitRate = 0; //昨日B本位利润率
        $yestUTotalBalance = isset($uPrincipalRes['total_balance']) ? (float)$uPrincipalRes['total_balance'] : 0;
        $yestBTotalBalance = isset($bPrincipalRes['total_balance']) ? (float)$bPrincipalRes['total_balance'] : 0;
        $depositToday = self::getInoutGoldDepositToday($currency_id); //获取今日入金数量
        $dailyUProfit = $UTotalBalance - $yestUTotalBalance - $depositToday; //U本位日利润 = 今日的总结余-昨日的总结余-今日入金数量
        $dailyBProfit = $BTotalBalance - $yestBTotalBalance - ($depositToday / $btcPrice); //币本位日利润 = 今日的总结余-昨日的总结余-今日入金数量
        $dailyUProfitRate = $yestUTotalBalance > 0 ? $dailyUProfit / $yestUTotalBalance * 100 : 0; // 日利润率
        $dailyBProfitRate = $yestBTotalBalance > 0 ? $dailyBProfit / $yestBTotalBalance * 100 : 0;

        $tradingPairData = self::getTradingPairData($currency_id);
        $UaverageDayRate = self::name('piggybank_currency_date')->where(['standard' => 1, 'product_name' => $tradingPairData['name']])->whereNotIn('date', $date)->avg('daily_profit_rate'); //获取U本位平均日利率
        $UaverageYearRate = $UaverageDayRate * 365; //平均年利率 = 平均日利率 * 365
        $BaverageDayRate = self::name('piggybank_currency_date')->where(['standard' => 2, 'product_name' => $tradingPairData['name']])->whereNotIn('date', $date)->avg('daily_profit_rate'); //获取B本位平均日利率
        $BaverageYearRate = $BaverageDayRate * 365; //平均年利率 = 平均日利率 * 365
        self::startTrans();
        try {
            $URes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
            if ($URes && count((array)$URes) > 0) {
                $upDataU = [
                    'principal' => $countUstandardPrincipal,
                    'total_balance' => $UTotalBalance,
                    'daily_profit' => $dailyUProfit,
                    'daily_profit_rate' => $dailyUProfitRate, //日利润率
                    'average_day_rate' => $UaverageDayRate,
                    'average_year_rate' => $UaverageYearRate,
                    'profit' => $UProfit,
                    'profit_rate' => $UProfitRate,
                    'price' => $btcPrice,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update($upDataU);
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
                    'price' => $btcPrice,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $saveUres = self::name('piggybank_currency_date')->insertGetId($insertDataU);
            }
            if ($saveUres !== false) {
                $BRes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
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
                        'price' => $btcPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update($upDataB);
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
                        'price' => $btcPrice,
                        'up_time' => date('Y-m-d H:i:s')
                    ];
                    $saveBres = self::name('piggybank_currency_date')->insertGetId($insertDataB);
                }
                if ($saveBres !== false) {
                    if ($amount > 0) {
                        $isIntOut = self::setInoutGoldRecord($amount, $btcPrice, $direction, $remark, $currency_id);
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
            p($e);
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
        $balanceDetails = self::getTradeValuation($product_name);
        $btcPrice = $balanceDetails['btcPrice'];
        $UTotalBalance = $balanceDetails['usdtBalance'] + $balanceDetails['btcValuation']; //U本位总结余 = USDT数量+BTC数量*价格
        $BTotalBalance = $balanceDetails['btcBalance'] + $balanceDetails['usdtBalance'] / $btcPrice; //币本位结余 = BTC数量+USDT数量/价格
        
        $URes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->find();
        if($URes) {
            self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 1])->update(['total_balance' => $UTotalBalance, 'price' => $btcPrice]);
        }
        $BRes = self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->find();
        if($BRes) {
            self::name('piggybank_currency_date')->where(['product_name' => $product_name, 'date' => $date, 'standard' => 2])->update(['total_balance' => $BTotalBalance, 'price' => $btcPrice]);
        }
        return true;
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
        $data = self::name('piggybank')->where($where)->select()->toArray();
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
    public static function setInoutGoldRecord($amount='', $price, $type=0, $remark='', $currency_id=0)

    {
        if ($amount !== 0 && $type > 0) {
            if($type == 1) {
                $total_balance = self::getInoutGoldTotalBalance($currency_id) + (float)$amount;
                $amount_num = $amount;
            } else {
                $total_balance = self::getInoutGoldTotalBalance($currency_id) - (float)$amount;
                $amount_num = $amount *= -1;
            }
            $insertData = [
                'pig_id' => $currency_id,
                'amount' => $amount_num,
                // 'price' => $price,
                'type' => $type,
                'total_balance' => $total_balance,
                'remark' => $remark,
                'time' => date('Y-m-d H:i:s'),
            ];
            $res = self::name('inout_gold')->insertGetId($insertData);
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
    public static function getInoutGoldTotalBalance($currency_id=0)
    {
        $count = self::name('inout_gold')->where('pig_id', $currency_id)->sum('amount');
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
    public static function getInoutGoldDepositTotal($currency_id=0)
    {
        $amount = self::name('inout_gold')->whereTime('time', 'today')->where(['pig_id' => $currency_id])->sum('amount');
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
    public static function getPiggybankCurrencyPrincipal($standard=0, $currency_id=0)
    {
        if ($standard > 0 && $currency_id > 0) {
            $date = date("Y-m-d", strtotime("-1 day")); //获取昨天的时间
            $tradingPairData = self::getTradingPairData($currency_id);
            $res = self::name('piggybank_currency_date')->where(['date' => $date, 'standard' => $standard, 'product_name' => $tradingPairData['name']])->find();
            if ($res && count((array)$res) > 0) {
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
        $count = self::name("inout_gold")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("inout_gold")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 每日存钱罐数据统计
     * @author qinlh
     * @since 2025-06-23
     */
    public static function piggybankDate($symbol='') {
        if($symbol == '') {
            return false;
        }
        try { 
            $balanceDetails = self::getSymbolInfo($symbol);
            $btcBalance = 0;
            $usdtBalance = 0;
            if($balanceDetails && count((array)$balanceDetails) > 0) {
                $btcBalance = $balanceDetails['valuation']['btc_balance'];
                $usdtBalance = $balanceDetails['valuation']['usdt_balance'];
            }
            
            $totalAssets = 0;//总资产
            $btcValuation = 0;
            if(isset($balanceDetails['valuation']['btc_price']) && (float)$balanceDetails['valuation']['btc_price'] > 0) {
                $btcValuation = $btcBalance * (float)$balanceDetails['valuation']['btc_price'];
                $usdtValuation = $usdtBalance;
            }
            $totalAssets = $btcValuation + $usdtValuation;
            
            $date = date('Y-m-d');
            //获取总的利润
            $countProfit = Piggybank::getUStandardProfit($symbol); //获取总的利润 网格利润
            $countProfitRate = $countProfit / $totalAssets * 100; //网格总利润率 = 总利润 / 总市值
            $dayProfit = Piggybank::getUStandardProfit($symbol, $date); //获取总的利润 网格利润
            $dayProfitRate = $dayProfit / $totalAssets * 100; //网格日利润率 = 日利润 / 总市值
            $averageDayRate = self::name('piggybank_date')->whereNotIn('date', $date)->avg('grid_day_spread_rate'); //获取平均日利润率
            $averageYearRate = $averageDayRate * 365; //平均年利率 = 平均日利率 * 365
            $data = self::name('piggybank_date')->where(['product_name' => $symbol, 'date' => $date])->find();
            if($data && count((array)$data) > 0) {
                $upData = [
                    'count_market_value'=>$totalAssets, 
                    'grid_spread' => $countProfit,
                    'grid_spread_rate' => $countProfitRate,
                    'grid_day_spread' => $dayProfit,
                    'grid_day_spread_rate' => $dayProfitRate,
                    'average_day_rate' => $averageDayRate,
                    'average_year_rate' => $averageYearRate,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $res = self::name('piggybank_date')->where(['product_name' => $symbol, 'date' => $date])->update($upData);
            } else {
                $insertData = [
                    'exchange' => $balanceDetails['base_currency'],
                    'product_name' => $symbol, 
                    'date'=>$date, 
                    'count_market_value'=>$totalAssets, 
                    'grid_spread' => $countProfit,
                    'grid_spread_rate' => $countProfitRate,
                    'grid_day_spread' => $dayProfit,
                    'grid_day_spread_rate' => $dayProfitRate,
                    'average_day_rate' => $averageDayRate,
                    'average_year_rate' => $averageYearRate,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $res = self::name('piggybank_date')->insertGetId($insertData);
            }
            if($res !== false) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            p($e->getMessage());
            logger("GMX-USDT 每日存钱罐数据统计 Error \r\n".$e);
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取 currency_list 表中的币种列表
     * @param array $where 查询条件，默认为空获取所有
     * @param int $page 当前页码，默认为1
     * @param int $limit 每页条数，默认为10
     * @return array 返回包含总数、总页数和列表数据的数组
     */
    public static function getCurrencyList($where = [], $page = 1, $limit = 10) {
        $count = self::name('currency_list')->where($where)->count();
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('currency_list')
                    ->where($where)
                    ->page($page, $limit)
                    ->order('id desc')
                    ->select()
                    ->toArray();
        return ['count' => $count, 'allpage' => $allpage, 'lists' => $lists];
    }

    /**
     * 获取所有币种列表
     * @return array 返回包含所有币种列表的数组
     * @author qinlh
     * @since 2025-06-23
     */
    public static function getCurrencyAllList() {
        $lists = self::name('currency_list')
                    ->where(['state' => 1])
                    ->order('id desc')
                    ->select()
                    ->toArray();
        return $lists;
    } 

    /**
     * 获取交易对名称
     * @author qinlh
     * @since 2025-06-23
     */
    public static function getTradingPairData($currency_id=0) {
        $data = self::name('currency_list')->where(['id' => $currency_id, 'state' => 1])->find();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }

    /**
     * 获取交易对id
     * @author qinlh
     * @since 2025-06-26
     */
    public static function getTradingPairId($name='') {
        $data = self::name('currency_list')->where(['name' => $name])->find();
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
    }

    /**
     * 获取交易对估值
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradeValuation($transactionCurrency) {
        $balanceDetails = self::getSymbolInfo($transactionCurrency);
        $usdtBalance = isset($balanceDetails['valuation']['usdt_balance']) ? $balanceDetails['valuation']['usdt_balance'] : 0;
        $btcBalance = isset($balanceDetails['valuation']['btc_balance']) ? $balanceDetails['valuation']['btc_balance'] : 0;
        $btcPrice = 1;
        $btcValuation = 0;
        $usdtValuation = 0;
        if($balanceDetails && isset($balanceDetails['valuation']['btc_price']) && $balanceDetails['valuation']['btc_price'] > 0) {
            $btcPrice = (float)$balanceDetails['valuation']['btc_price'];
            $btcValuation = $btcBalance * (float)$balanceDetails['valuation']['btc_price'];
            $usdtValuation = $usdtBalance;
        }
        return ['btcPrice' => $btcPrice, 'usdtBalance' => $usdtBalance, 'btcBalance' => $btcBalance, 'btcValuation' => $btcValuation, 'usdtValuation' => $usdtValuation];
    }

    /**
     * 获取账户币种基本信息
     * @author qinlh
     * @since 2025-06-23
     */
    public static function getSymbolInfo($symbol) {
        $url = Config('piggybank_uri') . "/symbol-info";
        $params = [
            'symbol' => $symbol,
        ];
        $response_string = RequestService::doCurlGetRequest($url, $params);
        $response_arr = $response_string;
        if($response_arr) {
            return $response_arr;
        }
        return [];
    }


    /**
     * 测试平衡仓位
     * @author qinlh
     * @since 2022-11-21
     */
    public static function testBalancePosition($currency_id=0) {
        if($currency_id <= 0) {
            return ['code' => 0, 'msg' => '参数错误'];
        }
        $currencyData = self::getTradingPairData($currency_id);
        if(!$currencyData) {
            return ['code' => 0, 'msg' => '币种不存在'];
        }
        $result = [];
        $symbolInfo = self::getSymbolInfo($currencyData['name']);
        $currency1 = $symbolInfo['base_currency'];
        $currency2 = $symbolInfo['quote_currency'];
        $configData = self::getConfig();
        $changeRatioNum = $configData['change_ratio']; //涨跌比例 2%
        $balanceRatio = $configData['balance_ratio']; //平衡比例
        $balanceRatioArr = explode(':', $balanceRatio);
        $sellPropr = ($changeRatioNum / $changeRatioNum) + ($changeRatioNum / 100); //出售比例
        $buyPropr = ($changeRatioNum / $changeRatioNum) - ($changeRatioNum / 100); //购买比例

        //获取最小下单数量
        $minSizeOrderNum = isset($symbolInfo['min_order_size']) ? $symbolInfo['min_order_size'] : 0; //最小下单数量
        $base_ccy = isset($symbolInfo['base_currency']) ? $symbolInfo['base_currency'] : ''; //交易货币币种
        $quote_ccy = isset($symbolInfo['quote_currency']) ? $symbolInfo['quote_currency'] : ''; //计价货币币种

        $tradeValuation = self::getTradeValuation($currencyData['name']); //获取交易估值及价格
        $btcBalance = $tradeValuation['btcBalance'];
        $usdtBalance = $tradeValuation['usdtBalance'];
        $btcValuation = $tradeValuation['btcValuation'];
        $usdtValuation = $tradeValuation['usdtValuation'];
        $btcPrice = $tradeValuation['btcPrice'];


        $balancedValuation = self::getLastBalancedValuation(); // 获取上一次平衡状态下估值
        $changeRatio = 0;
        if($balancedValuation > 0) {
            $changeRatio = abs($btcValuation - $usdtValuation) / $balancedValuation * 100;
        } else if($usdtValuation > 0){
            $changeRatio = abs($btcValuation - $usdtValuation) / $usdtValuation * 100;
        }

        $btcSellNum = $balanceRatioArr[0] * (($btcValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        $btcSellOrdersNumber = $btcSellNum / $btcPrice;

        $usdtBuyNum = $balanceRatioArr[1] * (($usdtValuation - $btcValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
        $usdtBuyOrdersNumber = $usdtBuyNum;

        $result['minSizeOrderNum'] = $minSizeOrderNum;
        $result['base_ccy'] = $base_ccy;
        $result['quote_ccy'] = $quote_ccy;
        $result['tradingPrice'] = $btcPrice;
        $result['usdtBalance'] = $usdtBalance;
        $result['usdtValuation'] = $usdtValuation;
        $result['btcBalance'] = $btcBalance;
        $result['btcValuation'] = $btcValuation;
        $result['defaultRatio'] = $changeRatioNum;
        $result['changeRatio'] = $changeRatio;
        $result['sellOrdersNumberStr'] = '';
        $getLastRes = self::getLastRes();
        $lastPrice = (float)$getLastRes['price']; //最近一次交易价格
        $result['lastTimePrice'] = $lastPrice;
        $result['sellingPrice'] = $lastPrice * $sellPropr;
        $result['buyingPrice'] = $lastPrice * $buyPropr;
        if($btcValuation > $usdtValuation) { //GMX的估值超过BUSD时候，卖GMX换成BUSDT
            $result['sellOrdersNumberStr'] = $currency1 . '出售数量: ' . $btcSellOrdersNumber ;
        }
        if($btcValuation < $usdtValuation) { //GMX的估值低于BUSD时，买GMX，换成BUSD
            $result['sellOrdersNumberStr'] = $currency2 . '购买数量: ' . $usdtBuyOrdersNumber ;
        }

        $peningOrderList = self::getOpenPeningOrder();
        //计算下一次下单购买出售数据
        $result['pendingOrder'] = [];
        $result['pendingOrder']['buy']['price'] = 0;
        $result['pendingOrder']['buy']['amount'] = 0;
        $result['pendingOrder']['buy']['btcValuation'] = 0;
        $result['pendingOrder']['buy']['usdtValuation'] = 0;
        $result['pendingOrder']['sell']['price'] = 0;
        $result['pendingOrder']['sell']['amount'] = 0;
        $result['pendingOrder']['sell']['btcValuation'] = 0;
        $result['pendingOrder']['sell']['usdtValuation'] = 0;
        foreach ($peningOrderList as $key => $val) {
            if($val['type'] == 1) {
                $result['pendingOrder']['buy']['price'] = $val['price'];
                $result['pendingOrder']['buy']['amount'] = $val['amount'];
                // $result['pendingOrder']['buy']['bifiValuation'] = ($bifiBalance + (float)$val['amount']) * $val['price'];
                $result['pendingOrder']['buy']['btcValuation'] = $val['clinch_currency1'] * $val['price'];
                // $result['pendingOrder']['buy']['busdValuation'] = $busdValuation - ((float)$val['amount'] * $val['price']);
                $result['pendingOrder']['buy']['usdtValuation'] = $val['clinch_currency2'];
            } else {
                $result['pendingOrder']['sell']['price'] = $val['price'];
                $result['pendingOrder']['sell']['amount'] = $val['amount'];
                // $result['pendingOrder']['sell']['bifiValuation'] = ($bifiBalance - (float)$val['amount']) * $val['price'];
                $result['pendingOrder']['sell']['btcValuation'] = $val['clinch_currency1'] * $val['price'];
                // $result['pendingOrder']['sell']['busdValuation'] = $busdValuation + ((float)$val['amount'] * $val['price']);
                $result['pendingOrder']['sell']['usdtValuation'] = $val['clinch_currency2'];
            }
        }

        return $result;
    }

    /**
     * 获取上一次平衡估值
     * @author qinlh
     * @since 2022-08-19
     */
    public  static function getLastBalancedValuation() {
        $data = self::name('piggybank')->order('id desc, time desc')->find();
        if($data && count((array)$data) > 0) {
            return $data['balanced_valuation'];
        }
        return 0;
    }

    /**
     * 获取最近一次成交数据
     * @author qinlh
     * @since 2022-08-19
     */
    public  static function getLastRes() {
        $data = self::name('piggybank')->order('id desc, time desc')->find();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

    /**
     * 获取当前挂单数据
     * @author qinlh
     * @since 2022-11-25
     */
    public static function getOpenPeningOrder() {
        $data = self::name('piggybank_pendord')->where('status', 1)->order('id desc, time desc')->limit(2)->select();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

     /**
     * 获取今日入金总数量
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getInoutGoldDepositToday($pig_id=0)
    {
        $amount = self::name('inout_gold')->whereTime('time', 'today')->where(['pig_id' => $pig_id])->sum('amount');
        if ($amount !== 0) {
            return $amount;
        }
        return 0;
    }

    /**
     * 获取配置表数据，只获取第一条
     * @author qinlh
     * @since 2022-08-20
     */
    public static function getConfig()
    {
        $data = self::name('config')->order('id desc')->find();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

    /**
    * 修改配置
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function savePiggybankConfig($change_ratio, $balance_ratio) {
        $res = self::name('config')->where("id", 1)->update(['change_ratio'=>$change_ratio, 'balance_ratio'=>$balance_ratio]);
        if(false !== $res) {
            return ['code'=>1, 'msg'=>'修改成功'];
        } else {
            return ['code'=>0, 'msg'=>'修改失败'];
        } 
    }
    
}
