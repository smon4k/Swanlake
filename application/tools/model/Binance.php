<?php
// +----------------------------------------------------------------------
// | 文件说明：币安 交易 业务逻辑 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-11-19
// +----------------------------------------------------------------------
namespace app\tools\model;

use think\Model;
use think\Db;
use RequestService\RequestService;
use hbdm\hbdm;
use okex\okv5;
use app\admin\model\BinancePiggybank;

class Binance extends Base
{

    public static $apiKey = 'e4ddCJS2dng12vuYOCl9X37PLXn59YMPvaZCOSvx9Ci8oHjXva9hIKXXKVlwqxc4';
    public static $secret = 'ab663zxlTlldmqxMPL6Xv5Ws5hwBDFfVburcp4Z8BZ2lu11TOXDNDJcRUDawqApb';
    public static $password = 'Zx112211@';

    /**
     * 平衡仓位 - 下单
     * @author qinlh
     * @since 2022-06-29
     */
    public static function balancePositionOrder() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = "BIFI-BUSD"; //交易币种
        $symbol = str_replace("-",'', $transactionCurrency);
        $order_symbol = str_replace("-",'/', $transactionCurrency);
        $className = "\binance";
        $className = "\ccxt\\binance";
        // $exchange  = new $className(array( //母账户
        //     'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
        //     'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
        //     'password' => 'Zx112211@',
        // ));
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret
        ));
        // p($exchange);
        try {
            // $balanceDetails = self::getTradeValuation($transactionCurrency);
            // $btcBalance = $balanceDetails['btcBalance'];
            // $usdtBalance = $balanceDetails['usdtBalance'];

            // p($btcBalance);
            //获取最小下单数量
            $rubikStatTakerValume = $exchange->fetch_markets(['symbol'=>$symbol]);
            // p($rubikStatTakerValume);
            $minSizeOrderNum = isset($rubikStatTakerValume[0]['limits']['amount']['min']) ? $rubikStatTakerValume[0]['limits']['amount']['min'] : 0; //最小下单数量
            $base_ccy = isset($rubikStatTakerValume[0]['base']) ? $rubikStatTakerValume[0]['base'] : ''; //交易货币币种
            $quote_ccy = isset($rubikStatTakerValume[0]['quote']) ? $rubikStatTakerValume[0]['quote'] : ''; //计价货币币种
            // p($minSizeOrderNum);
            $changeRatioNum = 3; //涨跌比例 2%
            $balanceRatio = '1:1'; //平衡比例
            $balanceRatioArr = explode(':', $balanceRatio);
            // p($balanceRatioArr);
            $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
            // p($tradeValuation);
            $bifiBalance = $tradeValuation['bifiBalance'];
            $busdBalance = $tradeValuation['busdBalance'];
            $bifiValuation = $tradeValuation['bifiValuation'];
            $busdValuation = $tradeValuation['busdValuation'];
            $tradingPrice = $tradeValuation['tradingPrice'];

            // p($btcPrice);
            // $changeRatio = 0;
            // $changeRatio01 = abs($btcValuation / $usdtValuation);
            // $changeRatio02 = abs($usdtValuation / $btcValuation);
            $balancedValuation = self::getLastBalancedValuation(); // 获取上一次平衡状态下估值
            $changeRatio = $balancedValuation > 0 ? abs($bifiValuation - $busdValuation) / $balancedValuation * 100 : abs($bifiValuation / $busdValuation);
            $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            // $result = $exchange->create_order($order_symbol, 'market', 'SELL', '0.01', null, ['newClientOrderId' => $clientOrderId]);
            // p($changeRatio);
            if((float)$changeRatio > $changeRatioNum) { //涨跌大于1%
                // p($busdValuation);
                if($bifiValuation > $busdValuation) { //btc的估值超过usdt时候，卖btc换成u
                    // $btcSellNum = ($bifiValuation - $busdValuation) / 2;
                    $btcSellNum = $balanceRatioArr[0] * (($bifiValuation - $busdValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
                    $btcSellOrdersNumber = $btcSellNum / $tradingPrice;
                    // p($btcSellOrdersNumber);
                    if((float)$btcSellOrdersNumber > (float)$minSizeOrderNum) {
                        $orderDetails = $exchange->create_order($order_symbol, 'market', 'SELL', $btcSellOrdersNumber, null, ['newClientOrderId' => $clientOrderId]);
                        // print_r($orderDetails['info']);
                        if($orderDetails && $orderDetails['info']) {
                            $theDealPrice = $tradingPrice; //成交均价
                            $clinch_number = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['qty'] ? $orderDetails['info']['fills'][0]['qty'] : 0; //最新成交数量
                            $theDealPrice = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['price'] ? $orderDetails['info']['fills'][0]['price'] : 1; //成交均价
                            //获取上一次是否成对出现
                            $isPair = false;
                            $profit = 0;
                            // $res = Db::name('okx_piggybank')->order('id desc')->limit(1)->find();
                            $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=1 AND pair = 0 ORDER BY `time` DESC,abs('$theDealPrice'-`price`) LIMIT 1;";
                            $res = Db::query($sql);
                            $pairId = 0; //配对ID
                            if($res && count((array)$res) > 0 && $tradingPrice > $res[0]['price']) { //计算利润 卖出要高于买入才能配对
                                $pairId = $res[0]['id'];
                                $isPair = true;
                                // $profit = ($clinch_number * $tradingPrice) - ((float)$res['clinch_number'] * (float)$res['price']);
                                $profit = $clinch_number * ($theDealPrice - (float)$res[0]['price']); // 卖出的成交数量 * 价差
                            }
                            //获取平衡状态下的USDT估值
                            $tradeValuationPoise = self::getTradeValuation($transactionCurrency);
                            $usdtValuationPoise = $tradeValuationPoise['busdValuation'];
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
                                'currency1' => $bifiBalance,
                                'currency2' => $busdBalance,
                                'balanced_valuation' => $usdtValuationPoise,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('binance_piggybank')->insertGetId($insertOrderData);
                            if ($insertId) {
                                if(isset($pairId) && $pairId > 0) {
                                    $isPair = Db::name('binance_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
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
                if($bifiValuation < $busdValuation) { //btc的估值低于usdt时，买btc，u换成btc
                    // $usdtBuyNum = ($busdValuation - $bifiValuation) / 2;
                    $usdtBuyNum = $balanceRatioArr[1] * (($busdValuation - $bifiValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
                    $usdtSellOrdersNumber = $usdtBuyNum;
                    // p($usdtSellOrdersNumber);
                    if($usdtSellOrdersNumber > $minSizeOrderNum) {
                        $orderDetails = $exchange->create_order($order_symbol, 'market', 'BUY', $usdtSellOrdersNumber, null, ['newClientOrderId' => $clientOrderId]);
                        if($orderDetails && $orderDetails['info']) {
                            //获取上一次是否成对出现
                            // $orderDetails = $exchange->fetch_trade_order($symbol, $clientOrderId, null); //获取成交数量
                            $theDealPrice = $tradingPrice; //成交均价
                            $clinch_number = 0; //累计成交数量
                            $clinch_number = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['qty'] ? $orderDetails['info']['fills'][0]['qty'] : 0; //最新成交数量
                            $theDealPrice = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['price'] ? $orderDetails['info']['fills'][0]['price'] : 1; //成交均价
                            $isPair = false;
                            $profit = 0;
                            $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=2 AND pair = 0 ORDER BY `time` DESC,abs('$theDealPrice'-`price`) LIMIT 1;";
                            $res = Db::query($sql);
                            $pairId = 0;
                            if($res && count((array)$res) > 0 && $tradingPrice < $res[0]['price']) { //计算利润
                                $pairId = $res[0]['id'];
                                $isPair = true;
                                // $profit = ((float)$res['clinch_number'] * (float)$res['price']) - ($clinch_number * $tradingPrice);
                                $profit = (float)$res[0]['clinch_number'] * ((float)$res[0]['price'] - $theDealPrice); //卖出的成交数量 * 价差
                            }
                            //获取平衡状态下的USDT估值
                            $tradeValuationPoise = self::getTradeValuation($transactionCurrency);
                            $usdtValuationPoise = $tradeValuationPoise['busdValuation'];
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
                                'currency1' => $bifiBalance,
                                'currency2' => $busdBalance,
                                'balanced_valuation' => $usdtValuationPoise,
                                'pair' => $pairId,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('binance_piggybank')->insertGetId($insertOrderData);
                            if($insertId) {
                                if (isset($pairId) && $pairId > 0) {
                                    $isPair = Db::name('binance_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
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
        $transactionCurrency = "BIFI-BUSD"; //交易币种
        $assetArr = explode('-', $transactionCurrency);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try { 
            $balanceDetails = $exchange->fetch_balance([]);
            // p($balanceDetails);
            $bifiBalance = 0;
            $busdBalance = 0;
            foreach ($balanceDetails['info']['balances'] as $k => $v) {
                if(isset($v['asset'])) {
                    if($v['asset'] == $assetArr[1] || $v['asset'] == $assetArr[0]) {
                        if($v['asset'] == $assetArr[1] && (float)$v['free'] > 0) {
                            $busdBalance += (float)$v['free'];
                        }
                        if($v['asset'] == $assetArr[0] && (float)$v['free'] > 0) {
                            $bifiBalance += (float)$v['free'];
                        }
                    }
                }
            }
            $totalAssets = 0;//总资产
            $symbol = str_replace("-",'', $transactionCurrency);
            // $marketIndexTickers = $exchange->fetch_market_index_tickers($transactionCurrency); //获取交易BTC价格
            $marketIndexTickers = $exchange->fetch_ticker_price($symbol); //获取交易BTC价格
            if($marketIndexTickers && $marketIndexTickers['price'] > 0) {
                $bifiValuation = $bifiBalance * (float)$marketIndexTickers['price'];
                $busdValuation = $busdBalance;
            }
            $totalAssets = $bifiValuation + $busdValuation;
            
            $date = date('Y-m-d');

            //获取总的利润
            $countProfit = BinancePiggybank::getUStandardProfit($transactionCurrency); //获取总的利润 网格利润
            $dayProfit = BinancePiggybank::getUStandardProfit($transactionCurrency, $date); //获取总的利润 网格利润
            $data = Db::name('binance_piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->find();
            if($data && count((array)$data) > 0) {
                $upData = [
                    'count_market_value'=>$totalAssets, 
                    'grid_spread' => $countProfit,
                    'grid_day_spread' => $dayProfit,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $res = Db::name('binance_piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->update($upData);
            } else {
                $insertData = [
                    'product_name' => $transactionCurrency, 
                    'date'=>$date, 
                    'count_market_value'=>$totalAssets, 
                    'grid_spread' => $countProfit,
                    'grid_day_spread' => $dayProfit,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $res = Db::name('binance_piggybank_date')->insertGetId($insertData);
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
        $assetArr = explode('-', $transactionCurrency);
        $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
        $dataJson = self::getCache($cache_params);
        $data = json_decode($dataJson, true);
        // if($data && count((array)$data) > 0) {
        //     return $data;
        // }
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $balanceDetails = $exchange->fetch_balance([]);
            // p($balanceDetails);
            $busdBalance = 0;
            $bifiBalance = 0;
            foreach ($balanceDetails['info']['balances'] as $k => $v) {
                if(isset($v['asset'])) {
                    if($v['asset'] == $assetArr[1] || $v['asset'] == $assetArr[0]) {
                        if($v['asset'] == $assetArr[1] && (float)$v['free'] > 0) {
                            $busdBalance += (float)$v['free'];
                        }
                        if($v['asset'] == $assetArr[0] && (float)$v['free'] > 0) {
                            $bifiBalance += (float)$v['free'];
                        }
                    }
                }
            }
            $returnArray = ['busdBalance' => $busdBalance, 'bifiBalance' => $bifiBalance];
            $dataJson = json_encode($returnArray);
            self::setCache($cache_params, $dataJson);
            return $returnArray;
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
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $tradeOrder = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, null);
            return $tradeOrder;
        } catch (\Exception $e) {
            return array(0, $e->getMessage());
        }
    }

    /**
     * 获取交易对价格
     * @author qinlh
     * @since 2022-08-19
     */
    public static function fetchMarketIndexTickers($transactionCurrency) {
        $cache_params = ['class' => __CLASS__,'key' => md5(json_encode(func_get_args())),'func' => __FUNCTION__];
        $dataJson = self::getCache($cache_params);
        $data = json_decode($dataJson, true);
        // if($data && count((array)$data) > 0) {
        //     return $data;
        // }
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret
        ));
        try {
            // $symbols = explode('-', $transactionCurrency);
            $symbol = str_replace("-",'', $transactionCurrency);
            $marketIndexPrice = $exchange->fetch_ticker_price($symbol); //获取交易BTC价格
            $dataJson = json_encode($marketIndexPrice);
            self::setCache($cache_params, $dataJson);
            return $marketIndexPrice;
        } catch (\Exception $e) {
            // p($e);
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
        // p($balanceDetails);
        $busdBalance = $balanceDetails['busdBalance'];
        $bifiBalance = $balanceDetails['bifiBalance'];
        $marketIndexTickers = self::fetchMarketIndexTickers($transactionCurrency); //获取交易BIFI价格
        $tradingPrice = 1; //交易对价格
        $bifiValuation = 0;
        $busdValuation = 0;
        if($marketIndexTickers && $marketIndexTickers['price'] > 0) {
            $tradingPrice = (float)$marketIndexTickers['price'];
            $bifiValuation = $bifiBalance * (float)$marketIndexTickers['price'];
            $busdValuation = $busdBalance;
        }
        return ['tradingPrice' => $tradingPrice, 'busdBalance' => $busdBalance, 'bifiBalance' => $bifiBalance, 'bifiValuation' => $bifiValuation, 'busdValuation' => $busdValuation];
    }


    /**
     * 获取上一次平衡估值
     * @author qinlh
     * @since 2022-08-19
     */
    public  static function getLastBalancedValuation() {
        $data = Db::name('binance_piggybank')->order('id desc, time desc')->find();
        if($data && count((array)$data) > 0) {
            return $data['balanced_valuation'];
        }
        return 0;
    }

    /**
     * 测试平衡仓位
     * @author qinlh
     * @since 2022-11-21
     */
    public static function testBalancePosition() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = "BIFI-BUSD"; //交易币种
        $symbol = str_replace("-",'', $transactionCurrency);
        $order_symbol = str_replace("-",'/', $transactionCurrency);
        $className = "\binance";
        $className = "\ccxt\\binance";
        // $exchange  = new $className(array( //母账户
        //     'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
        //     'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
        //     'password' => 'Zx112211@',
        // ));
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret
        ));
        $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        // $result = $exchange->create_order($order_symbol, 'market', 'BUY', '1', null, ['newClientOrderId' => $clientOrderId]);
        // $result = $exchange->fetch_markets(['symbol'=>$symbol]);
        // p($result);

        $changeRatioNum = 3; //涨跌比例 2%
        $balanceRatio = '1:1'; //平衡比例
        $balanceRatioArr = explode(':', $balanceRatio);

        //获取最小下单数量
        $rubikStatTakerValume = $exchange->fetch_markets(['symbol'=>$symbol]);
        // p($rubikStatTakerValume);
        $minSizeOrderNum = isset($rubikStatTakerValume[0]['limits']['amount']['min']) ? $rubikStatTakerValume[0]['limits']['amount']['min'] : 0; //最小下单数量
        $base_ccy = isset($rubikStatTakerValume[0]['base']) ? $rubikStatTakerValume[0]['base'] : ''; //交易货币币种
        $quote_ccy = isset($rubikStatTakerValume[0]['quote']) ? $rubikStatTakerValume[0]['quote'] : ''; //计价货币币种

        $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
        // p($tradeValuation);
        $bifiBalance = $tradeValuation['bifiBalance'];
        $busdBalance = $tradeValuation['busdBalance'];
        $bifiValuation = $tradeValuation['bifiValuation'];
        $busdValuation = $tradeValuation['busdValuation'];
        $tradingPrice = $tradeValuation['tradingPrice'];

        $balancedValuation = self::getLastBalancedValuation(); // 获取上一次平衡状态下估值
        $changeRatio = $balancedValuation > 0 ? abs($bifiValuation - $busdValuation) / $balancedValuation * 100 : abs($bifiValuation / $busdValuation);

        $bifiSellNum = $balanceRatioArr[0] * (($bifiValuation - $busdValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        $bifiSellOrdersNumber = $bifiSellNum / $tradingPrice;

        $busdBuyNum = $balanceRatioArr[1] * (($busdValuation - $bifiValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
        $busdBuyOrdersNumber = $busdBuyNum;

        echo "最小下单量: " . $minSizeOrderNum . "<br>";
        echo "交易货币币种: " . $base_ccy . "<br>";
        echo "计价货币币种: " . $quote_ccy . "<br>";
        echo "BIFI价格: " . $tradingPrice . "<br>";
        echo "BUSD余额: " . $busdBalance . "<br>BUSD估值: " . $busdValuation . "<br>";
        echo "BIFI余额: " . $bifiBalance . "<br>BIFI估值: " . $bifiValuation . "<br>";
        echo "涨跌比例: " . $changeRatio . "<br>";
        if($bifiValuation > $busdValuation) { //BIFI的估值超过BUSD时候，卖BIFI换成BUSDT
            echo "BIFI出售数量: " . $bifiSellOrdersNumber . "<br>";
        }
        if($bifiValuation < $busdValuation) { //BIFI的估值低于BUSD时，买BIFI，换成BUSD
            echo "BUSD购买数量: " . $busdBuyOrdersNumber . "<br>";
        }
    }
    
}