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
use app\admin\model\BinanceConfig;

class Binance extends Base
{

    public static $apiKey = 'xMPrGnl0shwpxVGAqzhpy3UQEWcUNl7eSMcnws0r7FPX0nYaRRxYsZQMgqaqc3Aa';
    public static $secret = 'vTatOBTdm1jL9OGeI9YC7jOviD89nilUja2d8bMRkYF2p52DWzN0tT5RtXL6ZvsU';
    public static $password = 'Zx112211@';

    /**
     * 平衡仓位 - 下单
     * @author qinlh
     * @since 2022-06-29
     */
    public static function balancePositionOrder($isPending=false) {
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
            // p($minSizeOrderNum);
            $changeRatioNum = BinanceConfig::getChangeRatio(); //涨跌比例
            // $changeRatioNum = 2; //涨跌比例 2%
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
            $changeRatio = $balancedValuation > 0 ? abs($bifiValuation - $busdValuation) / $balancedValuation * 100 : abs($bifiValuation - $busdValuation) / $busdValuation * 100;
            $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            // $result = $exchange->create_order($order_symbol, 'market', 'SELL', '0.01', null, ['newClientOrderId' => $clientOrderId]);
            // p($changeRatio);
            if((float)$changeRatio > $changeRatioNum) { //涨跌大于1%
                echo "涨跌幅度大于".$changeRatioNum."% 开始下单\r\n";
                // p($busdValuation);
                //获取最小下单数量
                $rubikStatTakerValume = $exchange->fetch_markets(['symbol'=>$symbol]);
                // p($rubikStatTakerValume);
                $minSizeOrderNum = isset($rubikStatTakerValume[0]['limits']['amount']['min']) ? $rubikStatTakerValume[0]['limits']['amount']['min'] : 0; //最小下单数量
                $base_ccy = isset($rubikStatTakerValume[0]['base']) ? $rubikStatTakerValume[0]['base'] : ''; //交易货币币种
                $quote_ccy = isset($rubikStatTakerValume[0]['quote']) ? $rubikStatTakerValume[0]['quote'] : ''; //计价货币币种
                if($bifiValuation > $busdValuation) { //btc的估值超过usdt时候，卖btc换成u
                    echo "BIFI的估值大于USDT 开始下单出售 \r\n";
                    // $btcSellNum = ($bifiValuation - $busdValuation) / 2;
                    $btcSellNum = $balanceRatioArr[0] * (($bifiValuation - $busdValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
                    $btcSellOrdersNumber = $btcSellNum / $tradingPrice;
                    // p($btcSellOrdersNumber);
                    if((float)$btcSellOrdersNumber > (float)$minSizeOrderNum) {
                        echo "下单出售 大于 最小下单量".$minSizeOrderNum." \r\n";
                        $orderDetails = $exchange->create_order($order_symbol, 'market', 'SELL', $btcSellOrdersNumber, null, ['newClientOrderId' => $clientOrderId]);
                        // print_r($orderDetails['info']);
                        if($orderDetails && $orderDetails['info']) {
                            echo "下单出售成功 \r\n";
                            //获取平衡状态下的USDT估值
                            $order_id = $orderDetails['info']['orderId']; //返回的订单id
                            $clinch_number = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['qty'] ? $orderDetails['info']['fills'][0]['qty'] : 0; //最新成交数量
                            $makeDealPrice = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['price'] ? $orderDetails['info']['fills'][0]['price'] : 1; //成交均价
                            //获取上一次是否成对出现
                            $isPair = false;
                            $profit = 0;
                            // $res = Db::name('okx_piggybank')->order('id desc')->limit(1)->find();
                            $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=1 AND pair = 0 ORDER BY `time` DESC,abs('$makeDealPrice'-`price`) LIMIT 1;";
                            $res = Db::query($sql);
                            $pairId = 0; //配对ID
                            if($res && count((array)$res) > 0 && $makeDealPrice > $res[0]['price']) { //计算利润 卖出要高于买入才能配对
                                $pairId = $res[0]['id'];
                                $isPair = true;
                                // $profit = ($clinch_number * $tradingPrice) - ((float)$res['clinch_number'] * (float)$res['price']);
                                $profit = $clinch_number * ($makeDealPrice - (float)$res[0]['price']); // 卖出的成交数量 * 价差
                            }
                            $tradeValuationPoise = self::getTradeValuation($transactionCurrency);
                            $theDealPrice = $tradeValuationPoise['tradingPrice']; //最新价格
                            $usdtValuationPoise = $tradeValuationPoise['busdValuation'];
                            $insertOrderData = [
                                'product_name' => $transactionCurrency,
                                'order_id' => $order_id,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 2,
                                'order_type' => 'market',
                                'amount' => $btcSellOrdersNumber,
                                'clinch_number' => $clinch_number,
                                'price' => $theDealPrice,
                                'make_deal_price' => $makeDealPrice,
                                'profit' => $profit,
                                'pair' => $pairId,
                                'currency1' => $tradeValuationPoise['bifiBalance'],
                                'currency2' => $tradeValuationPoise['busdBalance'],
                                'balanced_valuation' => $usdtValuationPoise,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('binance_piggybank')->insertGetId($insertOrderData);
                            if ($insertId) {
                                echo "写入出售下单数据成功 \r\n";
                                if(isset($pairId) && $pairId > 0) {
                                    echo "出售下单配对成功 \r\n";
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
                        echo "下单出售 小于 最小下单量".$minSizeOrderNum." 停止下单 \r\n";
                        return false;
                    }
                }
                if($bifiValuation < $busdValuation) { //btc的估值低于usdt时，买btc，u换成btc
                    echo "BIFI的估值小于BUSD 开始下单购买 \r\n";
                    // $usdtBuyNum = ($busdValuation - $bifiValuation) / 2;
                    $usdtBuyNum = $balanceRatioArr[1] * (($busdValuation - $bifiValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
                    $usdtSellOrdersNumber = $usdtBuyNum / $tradingPrice;
                    // p($usdtSellOrdersNumber);
                    if($usdtSellOrdersNumber > $minSizeOrderNum) {
                        echo "下单购买 大于 最小下单量".$minSizeOrderNum." \r\n";
                        $orderDetails = $exchange->create_order($order_symbol, 'market', 'BUY', $usdtSellOrdersNumber, null, ['newClientOrderId' => $clientOrderId]);
                        if($orderDetails && $orderDetails['info']) {
                            echo "下单购买成功 \r\n";
                            //获取上一次是否成对出现
                            // $orderDetails = $exchange->fetch_trade_order($symbol, $clientOrderId, null); //获取成交数量
                            $order_id = $orderDetails['info']['orderId']; //返回的订单id
                            $clinch_number = 0; //累计成交数量
                            $clinch_number = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['qty'] ? $orderDetails['info']['fills'][0]['qty'] : 0; //最新成交数量
                            $makeDealPrice = $orderDetails['info']['fills'][0] && $orderDetails['info']['fills'][0]['price'] ? $orderDetails['info']['fills'][0]['price'] : 1; //成交均价
                            $isPair = false;
                            $profit = 0;
                            $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=2 AND pair = 0 ORDER BY `time` DESC,abs('$makeDealPrice'-`price`) LIMIT 1;";
                            $res = Db::query($sql);
                            $pairId = 0;
                            if($res && count((array)$res) > 0 && $makeDealPrice < $res[0]['price']) { //计算利润
                                $pairId = $res[0]['id'];
                                $isPair = true;
                                // $profit = ((float)$res['clinch_number'] * (float)$res['price']) - ($clinch_number * $tradingPrice);
                                $profit = (float)$res[0]['clinch_number'] * ((float)$res[0]['price'] - $makeDealPrice); //卖出的成交数量 * 价差
                            }
                            //获取平衡状态下的USDT估值
                            $tradeValuationPoise = self::getTradeValuation($transactionCurrency);
                            $theDealPrice = $tradeValuationPoise['tradingPrice']; //最新价格
                            $usdtValuationPoise = $tradeValuationPoise['busdValuation'];
                            $insertOrderData = [
                                'product_name' => $transactionCurrency,
                                'order_id' => $order_id,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 1,
                                'order_type' => 'market',
                                'amount' => $usdtSellOrdersNumber,
                                'clinch_number' => $clinch_number,
                                'price' => $theDealPrice,
                                'make_deal_price' => $makeDealPrice,
                                'profit' => $profit,
                                'currency1' => $tradeValuationPoise['bifiBalance'],
                                'currency2' => $tradeValuationPoise['busdBalance'],
                                'balanced_valuation' => $usdtValuationPoise,
                                'pair' => $pairId,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('binance_piggybank')->insertGetId($insertOrderData);
                            if($insertId) {
                                echo "写入购买下单数据成功 \r\n";
                                if (isset($pairId) && $pairId > 0) {
                                    $isPair = Db::name('binance_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
                                    if ($isPair) {
                                        echo "出售下购买配对成功 \r\n";
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
                        echo "下单出售 小于 最小下单量".$minSizeOrderNum." 停止下单 \r\n";
                        return true;
                    }
                }
            } else {
                echo "涨跌幅度小于".$changeRatioNum."% 停止下单\r\n";
                if($isPending) {
                    return false;
                } else {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            Db::rollback();
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT Error \r\n".$error_msg);
            return false;
        }
    }

    /**
     * 平衡仓位 - 挂单
     * @author qinlh
     * @since 2022-11-25
     */
    public static function balancePendingOrder() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = "BIFI-BUSD"; //交易币种
        $symbol = str_replace("-",'', $transactionCurrency);
        $order_symbol = str_replace("-",'/', $transactionCurrency);
        $className = "\binance";
        $className = "\ccxt\\binance";
        $exchange  = new $className(array(
            'apiKey' => self::$apiKey,
            'secret' => self::$secret
        ));
        Db::startTrans();
        try {
            // $isPendingOrder = self::startPendingOrder($transactionCurrency); //开始挂单
            // exit();
            // $ordersList = self::fetchGetOpenOrder($order_symbol);
            // p($ordersList);
            $peningOrderList = self::getOpenPeningOrders();
            if($peningOrderList && count((array)$peningOrderList) > 1) {
                $isReOrder = false; //是否撤单重新挂单
                $reOrderNum = 0; //撤单数量

                $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价
                $busdValuation = $tradeValuation['busdValuation'];
                $bifiValuation = $tradeValuation['bifiValuation'];
                $minMaxRes = bccomp($busdValuation, $bifiValuation);
                if($minMaxRes == 1) { //busd大
                    $perDiffRes = ($busdValuation - $bifiValuation) / $bifiValuation * 100;
                } else {
                    $perDiffRes = ($bifiValuation - $busdValuation) / $busdValuation * 100;
                }
                if($perDiffRes > 2) { //如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
                    echo "两个币种估值差大于2% 开始全部撤单 \r\n";
                    $orderCancelRes = self::fetchCancelOpenOrder($order_symbol);
                    if($orderCancelRes) { //撤单成功 开始吃单
                        echo "撤单成功 开始吃单 \r\n";
                        $toEatMeal = self::balancePositionOrder(1);
                        if($toEatMeal) { //如果吃单成功 重新挂单
                            Db::commit();
                            Db::startTrans();
                            echo "吃单成功 重新挂单 \r\n";
                            $isPendingOrder = self::startPendingOrder($transactionCurrency);
                            if($isPendingOrder) {
                                echo "已重新挂单 \r\n";
                                Db::commit();
                                return true;
                            }
                        } else {
                            return true;
                        }
                    }
                }

                $buyOrderData = $peningOrderList['buy']; //购买挂单数据
                $sellOrderData = $peningOrderList['sell']; //出售挂单数据
                $makeArray = []; //成交的数据

                $orderAmount = 0;
                $dealAmount = 0;
                $side_type = '';
                $minOrderAmount = 0;
                $make_side = 0; //成交状态 1：buy 2： sell
                $dealPrice = 0; //成交均价

                //首先获取挂买信息
                $buyClinchInfo = self::fetchTradeOrder($buyOrderData['order_id'], $buyOrderData['order_number'], $order_symbol); //获取挂买数据
                if($buyClinchInfo && isset($buyClinchInfo['info'])) {
                    $orderAmount = $buyClinchInfo['info']['origQty']; //订单数量
                    $dealAmount = $buyClinchInfo['info']['executedQty']; //成交数量
                    $side_type = $buyClinchInfo['info']['side']; //订单方向
                    $minOrderAmount = $orderAmount * 0.5; //最小成交数量
                    echo $side_type . "订单数量【" . $orderAmount . "】成交数量【". $dealAmount ."】\r\n";
                    if($dealAmount >= $minOrderAmount) { //如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                        $make_side = 1;
                        $makeArray = $buyOrderData;
                    }
                }  
                
                //然后获取挂卖信息
                $sellClinchInfo = self::fetchTradeOrder($sellOrderData['order_id'], $sellOrderData['order_number'], $order_symbol); //获取挂买数据
                if($sellClinchInfo && isset($sellClinchInfo['info'])) {
                    $orderAmount = $sellClinchInfo['info']['origQty']; //订单数量
                    $dealAmount = $sellClinchInfo['info']['executedQty']; //成交数量
                    $side_type = $sellClinchInfo['info']['side']; //订单方向
                    $minOrderAmount = $orderAmount * 0.5; //最小成交数量
                    echo $side_type . "订单数量【" . $orderAmount . "】成交数量【". $dealAmount ."】\r\n";
                    if($dealAmount >= $minOrderAmount) { //如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                        $make_side = 2;
                        $makeArray = $sellOrderData;
                    }
                }  

                if($make_side > 0) { // 如果有挂单已成交
                    //开始记录订单数据
                    //获取上一次是否成对出现
                    $isPair = false;
                    $profit = 0;
                    $pairId = 0; //配对ID
                    $dealPrice = $makeArray['price']; //成交价格
    
                    if($make_side == 1) { //挂买成交的话
                        $orderAmount = $buyClinchInfo['info']['origQty']; //订单数量
                        $dealAmount = $buyClinchInfo['info']['executedQty']; //成交数量
                        $setBuyClinchRes = Db::name('binance_piggybank_pendord')->where('id', $buyOrderData['id'])->update(['status' => 2, 'clinch_amount' => $dealAmount, 'up_time' => date('Y-m-d H:i:s')]);
                        if($setBuyClinchRes) { //如果修改状态为已成交
                            echo "BUY 已成交，修改挂单状态为已挂单成功 \r\n";
                            $setSellClinchRes = Db::name('binance_piggybank_pendord')->where('id', $sellOrderData['id'])->update(['status' => 3, 'clinch_amount' => 0, 'up_time' => date('Y-m-d H:i:s')]);
                            if($setSellClinchRes !== false) {
                                echo "修改挂卖状态为已撤销挂单 \r\n";
                                //撤销所有订单
                                $revokeOrder = self::fetchCancelOpenOrder($order_symbol); //撤销当前交易对所有挂单
                                if($revokeOrder) { //已撤销全部挂单
                                    echo "该交易对所有挂单挂单已撤销 \r\n";
                                    $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=2 AND pair = 0 ORDER BY `time` DESC,abs('$dealPrice'-`price`) LIMIT 1;";
                                    $res = Db::query($sql);
                                    if($res && count((array)$res) > 0 && $dealPrice < $res[0]['price']) { //计算利润
                                        $pairId = $res[0]['id'];
                                        $isPair = true;
                                        $profit = (float)$res[0]['clinch_number'] * ((float)$res[0]['price'] - $dealPrice); //卖出的成交数量 * 价差
                                    }
                                }
                            }
                        }
                    } else if($make_side == 2) { //挂卖成交的话
                        $orderAmount = $sellClinchInfo['info']['origQty']; //订单数量
                        $dealAmount = $sellClinchInfo['info']['executedQty']; //成交数量
                        $setSellClinchRes = Db::name('binance_piggybank_pendord')->where('id', $sellOrderData['id'])->update(['status' => 2, 'clinch_amount' => $dealAmount, 'up_time' => date('Y-m-d H:i:s')]);
                        if($setSellClinchRes) { //如果修改状态为已成交
                            echo "SELL 已成交，修改挂单状态为已挂单成功 \r\n";
                            $setBuyClinchRes = Db::name('binance_piggybank_pendord')->where('id', $buyOrderData['id'])->update(['status' => 3, 'clinch_amount' => 0, 'up_time' => date('Y-m-d H:i:s')]);
                            if($setBuyClinchRes) {
                                echo "修改挂买状态为已撤销挂单 \r\n";
                                //撤销所有订单
                                $revokeOrder = self::fetchCancelOpenOrder($order_symbol); //撤销当前交易对所有挂单
                                if($revokeOrder) { //已撤销全部挂单
                                    echo "该交易对所有挂单挂单已撤销 \r\n";
                                    $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=1 AND pair = 0 ORDER BY `time` DESC,abs('$dealPrice'-`price`) LIMIT 1;";
                                    $res = Db::query($sql);
                                    if($res && count((array)$res) > 0 && $dealPrice > $res[0]['price']) { //计算利润 卖出要高于买入才能配对
                                        $pairId = $res[0]['id'];
                                        $isPair = true;
                                        $profit = $dealAmount * ($dealPrice - (float)$res[0]['price']); // 卖出的成交数量 * 价差
                                    }
                                }
                            }
                        }
                    } else { //两笔挂单都没有成交
                        //检测余额是否变化，如果变化撤单重新下单
                        $tradeValuationNew = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
                        $bifiBalanceNew = $tradeValuationNew['bifiBalance']; //BIFI余额
                        $busdBalanceNew = $tradeValuationNew['busdBalance']; //BUSD余额
                        if((float)$buyOrderData['currency1'] !== $bifiBalanceNew || (float)$buyOrderData['currency2'] !== $busdBalanceNew) {
                            echo "余额有变化，撤单重新挂单 \r\n";
                            echo "变化前 BIFI余额:" . $buyOrderData['currency1'] . "BUSD余额:" . $buyOrderData['currency2'] . "\r\n";
                            echo "最新 BIFI余额:" . $bifiBalanceNew . "BUSD余额:" . $busdBalanceNew . "\r\n";
                            $orderCancelRes = self::fetchCancelOpenOrder($order_symbol);
                            if($orderCancelRes) {
                                echo "开始重新吃单模式 \r\n";
                                // $isPendingOrder = self::startPendingOrder($transactionCurrency); //重新挂单
                                $isPositionOrder = self::balancePositionOrder(); //开始吃单 平衡
                                if($isPositionOrder) {
                                    echo "已重新吃单 \r\n";
                                    Db::commit();
                                    // self::balancePendingOrder(); //挂完单直接获取是否已成交
                                    return true;
                                }
                            }
                        }
                    }
                    
                    if($make_side == 1 || $make_side == 2) {
                        //获取最小下单数量
                        $rubikStatTakerValume = $exchange->fetch_markets(['symbol'=>$symbol]);
                        // p($rubikStatTakerValume);
                        $base_ccy = isset($rubikStatTakerValume[0]['base']) ? $rubikStatTakerValume[0]['base'] : ''; //交易货币币种
                        $quote_ccy = isset($rubikStatTakerValume[0]['quote']) ? $rubikStatTakerValume[0]['quote'] : ''; //计价货币币种
                        //开始下单 写入下单表
                        $balanceDetails = self::getTradePairBalance($transactionCurrency);
                        $insertOrderData = [
                            'product_name' => $makeArray['product_name'],
                            'order_id' => $makeArray['order_id'],
                            'order_number' => $makeArray['order_number'],
                            'td_mode' => 'cross',
                            'base_ccy' => $base_ccy,
                            'quote_ccy' => $quote_ccy,
                            'type' => $make_side,
                            'order_type' => 'LIMIT',
                            'amount' => $orderAmount,
                            'clinch_number' => $dealAmount,
                            'price' => $dealPrice,
                            'make_deal_price' => $dealPrice,
                            'profit' => $profit,
                            'currency1' => $balanceDetails['bifiBalance'],
                            'currency2' => $balanceDetails['busdBalance'],
                            'balanced_valuation' => $balanceDetails['busdBalance'],
                            'pair' => $pairId,
                            'time' => date('Y-m-d H:i:s'),
                        ];
                        $insertId = Db::name('binance_piggybank')->insertGetId($insertOrderData);
                        if($insertId) {
                            echo "记录订单成交数据成功 \r\n";
                            if (isset($pairId) && $pairId > 0) {
                                $isPair = Db::name('binance_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
                                if ($isPair) {
                                    $isPendingOrder = self::startPendingOrder($transactionCurrency); //重新挂单
                                    if($isPendingOrder) {
                                        echo "已重新挂单 \r\n";
                                        Db::commit();
                                        // self::balancePendingOrder(); //挂完单直接获取是否已成交
                                        return true;
                                    }
                                }
                            } else {
                                $isPendingOrder = self::startPendingOrder($transactionCurrency); //重新挂单
                                if($isPendingOrder) {
                                    echo "已重新挂单 \r\n";
                                    Db::commit();
                                    // self::balancePendingOrder(); //挂完单直接获取是否已成交
                                    return true;
                                }
                            }
                        }
                    }
                } else {
                    echo "挂单进行中 \r\n";
                }


                // foreach ($peningOrderList as $key => $val) {
                //     $isClinchInfo = self::fetchTradeOrder($val['order_id'], $val['order_number'], $order_symbol); //获取订单数据
                //     if($isClinchInfo && isset($isClinchInfo['info'])) {
                //         // p($isClinchInfo['info']);
                //         $orderAmount = $isClinchInfo['info']['origQty']; //订单数量
                //         $dealAmount = $isClinchInfo['info']['executedQty']; //成交数量
                //         $side_type = $isClinchInfo['info']['side']; //订单方向
                //         $minOrderAmount = $orderAmount * 0.5; //最小成交数量
                //         echo $side_type . "订单数量【" . $orderAmount . "】成交数量【". $dealAmount ."】\r\n";
                //         if($dealAmount >= $minOrderAmount) { //如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                //             $setClinchRes = Db::name('binance_piggybank_pendord')->where('id', $val['id'])->update(['status' => 2, 'clinch_amount' => $dealAmount, 'up_time' => date('Y-m-d H:i:s')]);
                //             if($setClinchRes) { //如果修改状态为已成交
                //                 echo $side_type . "已成交，修改挂单状态为已挂单成功 \r\n";
                //                 //撤销另一个订单
                //                 $revokeKey = $key == 0 ? 1 : 0; //撤销订单key值
                //                 // $revokeOrder = self::fetchCancelOrder($peningOrderList[$revokeKey]['order_id'], $peningOrderList[$revokeKey]['order_number'], $order_symbol);
                //                 $revokeOrder = self::fetchCancelOpenOrder($order_symbol); //撤销当前交易对所有挂单
                //                 if($revokeOrder) { //已撤销全部挂单
                //                     $setRevokeRes = Db::name('binance_piggybank_pendord')->where('id', $peningOrderList[$revokeKey]['id'])->update(['status' => 3, 'up_time' => date('Y-m-d H:i:s')]); //修改撤销状态
                //                     if($setRevokeRes) {
                //                         echo "挂单已撤销 \r\n";
                //                         //开始记录订单数据
                //                         //获取上一次是否成对出现
                //                         $isPair = false;
                //                         $profit = 0;
                //                         $dealPrice = $val['price']; //成交价格
                //                         $side = $isClinchInfo['info']['side'] === 'BUY' ? 1 : 2; //订单方向
                //                         // $res = Db::name('okx_piggybank')->order('id desc')->limit(1)->find();
                //                         if($side == 1) { //购买的话
                //                             $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=2 AND pair = 0 ORDER BY `time` DESC,abs('$dealPrice'-`price`) LIMIT 1;";
                //                             $res = Db::query($sql);
                //                             $pairId = 0;
                //                             if($res && count((array)$res) > 0 && $dealPrice < $res[0]['price']) { //计算利润
                //                                 $pairId = $res[0]['id'];
                //                                 $isPair = true;
                //                                 $profit = (float)$res[0]['clinch_number'] * ((float)$res[0]['price'] - $dealPrice); //卖出的成交数量 * 价差
                //                             }
                //                         } else { //出售的话
                //                             $sql = "SELECT id,price,clinch_number FROM s_binance_piggybank WHERE `type`=1 AND pair = 0 ORDER BY `time` DESC,abs('$dealPrice'-`price`) LIMIT 1;";
                //                             $res = Db::query($sql);
                //                             $pairId = 0; //配对ID
                //                             if($res && count((array)$res) > 0 && $dealPrice > $res[0]['price']) { //计算利润 卖出要高于买入才能配对
                //                                 $pairId = $res[0]['id'];
                //                                 $isPair = true;
                //                                 $profit = $dealAmount * ($dealPrice - (float)$res[0]['price']); // 卖出的成交数量 * 价差
                //                             }
                //                         }
        
                //                         //获取最小下单数量
                //                         $rubikStatTakerValume = $exchange->fetch_markets(['symbol'=>$symbol]);
                //                         // p($rubikStatTakerValume);
                //                         $base_ccy = isset($rubikStatTakerValume[0]['base']) ? $rubikStatTakerValume[0]['base'] : ''; //交易货币币种
                //                         $quote_ccy = isset($rubikStatTakerValume[0]['quote']) ? $rubikStatTakerValume[0]['quote'] : ''; //计价货币币种
                //                         //开始下单 写入下单表
                //                         $balanceDetails = self::getTradePairBalance($transactionCurrency);
                //                         $insertOrderData = [
                //                             'product_name' => $val['product_name'],
                //                             'order_id' => $val['order_id'],
                //                             'order_number' => $val['order_number'],
                //                             'td_mode' => 'cross',
                //                             'base_ccy' => $base_ccy,
                //                             'quote_ccy' => $quote_ccy,
                //                             'type' => $side,
                //                             'order_type' => 'LIMIT',
                //                             'amount' => $orderAmount,
                //                             'clinch_number' => $dealAmount,
                //                             'price' => $dealPrice,
                //                             'make_deal_price' => $dealPrice,
                //                             'profit' => $profit,
                //                             'currency1' => $balanceDetails['bifiBalance'],
                //                             'currency2' => $balanceDetails['busdBalance'],
                //                             'balanced_valuation' => $balanceDetails['busdBalance'],
                //                             'pair' => $pairId,
                //                             'time' => date('Y-m-d H:i:s'),
                //                         ];
                //                         $insertId = Db::name('binance_piggybank')->insertGetId($insertOrderData);
                //                         if($insertId) {
                //                             echo "记录订单成交数据成功 \r\n";
                //                             if (isset($pairId) && $pairId > 0) {
                //                                 $isPair = Db::name('binance_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
                //                                 if ($isPair) {
                //                                     $isPendingOrder = self::startPendingOrder($transactionCurrency); //重新挂单
                //                                     if($isPendingOrder) {
                //                                         echo "已重新挂单 \r\n";
                //                                         Db::commit();
                //                                         // self::balancePendingOrder(); //挂完单直接获取是否已成交
                //                                         return true;
                //                                     }
                //                                 }
                //                             } else {
                //                                 $isPendingOrder = self::startPendingOrder($transactionCurrency); //重新挂单
                //                                 if($isPendingOrder) {
                //                                     echo "已重新挂单 \r\n";
                //                                     Db::commit();
                //                                     // self::balancePendingOrder(); //挂完单直接获取是否已成交
                //                                     return true;
                //                                 }
                //                             }
                //                         }
                //                     }
                //                 }
                //             }
                //             break;
                //         } else {
                //             //检测余额是否变化，如果变化撤单重新下单
                //             $tradeValuationNew = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
                //             $bifiBalanceNew = $tradeValuationNew['bifiBalance']; //BIFI余额
                //             $busdBalanceNew = $tradeValuationNew['busdBalance']; //BUSD余额
                //             if((float)$val['currency1'] !== $bifiBalanceNew || (float)$val['currency2'] !== $busdBalanceNew) {
                //                 echo "余额有变化，撤单重新挂单 \r\n";
                //                 echo "变化前 BIFI余额:" . $val['currency1'] . "BUSD余额:" . $val['currency2'] . "\r\n";
                //                 echo "最新 BIFI余额:" . $bifiBalanceNew . "BUSD余额:" . $busdBalanceNew . "\r\n";
                //                 $orderCancelRes = self::fetchCancelOrder($val['order_id'], $val['order_number'], $val['symbol']);
                //                 if($orderCancelRes) {
                //                     $setRevokeRes = Db::name('binance_piggybank_pendord')->where('id', $val['id'])->update(['status' => 3, 'up_time' => date('Y-m-d H:i:s')]); //修改撤销状态
                //                     if($setRevokeRes) {
                //                         $isReOrder = true;
                //                         $reOrderNum ++;
                //                     }
                //                 }
                //                 break;
                //             }
                //         }
                //     }
                // }
                // if($isReOrder) { // 如果币种余额有变化 两个单已经全部撤单成功 重新挂单 
                //     $orderCancelRes = self::fetchCancelOpenOrder($order_symbol); //取消当前订单下所有挂单
                //     if($orderCancelRes) {
                //         echo "开始重新吃单 \r\n";
                //         // $isPendingOrder = self::startPendingOrder($transactionCurrency); //重新挂单
                //         $isPositionOrder = self::balancePositionOrder(); //开始吃单 平衡
                //         if($isPositionOrder) {
                //             echo "已重新吃单 \r\n";
                //             Db::commit();
                //             // self::balancePendingOrder(); //挂完单直接获取是否已成交
                //             return true;
                //         }
                //     }
                // }

            } else { //开始挂单
                $isPendingOrder = self::startPendingOrder($transactionCurrency); //开始挂单
                if($isPendingOrder) {
                    Db::commit();
                    // self::balancePendingOrder(); //挂完单直接获取是否已成交
                    return true;
                }
            }
            Db::rollback();
            return false;
        } catch (\Exception $e) {
            Db::rollback();
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT Error \r\n".$error_msg);
            return false;
        }
    }

    /**
     * 开始挂单
     * @author qinlh
     * @since 2022-11-26
     */
    public static function startPendingOrder($transactionCurrency='') {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        $order_symbol = str_replace("-",'/', $transactionCurrency);
        // $orderDetail = self::fetchTradeOrder('', "Zx22022112657545610", $order_symbol); //查询订单
        // p($orderDetail);
        // $orderDetail = self::fetchCancelOrder('', "Zx22022112657100495", $order_symbol); //撤销订单 status：已取消：CANCELED 未取消：NEW
        // p($orderDetail);
        
        if($transactionCurrency) {
            $symbol = str_replace("-",'', $transactionCurrency);
            $order_symbol = str_replace("-",'/', $transactionCurrency);
            $changeRatioNum = BinanceConfig::getChangeRatio(); //涨跌比例
            // $changeRatioNum = 2; //涨跌比例 2%
            $balanceRatio = '1:1'; //平衡比例
            $balanceRatioArr = explode(':', $balanceRatio);
            $tradeValuation = self::getTradeValuation($transactionCurrency); //获取交易估值及价格
            $getLastRes = self::getLastRes(); //获取上次成交价格
            // $price = (float)$getLastRes['price'];
            $price = (float)$getLastRes['price'];
            $sellPropr = ($changeRatioNum / $changeRatioNum) + ($changeRatioNum / 100); //出售比例
            $buyPropr = ($changeRatioNum / $changeRatioNum) - ($changeRatioNum / 100); //购买比例
            // echo $buyPropr;die;
            $sellingPrice = $price * $sellPropr; //出售价格
            $buyingPrice = $price * $buyPropr; //购买价格
            $bifiBalance = $tradeValuation['bifiBalance']; //BIFI余额
            $busdBalance = $tradeValuation['busdBalance']; //BUSD余额
            $bifiValuation = $tradeValuation['bifiValuation'];
            $tradingPrice = $tradeValuation['tradingPrice']; //现价
            if($sellingPrice < $tradingPrice) { //挂买价格如果大于现价 那就用现价
                $sellingPrice = $tradingPrice;
            }
            if($buyingPrice > $tradingPrice) { //挂卖价格如果小于现价 那就用现价
                $buyingPrice = $tradingPrice;
            }
            $bifiSellValuation = $sellingPrice * $bifiBalance; //BIFI 出售估值
            $bifiBuyValuation = $buyingPrice * $bifiBalance; //BIFI 购买估值
            $busdValuation = $tradeValuation['busdValuation'];
            
            $clientBuyOrderId = 'Zx1'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            $clientSellOrderId = 'Zx2'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            echo "购买订单号：" . $clientBuyOrderId . "\r\n";
            echo "出售订单号：" . $clientSellOrderId . "\r\n";
            //挂单 购买
            // echo "BIFI估值:" . $bifiBuyValuation . "BUSD估值:" . $busdValuation . "\r\n";
            // p($bifiBuyValuation);  
            
            $buyNum = $balanceRatioArr[1] * (($busdValuation - $bifiBuyValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
            $buyOrdersNumber = $buyNum / $buyingPrice; //购买数量
            // p($buyOrdersNumber);

            //挂单 出售
            $sellNum = $balanceRatioArr[0] * (($bifiSellValuation - $busdValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
            $sellOrdersNumber = $sellNum / $sellingPrice;
            // p($sellOrdersNumber);
            
            if((float)$buyOrdersNumber < 0 || (float)$sellOrdersNumber < 0) {
                echo "购买出售出现负数，开始吃单 \r\n";
                $toEatMeal = self::balancePositionOrder(1);
                if($toEatMeal) { //如果吃单成功 重新挂单
                    Db::commit();
                    Db::startTrans();
                    echo "吃单成功 重新挂单 \r\n";
                    $isPendingOrder = self::startPendingOrder($transactionCurrency);
                    if($isPendingOrder) {
                        echo "已重新挂单 \r\n";
                        Db::commit();
                        return true;
                    }
                }
                return true;
            }

            $busdBuyClinchBalance = $busdBalance - $buyNum; //挂买以后BUSD数量 BUSD余额 减去 购买busd数量
            $bifiBuyClinchBalance = $bifiBalance + $buyOrdersNumber; //挂买以后BIFI数量 BIFI余额 加上 购买数量
            $busdSellClinchBalance = $busdBalance + $sellNum; //挂卖以后BUSD数量 BUSD余额 加上 出售busd数量
            $bifiSellClinchBalance = $bifiBalance - $sellOrdersNumber; //挂卖以后BIFI数量 BIFI余额 减去 出售数量

            $buyOrderDetailsArr = [];
            $sellOrderDetailsArr = [];
            if($bifiValuation > $busdValuation) { //BIFI的估值超过BUSD时候，出售 BIFI换成BUSDT
                $sellOrderDetails = $exchange->create_order($order_symbol, 'LIMIT', 'SELL', $sellOrdersNumber, $sellingPrice, ['newClientOrderId' => $clientSellOrderId]);
                if($sellOrderDetails['info']) { //如果挂单出售成功
                    echo "挂单出售成功" . "\r\n";
                    $sellOrderDetailsArr = $sellOrderDetails['info'];
                    $buyOrderDetails = $exchange->create_order($order_symbol, 'LIMIT', 'BUY', $buyOrdersNumber, $buyingPrice, ['newClientOrderId' => $clientBuyOrderId]);
                    if($buyOrderDetails['info']) { //如果挂单购买成功
                        echo "挂单购买成功" . "\r\n";
                        $buyOrderDetailsArr = $buyOrderDetails['info'];
                    }
                }
            }
            if($bifiValuation < $busdValuation) { //BIFI的估值低于BUSD时，买BIFI，换成BUSD
                $buyOrderDetails = $exchange->create_order($order_symbol, 'LIMIT', 'BUY', $buyOrdersNumber, $buyingPrice, ['newClientOrderId' => $clientBuyOrderId]);
                if($buyOrderDetails['info']) { //如果挂单购买成功
                    echo "挂单购买成功" . "\r\n";
                    $buyOrderDetailsArr = $buyOrderDetails['info'];
                    $sellOrderDetails = $exchange->create_order($order_symbol, 'LIMIT', 'SELL', $sellOrdersNumber, $sellingPrice, ['newClientOrderId' => $clientSellOrderId]);
                    if($sellOrderDetails['info']) { //如果挂单出售成功
                        echo "挂单出售成功" . "\r\n";
                        $sellOrderDetailsArr = $sellOrderDetails['info'];
                    }
                }
            }
            if($buyOrderDetailsArr && $sellOrderDetailsArr && count((array)$buyOrderDetailsArr) > 0 && count((array)$sellOrderDetailsArr) > 0) {
                $newBalanceDetailsInfo = self::getTradePairBalance($transactionCurrency); //获取最新余额
                $isSetBuyRes = self::setPiggybankPendordData(
                    $order_symbol, 
                    $transactionCurrency, 
                    $buyOrderDetailsArr['orderId'], 
                    $buyOrderDetailsArr['clientOrderId'], 
                    1, 
                    'LIMIT', 
                    $buyOrderDetailsArr['origQty'], 
                    $buyOrderDetailsArr['price'], 
                    $newBalanceDetailsInfo['bifiBalance'],
                    $newBalanceDetailsInfo['busdBalance'], 
                    $bifiBuyClinchBalance,
                    $busdBuyClinchBalance
                ); //记录挂单购买订单数据
                if($isSetBuyRes) {
                    echo "挂单购买记录数据库成功" . "\r\n";
                    // //挂单 出售
                    $isSetSellRes = self::setPiggybankPendordData(
                        $order_symbol, 
                        $transactionCurrency, 
                        $sellOrderDetailsArr['orderId'], 
                        $sellOrderDetailsArr['clientOrderId'], 
                        2, 
                        'LIMIT', 
                        $sellOrderDetailsArr['origQty'], 
                        $sellOrderDetailsArr['price'], 
                        $newBalanceDetailsInfo['bifiBalance'],
                        $newBalanceDetailsInfo['busdBalance'], 
                        $bifiSellClinchBalance,
                        $busdSellClinchBalance
                    ); //记录挂单出售订单数据
                    if($isSetSellRes) {
                        echo "挂单出售记录数据库成功" . "\r\n";
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * 取消订单
     * @author qinlh
     * @since 2022-11-26
     */
    public static function cancelOrder() {
        $orderDetail = self::fetchTradeOrder('', "Zx22022120655525654", 'BIFI/BUSD'); //查询订单
        p($orderDetail);
        $orderDetail = self::fetchCancelOpenOrder('BIFI/BUSD'); //查询订单
        $orderDetail = self::fetchCancelOrder('', "Zx22022120599575299", 'BIFI/BUSD'); //撤销订单 status：已取消：CANCELED 未取消：NEW
        return;
    }
    
    
    // public static function startRecoverData() {
    //     $data = self::name('binance_piggybank')->where(['id'=>['<', 133]])->select()->toArray();
    //     // p($data);
    //     foreach ($data as $key => $val) {
    //         $orderDetail = self::fetchTradeOrder('', $val['order_number'], 'BIFI/BUSD'); //查询订单
    //         if($orderDetail['price']) {
    //             $price = $orderDetail['price'];
    //             $currency2 = $price * $val['currency1'];
    //             $updateParams = [
    //                 'price' => $price,
    //                 'make_deal_price' => $price,
    //                 'currency2' => $currency2,
    //                 'balanced_valuation' => $currency2
    //             ];
    //             $saveRes = self::name('binance_piggybank')->where('id', $val['id'])->update($updateParams);
    //             if($saveRes !== false) {
    //                 echo "修改id【".$val['id']."】成功 \r\n";
    //             }
    //         }
    //         // exit();
    //         // p($element);
    //     }
    // }
    
    
    /**
     * 记录挂单订单数据
     * @author qinlh
     * @since 2022-11-25
     */
    public static function setPiggybankPendordData(
        $order_symbol='', 
        $product_name='', 
        $order_id='', 
        $order_number='', 
        $type=0, 
        $order_type='', 
        $amount='', 
        $price='', 
        $currency1=0, 
        $currency2=0,
        $bifiClinchBalance=0,
        $busdClinchBalance=0
    ) {
        if($order_id) {
            $insertOrderData = [
                'product_name' => $product_name,
                'symbol' => $order_symbol,
                'order_id' => $order_id,
                'order_number' => $order_number,
                'type' => $type,
                'order_type' => $order_type,
                'amount' => $amount,
                'price' => $price,
                'currency1' => $currency1,
                'currency2' => $currency2,
                'clinch_currency1' => $bifiClinchBalance,
                'clinch_currency2' => $busdClinchBalance,
                'time' => date('Y-m-d H:i:s'),
                'up_time' => date('Y-m-d H:i:s'),
                'status' => 1
            ];
            $insertId = Db::name('binance_piggybank_pendord')->insertGetId($insertOrderData);
            if($insertId) {
                return true;
            }
        }
        return false;
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
            $countProfitRate = $countProfit / $totalAssets * 100; //网格总利润率 = 总利润 / 总市值
            $dayProfit = BinancePiggybank::getUStandardProfit($transactionCurrency, $date); //获取日利润 网格利润
            $dayProfitRate = $dayProfit / $totalAssets * 100; //网格日利润率 = 日利润 / 总市值
            // $averageDayRate = Db::name('binance_piggybank_date')->where('state', 1)->whereNotIn('date', $date)->avg('grid_day_spread_rate'); //获取平均日利润率
            $averageDayRate = Db::name('binance_piggybank_date')->where('state', 1)->avg('grid_day_spread_rate'); //获取平均日利润率
            $averageYearRate = $averageDayRate * 365; //平均年利率 = 平均日利率 * 365
            $data = Db::name('binance_piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->find();
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
                $res = Db::name('binance_piggybank_date')->where(['product_name' => $transactionCurrency, 'date' => $date])->update($upData);
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
                $res = Db::name('binance_piggybank_date')->insertGetId($insertData);
            }
            if($res !== false) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            // p($e);
            logger("每日存钱罐数据统计 Error \r\n".$e);
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
            if(isset($balanceDetails['info']['balances'])) {
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
            }
            $returnArray = ['busdBalance' => $busdBalance, 'bifiBalance' => $bifiBalance];
            $dataJson = json_encode($returnArray);
            self::setCache($cache_params, $dataJson);
            return $returnArray;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT 获取交易对余额 Error \r\n".$error_msg);
            return false;
        }
    }

    /**
     * 获取订单数据
     * @author qinlh
     * info['status']：已取消：CANCELED  未取消：NEW
     * @since 2022-08-19
     */
    public static function fetchTradeOrder($order_id='', $order_number='', $order_symbol='') {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $tradeOrder = $exchange->fetch_order($order_id, $order_symbol, ['origClientOrderId' => $order_number]);
            return $tradeOrder;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT 获取订单数据 Error \r\n".$error_msg);
            return false;
        }
    }
    
    /**
     * 撤销订单数据
     * @author qinlh
     * @since 2022-11-25
     */
    public static function fetchCancelOrder($order_id='', $order_number='', $order_symbol='') {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $tradeOrder = $exchange->cancel_order($order_id, $order_symbol, ['origClientOrderId' => $order_number]);
            if($tradeOrder && isset($tradeOrder['info']) && $tradeOrder['info']['status'] === 'CANCELED') {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT 撤销订单数据 Error \r\n".$error_msg);
            return true;        
        }
    }

    /**
     * 撤销单一交易对全部挂单
     * @author qinlh
     * @since 2022-11-25
     */
    public static function fetchCancelOpenOrder($order_symbol='', $isEcho=true) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $tradeOrder = $exchange->cancel_open_order($order_symbol);
            if($tradeOrder && isset($tradeOrder['info'])) {
                if($isEcho) {
                    echo "修改挂单表 撤销全部挂单商品\r\n";
                }
                self::setRevokePendingOrder();
                // $peningOrderList = self::getOpenPeningOrder();
                // if($peningOrderList && count((array)$peningOrderList) > 0) {
                //     foreach ($peningOrderList as $key => $val) {
                //         @Db::name('binance_piggybank_pendord')->where('id', $val['id'])->update(['status' => 3, 'up_time' => date('Y-m-d H:i:s')]); //修改撤销状态
                //     }
                // }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            if($e->getCode() == '-2011') {
                if($isEcho) {
                    echo "修改挂单表 撤销全部挂单商品\r\n";
                }
                self::setRevokePendingOrder();
            }
            logger("BIFI-USDT 撤销单一交易对全部挂单 Error \r\n".$error_msg);
            // echo $error_msg . "\r\n";
            return true;
        }
    }

    /**
     * 撤销全部挂单商品
     * @author qinlh
     * @since 2022-12-05
     */
    public static function setRevokePendingOrder() {
        $res = self::name('binance_piggybank_pendord')->where('status', 1)->find();
        if($res && count((array)$res) > 0) {
            @Db::name('binance_piggybank_pendord')->where('status', 1)->update(['status' => 3, 'up_time' => date('Y-m-d H:i:s')]); //修改撤销状态
        }
        return true;
    }

    /**
     * 查询单一交易对全部挂单
     * @author qinlh
     * @since 2022-11-25
     */
    public static function fetchGetOpenOrder($order_symbol='') {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\binance";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
        ));
        try {
            $orderList = $exchange->fetch_open_orders($order_symbol);
            if($orderList) {
                return $orderList;
            }
            return false;
        } catch (\Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT 查询单一交易对全部挂单 Error \r\n".$error_msg);
            return false;
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
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            echo $error_msg . "\r\n";
            logger("BIFI-USDT 获取交易对价格 Error \r\n".$error_msg);
            return false;
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
     * 获取最近一次成交数据
     * @author qinlh
     * @since 2022-08-19
     */
    public  static function getLastRes() {
        $data = Db::name('binance_piggybank')->order('id desc, time desc')->find();
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
        $data = Db::name('binance_piggybank_pendord')->where('status', 1)->order('id desc, time desc')->limit(2)->select();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

    /**
     * 获取当前挂单数据 分开获取
     * @author qinlh
     * @since 2022-11-25
     */
    public static function getOpenPeningOrders() {
        $data = Db::name('binance_piggybank_pendord')->where('status', 1)->order('id desc, time desc')->limit(2)->select();
        if($data && count((array)$data) > 0) {
            $buyArray = [];
            $sellArray = [];
            foreach ($data as $key => $val) {
                if($val['type'] == 1) {
                    $buyArray = $val;
                }
                if($val['type'] == 2) {
                    $sellArray = $val;
                }
            }
            return ['buy' => $buyArray, 'sell' => $sellArray];
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

        $changeRatioNum = BinanceConfig::getChangeRatio(); //涨跌比例
        $balanceRatio = '1:1'; //平衡比例
        $balanceRatioArr = explode(':', $balanceRatio);
        $sellPropr = ($changeRatioNum / $changeRatioNum) + ($changeRatioNum / 100); //出售比例
        $buyPropr = ($changeRatioNum / $changeRatioNum) - ($changeRatioNum / 100); //购买比例

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
        $changeRatio = $balancedValuation > 0 ? abs($bifiValuation - $busdValuation) / $balancedValuation * 100 : abs($bifiValuation - $busdValuation) / $busdValuation * 100;

        $bifiSellNum = $balanceRatioArr[0] * (($bifiValuation - $busdValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        $bifiSellOrdersNumber = $bifiSellNum / $tradingPrice;

        $busdBuyNum = $balanceRatioArr[1] * (($busdValuation - $bifiValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
        $busdBuyOrdersNumber = $busdBuyNum / $tradingPrice;

        $result['minSizeOrderNum'] = $minSizeOrderNum;
        $result['base_ccy'] = $base_ccy;
        $result['quote_ccy'] = $quote_ccy;
        $result['tradingPrice'] = $tradingPrice;
        $result['busdBalance'] = $busdBalance;
        $result['busdValuation'] = $busdValuation;
        $result['bifiBalance'] = $bifiBalance;
        $result['bifiValuation'] = $bifiValuation;
        $result['defaultRatio'] = $changeRatioNum;
        $result['changeRatio'] = $changeRatio;
        $result['sellOrdersNumberStr'] = '';
        $getLastRes = self::getLastRes();
        $result['lastTimePrice'] = $getLastRes['price'];
        $result['sellingPrice'] = (float)$getLastRes['price'] * $sellPropr;
        $result['buyingPrice'] = (float)$getLastRes['price'] * $buyPropr;
        if($bifiValuation > $busdValuation) { //BIFI的估值超过BUSD时候，卖BIFI换成BUSDT
            $result['sellOrdersNumberStr'] = 'BIFI出售数量: ' . $bifiSellOrdersNumber ;
        }
        if($bifiValuation < $busdValuation) { //BIFI的估值低于BUSD时，买BIFI，换成BUSD
            $result['sellOrdersNumberStr'] = 'BUSD购买数量: ' . $busdBuyOrdersNumber ;
        }
        $peningOrderList = self::getOpenPeningOrder();
        $result['pendingOrder'] = [];
        foreach ($peningOrderList as $key => $val) {
            if($val['type'] == 1) {
                $result['pendingOrder']['buy']['price'] = $val['price'];
                $result['pendingOrder']['buy']['amount'] = $val['amount'];
                // $result['pendingOrder']['buy']['bifiValuation'] = ($bifiBalance + (float)$val['amount']) * $val['price'];
                $result['pendingOrder']['buy']['bifiValuation'] = $val['clinch_currency1'] * $val['price'];
                // $result['pendingOrder']['buy']['busdValuation'] = $busdValuation - ((float)$val['amount'] * $val['price']);
                $result['pendingOrder']['buy']['busdValuation'] = $val['clinch_currency2'];
            } else {
                $result['pendingOrder']['sell']['price'] = $val['price'];
                $result['pendingOrder']['sell']['amount'] = $val['amount'];
                // $result['pendingOrder']['sell']['bifiValuation'] = ($bifiBalance - (float)$val['amount']) * $val['price'];
                $result['pendingOrder']['sell']['bifiValuation'] = $val['clinch_currency1'] * $val['price'];
                // $result['pendingOrder']['sell']['busdValuation'] = $busdValuation + ((float)$val['amount'] * $val['price']);
                $result['pendingOrder']['sell']['busdValuation'] = $val['clinch_currency2'];
            }
        }
        return $result;
        // echo "最小下单量: " . $minSizeOrderNum . "<br>";
        // echo "交易货币币种: " . $base_ccy . "<br>";
        // echo "计价货币币种: " . $quote_ccy . "<br>";
        // echo "BIFI价格: " . $tradingPrice . "<br>";
        // echo "BUSD余额: " . $busdBalance . "<br>BUSD估值: " . $busdValuation . "<br>";
        // echo "BIFI余额: " . $bifiBalance . "<br>BIFI估值: " . $bifiValuation . "<br>";
        // echo "涨跌比例: " . $changeRatio . "<br>";
        // if($bifiValuation > $busdValuation) { //BIFI的估值超过BUSD时候，卖BIFI换成BUSDT
        //     echo "BIFI出售数量: " . $bifiSellOrdersNumber . "<br>";
        // }
        // if($bifiValuation < $busdValuation) { //BIFI的估值低于BUSD时，买BIFI，换成BUSD
        //     echo "BUSD购买数量: " . $busdBuyOrdersNumber . "<br>";
        // }
    }
    
}