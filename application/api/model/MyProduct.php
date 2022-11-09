<?php

namespace app\api\model;

use think\Model;

class MyProduct extends Base {


    /**
     * 开始投资
     * @param [$address 钱包地址]
     * @param [$product_id 产品id]
     * @param [$number 份额]
     * @param [$type 1：投注 2：赎回]
     * @author qinlh
     * @since 2022-07-08
     */
    public static function startInvestNow($address='', $product_id=0, $number=0, $type=0) {
        if($address !== '' || $product_id > 0 || $number > 0 && $type > 0) {
            $userId = User::getUserAddress($address);
            self::startTrans();
            try {
                if($userId) {
                    $networth = 1; 
                    $buy_number = 0; //usdt 数量
                    $dayNetwordRes = DayNetworth::getDayNetworth($product_id);
                    if($dayNetwordRes) {
                        $networth = $dayNetwordRes['networth']; //获取今日净值
                    }
                    $buy_number = (float)$networth > 0 ? (float)$number * (float)$networth : (float)$number; //计算购买数量
                    $myProduct = self::getMyProduct($product_id, $userId);
                    $insertId = 0;
                    if ($myProduct && count((array)$myProduct) > 0) { //已存在 进行更新数据
                        // $insertId = $myProduct['id'];
                        if($type == 1) { //投注
                            $update = [
                                'total_invest' => (float)$myProduct['total_invest'] + (float)$buy_number,
                                'total_number' => (float)$myProduct['total_number'] + (float)$number,
                                'up_time' => date('Y-m-d H:i:s')
                            ];
                        } else { //赎回
                            $update = [
                                'total_invest' => (float)$myProduct['total_invest'] - (float)$buy_number,
                                'total_number' => (float)$myProduct['total_number'] - (float)$number,
                                'up_time' => date('Y-m-d H:i:s')
                            ];
                        }
                        $res = self::where('id', $myProduct['id'])->update($update);
                    } else {  //不存在 添加
                        if($type == 2) {
                            return false;
                        }
                        $insertData = [
                            'product_id' => $product_id,
                            'uid' => $userId,
                            'total_invest' => $buy_number,
                            'total_number' => $number,
                            'buy_networth' => $networth,
                            'time' => date('Y-m-d H:i:s'),
                            'up_time' => date('Y-m-d H:i:s'),
                        ];
                        self::insert($insertData);
                        $res = self::getLastInsID();
                        if($res) {
                            //第一次购买插入一条今日收益记录空数据
                            $account_balance = (float)$number * (float)$networth; //账户余额 = 今日净值 * 购买份数
                            // $total_revenue = $account_balance - $buy_number; //	总收益: 当前账户余额-总投资额
                            // $total_revenue_rate = ($total_revenue / $buy_number) * 100; // 总收益率: 总收益 / 总投资额
                            $insertDataUserDetails = [
                                'product_id' => $product_id,
                                'uid' => $userId,
                                'date' => date('Y-m-d'),
                                'networth' => $networth,
                                'account_balance' => $account_balance,
                                'total_revenue' => 0,
                                'daily_income' => 0,
                                'daily_rate_return' => 0,
                                'total_revenue_rate' => 0,
                                'daily_arg_rate' => 0,
                                'daily_arg_annualized' => 0,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            $res = self::name('product_user_details')->insert($insertDataUserDetails);
                        }
                    }
                    if($res) {
                        // $userBalance = (float)$networth > 0 ? (float)$number * (float)$networth : $number;
                        if($type == 1) { //投注
                            $isUserBalance = User::setUserLocalBalance($address, $buy_number, 2); //扣除用户余额
                        } else {//赎回
                            $isUserBalance = User::setUserLocalBalance($address, $buy_number, 1); //增加用户余额
                        }
                        if($isUserBalance) {
                            if($type == 1) { //投注 份数减少
                                $isTotalSize = Product::setTotalSizeBalance($product_id, $number, 1);
                            } else { //赎回 份数增加
                                $isTotalSize = Product::setTotalSizeBalance($product_id, $number, 2);
                            }
                            if($isTotalSize) {
                                $orderLog = ProductOrder::setProductOrder($userId, $product_id, $buy_number, $number, $networth, $type);
                                if($orderLog) {
                                    self::commit();
                                    return true;
                                }
                            }
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
     * 获取我的投资理财数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getMyProductList($where, $page, $limit, $order='id desc', $userId=0)
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::alias('a')->join('s_product b', 'a.product_id = b.id')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::alias('a')
                    ->join('s_product b', 'a.product_id = b.id')
                    ->field("a.*,b.name,b.currency")
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        $date = date("Y-m-d");
        $yestDate = date('Y-m-d', strtotime("-1 day"));
        $d1 = strtotime($date);
        foreach ($lists as $key => $val) {
            $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($val['product_id']);
            $toDayNetworth = (float)$NewTodayYesterdayNetworth['toDayData']; //今日最新净值
            $yestDayNetworth = (float)$NewTodayYesterdayNetworth['yestDayData']; //昨日净值

            // $yestDayProfit = (float)$NewTodayYesterdayNetworth['yestDayProfit']; //今日利润
            // $total_investment = $val['buy_number'] * $toDayNetworth; //总投资 = 购买份数 * 最新净值
            $total_investment = (float)$val['total_invest']; //总投资
            $total_number = (float)$val['total_number']; //总份数
            $total_balance = $total_number * $toDayNetworth; //	总结余: 总的份数 * 最新净值 （随着净值的变化而变化）
            $lists[$key]['total_balance'] = $total_balance;
            $cumulative_income = $total_balance - $total_investment; // 累计收益: 总结余 – 总投资
            $lists[$key]['total_income'] = $cumulative_income;
            $total_return = ($cumulative_income / $total_investment) * 100; //	总收益率: 累计收益 / 总投资
            // $total_networth = DayNetworth::getCountNetworth($val['product_id']); //总的净值
            $d2 = strtotime($val['time']);
            $buy_days = round(($d1 - $d2) / 3600 / 24); //购买天数
            $lists[$key]['networth'] = $toDayNetworth;  //今日最新净值

            //获取是否今天刚投注
            $lists[$key]['yest_income'] = 0;
            $lists[$key]['total_rate'] = 0;
            $lists[$key]['year_rate'] = 0;
            if((float)$toDayNetworth !== (float)$val['buy_networth']) { //如果净值发生变换 计算收益
                $lists[$key]['yest_income'] = ($toDayNetworth - $yestDayNetworth) * (float)$val['total_number']; //	昨日收益：（今天的净值 - 昨天的净值） * 用户总份数
                $lists[$key]['total_rate'] = $total_return;
                $buyDate = date('Y-m-d', $d2);
                $buyNetworth = DayNetworth::getDayNetworth($val['product_id'], $buyDate);
                // $daily_rate_return = (($toDayNetworth - $yestDayNetworth) / $yestDayNetworth) * 100; // 日收益率:（当日净值-昨日净值）/ 昨日净值
                $averag_daily_rate = ProductUserDetails::getAverageDailyRate($val['product_id'], $userId); //日均收益率: 所有日收益率的平均值
                // p($yestDayNetworth);
                // p($val['buy_networth']);
                $lists[$key]['year_rate'] = $buy_days > 0 ? (((float)$toDayNetworth - (float)$val['buy_networth']) / (float)$buy_days) * 365 * 100 : ((float)$toDayNetworth - $val['buy_networth']) / 1 * 365 * 100;  //年化收益率: ((当前最新净值-购买第一天的当日净值)/天数)*365
                // $lists[$key]['year_rate'] = (float)$averag_daily_rate * 365; // 日均年化: 日均收益率 * 365
            }
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

     /**
     * 获取计算总的投注数量和份数
     * @author qinlh
     * @since 2022-07-09
     */
    public static function getNewsBuyAmount($product_id=0, $userId=0, $iscalcToday=true, $toDayDate='', $yestDayDate='') {
        if($product_id > 0 || $userId > 0) {
            $sql = "SELECT sum(total_number) AS count_total_number FROM s_my_product WHERE 1=1 ";
            if($product_id) {
                $sql .= " AND product_id = {$product_id}";
            }
            if($userId) {
                $sql .= " AND uid = {$userId}";
            }
            // $date = date('Y-m-d');
            $data = self::query($sql);
            // p($data);
            $count_total_number = 0; //总的份数
            // $count_buy_networth = 0; //总的净值
            $count_balance = 0; //总结余
            $today_net_worth = 0;
            $today_profit = 0;
            if($data && count((array)$data) > 0) {
                $count_total_number = (float)$data[0]['count_total_number']; //总的份数

                // p($toDayNetworth);
                if($toDayDate == '' || $yestDayDate == '') {
                    $yestDayDate = date("Y-m-d", strtotime("-1 day"));
                    $toDayDate = date("Y-m-d");
                }
                $toDayNetworthArr = DayNetworth::getDayNetworth($product_id, $toDayDate);
                $yestDayNetworthArr = DayNetworth::getDayNetworth($product_id, $yestDayDate);

                // p($toDayNetworthNew);
                $toDayNetworth = 0; //今日净值
                $yestDayNetworth = 0; //昨日净值
                $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($product_id);
                $toDayNetworthNew = (float)$NewTodayYesterdayNetworth['toDayData']; // 获取最新的一天净值
                $yestDayNetworthNew = (float)$NewTodayYesterdayNetworth['yestDayData']; //倒数一天的净值
                if(isset($toDayNetworthArr['networth']) && $toDayNetworthArr['networth'] !== 0) {
                    $toDayNetworth = (float)$toDayNetworthArr['networth'];
                } else {
                    $toDayNetworth = $toDayNetworthNew;
                }

                if(isset($yestDayNetworthArr['networth']) && $yestDayNetworthArr['networth'] !== 0) {
                    $yestDayNetworth = (float)$yestDayNetworthArr['networth'];
                } else {
                    $yestDayNetworth = $yestDayNetworthNew;
                }

                $count_balance = $count_total_number * (float)$toDayNetworth;//总结余 = 总的份数 * 今日最新净值
                $yest_count_balance = $count_total_number * (float)$yestDayNetworth;//总结余 = 总的份数 * 昨日最新净值
            }
            return [
                'date' => date('Y-m-d'), 
                'count_balance' => $count_balance, //总结余
                'yest_count_balance' => $yest_count_balance, //昨日总结余
                'count_buy_number' => $count_total_number, //总的份数
                'today_net_worth' => (float)$toDayNetworth, //今日最新净值
                'yest_net_worth' => (float)$yestDayNetworth, //最新净值
                'is_networth' => (float)$toDayNetworth == 0 ? false : true,
            ];
        } 
    }

    /**
     * 根据用户id获取所有产品总结余
     * @author qinlh
     * @since 2022-07-14
     */
    public static function getAllProductBalance($uid=0) {
        if($uid > 0) {
            $sql = "SELECT product_id,uid,total_number FROM s_my_product WHERE uid={$uid}";
            $data = self::query($sql);
            $count_balance = 0;
            foreach ($data as $key => $val) {
                $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($val['product_id']);
                $toDayNetworth = (float)$NewTodayYesterdayNetworth['toDayData']; //今日最新净值
                $count_balance += $val['total_number'] * $toDayNetworth;//总结余 = 总的份数 * 今日最新净值
            }
            return $count_balance;
            // p($count_balance);
        }
        return 0;
    }
    
    /**
     * 获取用户总的投注数量几购买总份数
     * @author qinlh
     * @since 2022-07-11
     */
    public static function getUserTotalInvest($userId=0, $product_id=0) {
        $sql = "SELECT sum(total_invest) AS total_invest, sum(total_number) AS total_number FROM s_my_product WHERE 1=1 ";
        if ($userId && $userId > 0) {
            $sql .= " AND uid={$userId}";
        }
        if ($product_id && $product_id > 0) {
            $sql .= " AND product_id={$product_id}";
        }
        $data = self::query($sql);
        $total_invest = 0;
        $total_number = 0;
        if($data && count((array)$data) > 0) {
            $total_invest = $data[0]['total_invest'];
            $total_number = $data[0]['total_number'];
        }
        return ['total_invest' => $total_invest, 'total_number' => $total_number];
        
        return [];
    }

    /**
     * 获取计算总的投注数量和份数
     * @author qinlh
     * @since 2022-07-09
     */
    public static function calcNewsNetWorth($count_profit=0, $product_id=0, $date='', $yestDate='') {
        $dayNetWorth = 0; //当天净值
        $count_balance = 0; //总结余
        $count_buy_number = 0; //总的份数
        // if($count_profit !== 0) {
        $data = self::getNewsBuyAmount($product_id, 0, false, $date, $yestDate);
        if($data) {
            $count_buy_number = $data['count_buy_number']; //总的份数
            // p($data['count_balance']);
            // $count_balance = $data['count_balance'] * $data['today_net_worth'];//总结余 = 总的份数 * 总的净值
            // $count_balance = $data['count_balance'];//总结余 = 总的份数 * 总的净值
            $count_balance = $data['yest_count_balance'];//总结余 = 总的份数 * 总的净值
            // p($count_balance);
            $dayNetWorth =  $count_buy_number > 0 ? ((float)$count_profit + (float)$count_balance) / (float)$count_buy_number : (float)$count_profit + (float)$count_balance;  //	当天净值:  （总的利润 + 总的结余）/ 总的份数
        }
        // }
        return $dayNetWorth;
    }

    /**
     * 获取用户对应产品是否已投注
     * @author qinlh
     * @since 2022-07-09
     */
    public static function getMyProduct($product_id=0, $uid=0) {
        if($product_id > 0 && $uid > 0) {
            $res = self::where(['product_id'=>$product_id, 'uid'=>$uid])->find();
            if($res && count((array)$res) > 0) {
                return $res;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * 异步循环计算用户投资产品收益数据
     * @author qinlh
     * @since 2022-07-11
     */
    public static function saveUserProductData($date='', $yestDate='', $where=[]) {
        $data = self::where($where)->select()->toArray();
        if($date == '' || $yestDate == '') {
            $date = date('Y-m-d');
            $yestDate = date('Y-m-d', strtotime("-1 day"));
        }
        $d1 = strtotime($date);
        $insertData = [];
        self::startTrans();
        try {
            foreach ($data as $key => $val) {
                $networthData = DayNetworth::getDayNetworth($val['product_id'], $date);
                $yestNetworthData = DayNetworth::getDayNetworth($val['product_id'], $yestDate);
                $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($val['product_id']);
                if($networthData && count((array)$networthData) > 0) {
                    @self::name('product_user_details')->where(['product_id'=>$val['product_id'], 'uid' => $val['uid'], 'date' => $date])->delete();
                    $networth = (float)$networthData['networth']; //今日净值
                    $yestNetworth = "";
                    if(isset($yestNetworthData['networth']) && $yestNetworthData['networth'] !== '') {
                        $yestNetworth = (float)$yestNetworthData['networth']; //昨日净值
                    } else {
                        $yestNetworth = $NewTodayYesterdayNetworth['yestDayData'];
                    }
                    // $yestNetworth = isset($yestNetworthData['networth']) ? (float)$yestNetworthData['networth'] : 1; //昨日净值
                    $buy_total_number = (float)$val['total_number']; //购买总份数
                    $account_balance = $buy_total_number * $networth; //账户余额 = 今日净值 * 购买份数
                    $total_investment = (float)$val['total_invest']; //总投资
                    $total_revenue = 0;
                    $daily_income = 0;
                    $daily_rate_return = 0;
                    $total_revenue_rate = 0;
                    $averag_daily_rat = 0;
                    $daily_average_annualized = 0;

                    $d2 = strtotime($val['time']); //购买第一天时间
                    $buy_days = round(($d1 - $d2) / 3600 / 24); //计算购买天数
                    if((float)$networth !== (float)$val['buy_networth']) {
                        $total_revenue = $account_balance - $total_investment; //	总收益: 当前账户余额-总投资额
                        $daily_income = ($networth - $yestNetworth) * $buy_total_number; //	日收益:（当日净值-昨日净值）* 总购买份数
                        $daily_rate_return = (($networth - $yestNetworth) / $yestNetworth) * 100; // 日收益率:（当日净值-昨日净值）/ 昨日净值
                        $total_revenue_rate = ($total_revenue / $total_investment) * 100; // 总收益率: 总收益 / 总投资额
                        $averag_daily_rate = ProductUserDetails::getAverageDailyRate($val['product_id'], $val['uid'], $daily_rate_return); //日均收益率: 所有日收益率的平均值
                        // p($yestNetworth);
                        $daily_average_annualized = $buy_days > 0 ? (((float)$networth - (float)$val['buy_networth']) / (float)$buy_days) * 365 * 100 : ((float)$networth - $val['buy_networth']) / 1 * 365 * 100;  //年化收益率: ((当前最新净值-购买第一天的当日净值)/天数)*365
                        // $daily_average_annualized = (float)$averag_daily_rate * 365; // 日均年化: 日均收益率 * 365
                    }
                    $insertData[] = [
                        'product_id' => $val['product_id'],
                        'uid' => $val['uid'],
                        'date' => $date,
                        'networth' => $networth,
                        'account_balance' => $account_balance,
                        'total_revenue' => $total_revenue,
                        'daily_income' => $daily_income,
                        'daily_rate_return' => $daily_rate_return,
                        'total_revenue_rate' => $total_revenue_rate,
                        'daily_arg_rate' => $averag_daily_rate,
                        'daily_arg_annualized' => $daily_average_annualized,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                }   
            }
            if($insertData && count((array)$insertData) > 0) {
                $res = self::name('product_user_details')->insertAll($insertData);
                if ($res) {
                    self::commit();
                    return ['code' => 1, 'message' => '计算净值记录数据成功'];
                }
            }
            self::rollback();
            return ['code' => 0, 'message' => '计算失败'];
        } catch ( PDOException $e) {
            self::rollback();
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            return ['code' => 0, 'message' => $error_msg];
        }
    }

    /**
     * 异步计算产品每日收益数据
     * @author qinlh
     * @since 2022-07-13
     */
    public static function saveProductListData($date='', $yestDate='') {
        $data = self::name('product')->select()->toArray();
        if($date == '' || $yestDate == '') {
            $date = date('Y-m-d');
            $yestDate = date('Y-m-d', strtotime("-1 day"));
        }
        $d1 = strtotime($date);
        $insertData = [];
        self::startTrans();
        try {
            foreach ($data as $key => $val) {
                $networthData = DayNetworth::getDayNetworth($val['id'], $date);
                $yestNetworthData = DayNetworth::getDayNetworth($val['id'], $yestDate);
                $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($val['id']);
                // p($yestNetworthData);
                if($networthData && count((array)$networthData) > 0) {
                    @self::name('product_details')->where(['product_id'=>$val['id'], 'date' => $date])->delete();
                    $networth = (float)$networthData['networth']; //今日净值
                    $yestNetworth = "";
                    if(isset($yestNetworthData['networth']) && $yestNetworthData['networth'] !== '') {
                        $yestNetworth = (float)$yestNetworthData['networth']; //昨日净值
                    } else {
                        $yestNetworth = $NewTodayYesterdayNetworth['yestDayData'];
                    }
                    // $yestNetworth = isset($yestNetworthData['networth']) ? (float)$yestNetworthData['networth'] : 1; //昨日净值
                    $productTotalAmount = self::getUserTotalInvest(0, $val['id']);
                    // p($productTotalAmount);
                    $buy_total_number = (float)$productTotalAmount['total_number']; //购买总份数
                    $account_balance = $buy_total_number * $networth; //账户余额 = 今日净值 * 购买份数
                    $total_investment = (float)$productTotalAmount['total_invest']; //总投资
                    $total_revenue = $account_balance - $total_investment; //	总收益: 当前账户余额-总投资额
                    $daily_income = ($networth - $yestNetworth) * $buy_total_number; //	日收益:（当日净值-昨日净值）* 总购买份数
                    $daily_rate_return = (($networth - $yestNetworth) / $yestNetworth) * 100; // 日收益率:（当日净值-昨日净值）/ 昨日净值
                    $total_revenue_rate = ($total_revenue / $total_investment) * 100; // 总收益率: 总收益 / 总投资额
                    $averag_daily_rate = ProductDetails::getAverageDailyRate($val['id'], $daily_rate_return); //日均收益率: 所有日收益率的平均值
                    $daily_average_annualized = (float)$averag_daily_rate * 365; // 日均年化: 日均收益率 * 365
                    $insertData[] = [
                        'product_id' => $val['id'],
                        'date' => $date,
                        'networth' => $networth,
                        'account_balance' => $account_balance,
                        'total_revenue' => $total_revenue,
                        'daily_income' => $daily_income,
                        'daily_rate_return' => $daily_rate_return,
                        'total_revenue_rate' => $total_revenue_rate,
                        'daily_arg_rate' => $averag_daily_rate,
                        'daily_arg_annualized' => $daily_average_annualized,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                }   
            }
            if($insertData && count((array)$insertData) > 0) {
                $res = self::name('product_details')->insertAll($insertData);
                if ($res) {
                    self::commit();
                    return ['code' => 1, 'message' => '计算净值记录数据成功'];
                }
            }
            self::rollback();
            return ['code' => 0, 'message' => '计算失败'];
        } catch ( PDOException $e) {
            self::rollback();
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            return ['code' => 0, 'message' => $error_msg];
        }
    }
    
    /**
     * 获取用户累计收益
     * @author qinlh
     * @since 2022-07-11
     */
    public static function getAllCumulativeIncome($userId=0) {
        if($userId > 0) {
            $cumulative_income = 0; //累计收益
            $data = self::where('uid', $userId)->select()->toArray();
            if($data && count((array)$data) > 0) {
                foreach ($data as $key => $val) {
                    $NewTodayYesterdayNetworth = DayNetworth::getNewTodayYesterdayNetworth($val['product_id']);
                    $toDayNetworth = (float)$NewTodayYesterdayNetworth['toDayData']; //今日最新净值
                    // $amountRes = self::getNewsBuyAmount($val['product_id']);
                    $cumulative_income += ($val['total_number'] * $toDayNetworth) - $val['total_invest']; //累计收益 = 总结余（总的份数 * 最新净值） - 总投资；
                }
            }
            return $cumulative_income;
        }
        return 0;
    }   

    /**
     * 获取产品总的投资数量
     * @author qinlh
     * @since 2022-09-03
     */
    public static function getSumProductTotalInvest($product_id=0) {
        $num = 0;
        if($product_id) {
            $num = self::where('product_id', $product_id)->sum('total_invest');
        } else {
            $num = self::sum('total_invest');
        }
        return $num;
    }
}