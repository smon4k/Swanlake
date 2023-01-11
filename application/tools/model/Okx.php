<?php
// +----------------------------------------------------------------------
// | 文件说明：Okx 交易 业务逻辑 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-15
// +----------------------------------------------------------------------
namespace app\tools\model;

use think\Model;
use think\Db;
use RequestService\RequestService;
use hbdm\hbdm;
use okex\okv5;
use app\admin\model\Piggybank;

class Okx extends Base
{

    public static $apiKey = '036e3cdd-7dbc-467b-aaa7-75618a3afb83';
    public static $secret = '02AD3DEE63EA35889654616031797F22';
    public static $password = 'Zx112211@';

    /**
     * 平衡仓位 - 下单
     * @author qinlh
     * @since 2022-06-29
     */
    public static function balancePositionOrder() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $className = "\ccxt\\okex5";
        // $exchange  = new $className(array( //母账户
        //     'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
        //     'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
        //     'password' => 'Zx112211@',
        // ));
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            // $balanceDetails = self::getTradeValuation($transactionCurrency);
            // $btcBalance = $balanceDetails['btcBalance'];
            // $usdtBalance = $balanceDetails['usdtBalance'];

            // p($btcBalance);
            //获取最小下单数量
            $rubikStatTakerValume = $exchange->fetch_markets_by_type('SPOT', ['instId'=>$transactionCurrency]);
            $minSizeOrderNum = isset($rubikStatTakerValume[0]['info']['minSz']) ? $rubikStatTakerValume[0]['info']['minSz'] : 0; //最小下单数量
            $base_ccy = isset($rubikStatTakerValume[0]['info']['baseCcy']) ? $rubikStatTakerValume[0]['info']['baseCcy'] : ''; //交易货币币种
            $quote_ccy = isset($rubikStatTakerValume[0]['info']['quoteCcy']) ? $rubikStatTakerValume[0]['info']['quoteCcy'] : ''; //计价货币币种

            $changeRatioNum = 1; //涨跌比例 2%
            $balanceRatio = '1:1'; //平衡比例
            $balanceRatioArr = explode(':', $balanceRatio);

            $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
            $btcBalance = $tradeValuation['btcBalance'];
            $usdtBalance = $tradeValuation['usdtBalance'];
            $btcValuation = $tradeValuation['btcValuation'];
            $usdtValuation = $tradeValuation['usdtValuation'];
            $btcPrice = $tradeValuation['btcPrice'];

            // p($btcPrice);
            // $changeRatio = 0;
            // $changeRatio01 = abs($btcValuation / $usdtValuation);
            // $changeRatio02 = abs($usdtValuation / $btcValuation);
            $balancedValuation = self::getLastBalancedValuation();
            $changeRatio = $balancedValuation > 0 ? abs($btcValuation - $usdtValuation) / $balancedValuation * 100 : abs($btcValuation / $usdtValuation);
            $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            if($changeRatio > $changeRatioNum) { //涨跌大于1%
                // p($usdtValuation);
                if($btcValuation > $usdtValuation) { //btc的估值超过usdt时候，卖btc换成u
                    // $btcSellNum = ($btcValuation - $usdtValuation) / 2;
                    $btcSellNum = $balanceRatioArr[0] * (($btcValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
                    $btcSellOrdersNumber = $btcSellNum / $btcPrice;
                    if($btcSellOrdersNumber > $minSizeOrderNum) {
                        $result = $exchange->create_trade_order($transactionCurrency, $clientOrderId, 'market', 'sell', $btcSellOrdersNumber, []);
                        if($result['sCode'] == 0) {
                            $orderDetails = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, null); //获取成交数量
                            $theDealPrice = $btcPrice; //成交均价
                            $clinch_number = 0; //累计成交数量
                            if($orderDetails && $orderDetails['accFillSz']) {
                                $clinch_number = $orderDetails['accFillSz']; //最新成交数量
                            }
                            if($orderDetails && $orderDetails['avgPx']) {
                                $theDealPrice = $orderDetails['avgPx']; //成交均价
                            }
                            //获取上一次是否成对出现
                            $isPair = false;
                            $profit = 0;
                            // $res = Db::name('okx_piggybank')->order('id desc')->limit(1)->find();
                            $sql = "SELECT id,price,clinch_number FROM s_okx_piggybank WHERE `type`=1 AND pair = 0 ORDER BY `time` DESC,abs('$theDealPrice'-`price`) LIMIT 1;";
                            $res = Db::query($sql);
                            $pairId = 0; //配对ID
                            if($res && count((array)$res) > 0 && $btcPrice > $res[0]['price']) { //计算利润 卖出要高于买入才能配对
                                $pairId = $res[0]['id'];
                                $isPair = true;
                                // $profit = ($clinch_number * $btcPrice) - ((float)$res['clinch_number'] * (float)$res['price']);
                                $profit = $clinch_number * ($theDealPrice - (float)$res[0]['price']); // 卖出的成交数量 * 价差
                            }
                            //获取平衡状态下的USDT估值
                            $tradeValuationPoise = self::getTradeValuation($transactionCurrency);
                            $usdtValuationPoise = $tradeValuationPoise['usdtValuation'];
                            $insertOrderData = [
                                'product_name' => $transactionCurrency,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 2,
                                'order_type' => 'market',
                                'amount' => $btcSellOrdersNumber,
                                'clinch_number' => $clinch_number,
                                'price' => $theDealPrice,
                                'profit' => $profit,
                                'pair' => $pairId,
                                'currency1' => $btcBalance,
                                'currency2' => $usdtBalance,
                                'balanced_valuation' => $usdtValuationPoise,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                            if ($insertId) {
                                if(isset($pairId) && $pairId > 0) {
                                    $isPair = Db::name('okx_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
                                    if ($isPair) {
                                        Db::commit();
                                        return true;
                                    }
                                } else {
                                    Db::commit();
                                    return true;
                                }
                            }
                            Db::rollback();
                        }
                        return false;
                    } else {
                        return false;
                    }
                }
                if($btcValuation < $usdtValuation) { //btc的估值低于usdt时，买btc，u换成btc
                    // $usdtBuyNum = ($usdtValuation - $btcValuation) / 2;
                    $usdtBuyNum = $balanceRatioArr[1] * (($usdtValuation - $btcValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
                    $usdtSellOrdersNumber = $usdtBuyNum;
                    if($usdtSellOrdersNumber > $minSizeOrderNum) {
                        $result = $exchange->create_trade_order($transactionCurrency, $clientOrderId, 'market', 'buy', $usdtSellOrdersNumber, []);
                        if($result['sCode'] == 0) {
                            //获取上一次是否成对出现
                            $orderDetails = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, null); //获取成交数量
                            $theDealPrice = $btcPrice; //成交均价
                            $clinch_number = 0; //累计成交数量
                            if($orderDetails && $orderDetails['accFillSz']) {
                                $clinch_number = $orderDetails['accFillSz']; //最新成交数量
                            }
                            if($orderDetails && $orderDetails['avgPx']) {
                                $theDealPrice = $orderDetails['avgPx']; //成交均价
                            }
                            $isPair = false;
                            $profit = 0;
                            $sql = "SELECT id,price,clinch_number FROM s_okx_piggybank WHERE `type`=2 AND pair = 0 ORDER BY `time` DESC,abs('$theDealPrice'-`price`) LIMIT 1;";
                            $res = Db::query($sql);
                            $pairId = 0;
                            if($res && count((array)$res) > 0 && $btcPrice < $res[0]['price']) { //计算利润
                                $pairId = $res[0]['id'];
                                $isPair = true;
                                // $profit = ((float)$res['clinch_number'] * (float)$res['price']) - ($clinch_number * $btcPrice);
                                $profit = (float)$res[0]['clinch_number'] * ((float)$res[0]['price'] - $theDealPrice); //卖出的成交数量 * 价差
                            }
                            //获取平衡状态下的USDT估值
                            $tradeValuationPoise = self::getTradeValuation($transactionCurrency);
                            $usdtValuationPoise = $tradeValuationPoise['usdtValuation'];
                            $insertOrderData = [
                                'product_name' => $transactionCurrency,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 1,
                                'order_type' => 'market',
                                'amount' => $usdtSellOrdersNumber,
                                'clinch_number' => $clinch_number,
                                'price' => $theDealPrice,
                                'profit' => $profit,
                                'currency1' => $btcBalance,
                                'currency2' => $usdtBalance,
                                'balanced_valuation' => $usdtValuationPoise,
                                'pair' => $pairId,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                            if($insertId) {
                                if (isset($pairId) && $pairId > 0) {
                                    $isPair = Db::name('okx_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
                                    if ($isPair) {
                                        Db::commit();
                                        return true;
                                    }
                                } else {
                                    Db::commit();
                                    return true;
                                }
                            }
                            Db::rollback();
                        }
                        return false;
                    } else {
                        return false;
                    }
                }
            } else {
                return false;
            }
            return false;
        } catch (\Exception $e) {
            p($e);
            return array(0, $e->getMessage());
        }
    }

    /**
     * 每日存钱罐数据统计
     * @author qinlh
     * @since 2022-08-17
     */
    public static function piggybankDate() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $currencyArr = explode('-', $transactionCurrency);
        $currency1 = $currencyArr[0]; //交易币种
        $currency2 = $currencyArr[1]; //USDT
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try { 
            $balanceDetails = $exchange->fetch_account_balance();
            // p($balanceDetails);
            $btcBalance = 0;
            $usdtBalance = 0;
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['eq'])) {
                    if($v['ccy'] == $currency1 || $v['ccy'] == $currency2) {
                        if($v['ccy'] == $currency1 && (float)$v['eq'] > 0) {
                            $btcBalance += (float)$v['eq'];
                        }
                        if($v['ccy'] == $currency2 && (float)$v['eq'] > 0) {
                            $usdtBalance += (float)$v['eq'];
                        }
                    }
                }
            }
            $totalAssets = 0;//总资产
            $marketIndexTickers = $exchange->fetch_market_index_tickers($transactionCurrency); //获取交易BTC价格
            if($marketIndexTickers && $marketIndexTickers['idxPx'] > 0) {
                $btcValuation = $btcBalance * (float)$marketIndexTickers['idxPx'];
                $usdtValuation = $usdtBalance;
            }
            $totalAssets = $btcValuation + $usdtValuation;
            
            $date = date('Y-m-d');

            //获取总的利润
            $countProfit = Piggybank::getUStandardProfit($transactionCurrency); //获取总的利润 网格利润
            $countProfitRate = $countProfit / $totalAssets * 100; //网格总利润率 = 总利润 / 总市值
            $dayProfit = Piggybank::getUStandardProfit($transactionCurrency, $date); //获取总的利润 网格利润
            $dayProfitRate = $dayProfit / $totalAssets * 100; //网格日利润率 = 日利润 / 总市值
            $averageDayRate = Db::name('okx_piggybank_date')->whereNotIn('date', $date)->avg('grid_day_spread_rate'); //获取平均日利润率
            $averageYearRate = $averageDayRate * 365; //平均年利率 = 平均日利率 * 365
            $data = Db::name('okx_piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->find();
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
                $res = Db::name('okx_piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->update($upData);
            } else {
                $insertData = [
                    'product_name' => $transactionCurrency, 
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
                $res = Db::name('okx_piggybank_date')->insertGetId($insertData);
            }
            if($res !== false) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            // p($e);
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取交易对余额
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradePairBalance($transactionCurrency) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $currencyArr = explode('-', $transactionCurrency);
        $currency1 = $currencyArr[0]; //交易币种
        $currency2 = $currencyArr[1]; //USDT
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            $balanceDetails = $exchange->fetch_account_balance();
            // p($balance);
            $btcBalance = 0;
            $usdtBalance = 0;
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['eq'])) {
                    if($v['ccy'] == $currency1 || $v['ccy'] == $currency2) {
                        if($v['ccy'] == $currency1 && (float)$v['eq'] > 0) {
                            $btcBalance += (float)$v['eq'];
                        }
                        if($v['ccy'] == $currency2 && (float)$v['eq'] > 0) {
                            $usdtBalance += (float)$v['eq'];
                        }
                    }
                }
            }
            return ['btcBalance' => $btcBalance, 'usdtBalance' => $usdtBalance];
        } catch (\Exception $e) {
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取订单数据
     * @author qinlh
     * @since 2022-08-19
     */
    public static function fetchTradeOrder($transactionCurrency="BTC-USDT", $clientOrderId='Zx2022082010055985') {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            $tradeOrder = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, null);
            return $tradeOrder;
        } catch (\Exception $e) {
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取单个产品行情信息 价格
     * @author qinlh
     * @since 2022-08-19
     */
    public static function fetchMarketIndexTickers($transactionCurrency) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            $marketIndexTickers = $exchange->fetch_ticker($transactionCurrency); //获取交易BTC价格
            return $marketIndexTickers;
        } catch (\Exception $e) {
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取交易对估值
     * @author qinlh
     * @since 2022-08-19
     */
    public static function getTradeValuation($transactionCurrency) {
        $balanceDetails = self::getTradePairBalance($transactionCurrency);
        $usdtBalance = $balanceDetails['usdtBalance'];
        $btcBalance = $balanceDetails['btcBalance'];
        $marketIndexTickers = self::fetchMarketIndexTickers($transactionCurrency); //获取交易BTC价格
        $btcPrice = 1;
        $btcValuation = 0;
        $usdtValuation = 0;
        if($marketIndexTickers && $marketIndexTickers['last'] > 0) {
            $btcPrice = (float)$marketIndexTickers['last'];
            $btcValuation = $btcBalance * (float)$marketIndexTickers['last'];
            $usdtValuation = $usdtBalance;
        }
        return ['btcPrice' => $btcPrice, 'usdtBalance' => $usdtBalance, 'btcBalance' => $btcBalance, 'btcValuation' => $btcValuation, 'usdtValuation' => $usdtValuation];
    }


    /**
     * 获取上一次平衡估值
     * @author qinlh
     * @since 2022-08-19
     */
    public  static function getLastBalancedValuation() {
        $data = Db::name('okx_piggybank')->order('id desc, time desc')->find();
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
        $data = Db::name('okx_piggybank')->order('id desc, time desc')->find();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

    /**
     * 测试平衡仓位
     * @author qinlh
     * @since 2022-11-21
     */
    public static function testBalancePosition() {
        $result = [];
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $currencyArr = explode('-', $transactionCurrency);
        $currency1 = $currencyArr[0]; //交易币种
        $currency2 = $currencyArr[1]; //USDT
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        // $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        // $result = $exchange->create_order($order_symbol, 'market', 'BUY', '1', null, ['newClientOrderId' => $clientOrderId]);
        // $result = $exchange->fetch_markets(['symbol'=>$symbol]);
        // p($result);

        $changeRatioNum = 1; //涨跌比例 2%
        $balanceRatio = '1:1'; //平衡比例
        $balanceRatioArr = explode(':', $balanceRatio);
        $sellPropr = ($changeRatioNum / 2) + ($changeRatioNum / 100); //出售比例
        $buyPropr = ($changeRatioNum / 2) - ($changeRatioNum / 100); //购买比例

        //获取最小下单数量
        $rubikStatTakerValume = $exchange->fetch_markets_by_type('SPOT', ['instId'=>$transactionCurrency]);
        $minSizeOrderNum = isset($rubikStatTakerValume[0]['info']['minSz']) ? $rubikStatTakerValume[0]['info']['minSz'] : 0; //最小下单数量
        $base_ccy = isset($rubikStatTakerValume[0]['info']['baseCcy']) ? $rubikStatTakerValume[0]['info']['baseCcy'] : ''; //交易货币币种
        $quote_ccy = isset($rubikStatTakerValume[0]['info']['quoteCcy']) ? $rubikStatTakerValume[0]['info']['quoteCcy'] : ''; //计价货币币种

        $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
        $btcBalance = $tradeValuation['btcBalance'];
        $usdtBalance = $tradeValuation['usdtBalance'];
        $btcValuation = $tradeValuation['btcValuation'];
        $usdtValuation = $tradeValuation['usdtValuation'];
        $btcPrice = $tradeValuation['btcPrice'];


        $balancedValuation = self::getLastBalancedValuation(); // 获取上一次平衡状态下估值
        $changeRatio = $balancedValuation > 0 ? abs($btcValuation - $usdtValuation) / $balancedValuation * 100 : abs($btcValuation - $usdtValuation) / $usdtValuation * 100;

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
        if($btcValuation > $usdtValuation) { //BIFI的估值超过BUSD时候，卖BIFI换成BUSDT
            $result['sellOrdersNumberStr'] = $currency1 . '出售数量: ' . $btcSellOrdersNumber ;
        }
        if($btcValuation < $usdtValuation) { //BIFI的估值低于BUSD时，买BIFI，换成BUSD
            $result['sellOrdersNumberStr'] = $currency2 . '购买数量: ' . $usdtBuyOrdersNumber ;
        }

        //计算下一次下单购买出售数据
        $result['pendingOrder'] = [];
        $sellingPrice = $lastPrice * $sellPropr; //出售价格
        $btcSellValuation = $sellingPrice * $btcBalance; //BTC 出售估值

        $buyingPrice = $lastPrice * $buyPropr; //购买价格
        $btcBuyValuation = $buyingPrice * $btcBalance; //BTC 购买估值

        //计算购买数量
        $buyNum = $balanceRatioArr[1] * (($usdtValuation - $btcBuyValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        $buyOrdersNumber = $buyNum / $buyingPrice; //购买数量
        $usdtBuyClinchBalance = $usdtBalance - $buyNum; //挂买以后USDT数量 USDT余额 减去 购买busd数量
        $btcBuyClinchBalance = $btcBalance + $buyOrdersNumber; //挂买以后BTC数量 BTC余额 加上 购买数量

        $result['pendingOrder']['buy']['price'] = $buyingPrice;
        $result['pendingOrder']['buy']['amount'] = $buyOrdersNumber;
        $result['pendingOrder']['buy']['btcValuation'] = $btcBuyClinchBalance * $buyingPrice;
        $result['pendingOrder']['buy']['usdtValuation'] = $usdtBuyClinchBalance;

        //计算 出售数量
        $sellNum = $balanceRatioArr[0] * (($btcSellValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        $sellOrdersNumber = $sellNum / $sellingPrice;
        $usdtSellClinchBalance = $usdtBalance + $sellNum; //挂卖以后USDT数量 USDT余额 加上 出售USDT数量
        $btcSellClinchBalance = $btcBalance - $sellOrdersNumber; //挂卖以后BIFI数量 BTC余额 减去 出售数量
        $result['pendingOrder']['sell']['price'] = $sellingPrice;
        $result['pendingOrder']['sell']['amount'] = $sellOrdersNumber;
        $result['pendingOrder']['sell']['btcValuation'] = $btcSellClinchBalance * $sellingPrice;
        $result['pendingOrder']['sell']['usdtValuation'] = $usdtSellClinchBalance;

        return $result;
        // echo "最小下单量: " . $minSizeOrderNum . "<br>";
        // echo "交易货币币种: " . $base_ccy . "<br>";
        // echo "计价货币币种: " . $quote_ccy . "<br>";
        // echo "BIFI价格: " . $tradingPrice . "<br>";
        // echo "BUSD余额: " . $busdBalance . "<br>BUSD估值: " . $usdtValuation . "<br>";
        // echo "BIFI余额: " . $bifiBalance . "<br>BIFI估值: " . $bifiValuation . "<br>";
        // echo "涨跌比例: " . $changeRatio . "<br>";
        // if($bifiValuation > $usdtValuation) { //BIFI的估值超过BUSD时候，卖BIFI换成BUSDT
        //     echo "BIFI出售数量: " . $bifiSellOrdersNumber . "<br>";
        // }
        // if($bifiValuation < $usdtValuation) { //BIFI的估值低于BUSD时，买BIFI，换成BUSD
        //     echo "BUSD购买数量: " . $busdBuyOrdersNumber . "<br>";
        // }
    }

    /**
     * 获取交易对名称
     * @author qinlh
     * @since 2023-01-10
     */
    public static function getTradingPairData($exchange='') {
        $data = Db::name('piggybank_list')->where(['exchange' => $exchange, 'state' => 1])->find();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

     /**
     * 获取交易对名称
     * @author qinlh
     * @since 2023-01-10
     */
    public static function gettTradingPairName($exchange='') {
        $data = Db::name('piggybank_list')->where(['exchange' => $exchange, 'state' => 1])->find();
        if($data && count((array)$data) > 0) {
            return $data['name'];
        }
        return [];
    }

    /**
     * 获取交易对ID
     * @author qinlh
     * @since 2023-01-10
     */
    public static function gettTradingPairId($exchange='') {
        $data = Db::name('piggybank_list')->where(['exchange' => $exchange, 'state' => 1])->find();
        if($data && count((array)$data) > 0) {
            return $data['id'];
        }
        return [];
    }
}