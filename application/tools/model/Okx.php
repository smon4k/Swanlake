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

    public static $apiKey = '68ca4b58-f022-42e1-8055-8fd565bc4eff';
    public static $secret = 'BD573AF9E7B806503C9AB25B255BD62D';
    public static $password = 'gmxGrid';

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
            $balancedValuation = self::getLastBalancedValuation(); // 获取上一次平衡状态下估值
            $changeRatio = $balancedValuation > 0 ? abs($btcValuation - $usdtValuation) / $balancedValuation * 100 : abs($btcValuation / $usdtValuation);
            $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            if((float)$changeRatio > $changeRatioNum) { //涨跌大于1%
                // p($usdtValuation);
                echo "涨跌幅度大于".$changeRatioNum."% 开始下单\r\n";
                //获取最小下单数量
                $rubikStatTakerValume = $exchange->fetch_markets_by_type('SPOT', ['instId'=>$transactionCurrency]);
                $minSizeOrderNum = isset($rubikStatTakerValume[0]['info']['minSz']) ? $rubikStatTakerValume[0]['info']['minSz'] : 0; //最小下单数量
                $base_ccy = isset($rubikStatTakerValume[0]['info']['baseCcy']) ? $rubikStatTakerValume[0]['info']['baseCcy'] : ''; //交易货币币种
                $quote_ccy = isset($rubikStatTakerValume[0]['info']['quoteCcy']) ? $rubikStatTakerValume[0]['info']['quoteCcy'] : ''; //计价货币币种

                // echo "价格：" . $btcPrice . " -- GMX余额：" . $btcBalance . " -- GMX估值：" . $btcValuation . " -- USDT余额：" . $usdtValuation . "\r\n";
                if($btcValuation > $usdtValuation) { //btc的估值超过usdt时候，卖btc换成u
                    // $btcSellNum = ($btcValuation - $usdtValuation) / 2;
                    $btcSellNum = $balanceRatioArr[0] * (($btcValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
                    $btcSellOrdersNumber = $btcSellNum / $btcPrice;
                    if((float)$btcSellOrdersNumber > (float)$minSizeOrderNum) {
                        echo "下单出售 大于 最小下单量".$minSizeOrderNum." \r\n";
                        $result = $exchange->create_trade_order($transactionCurrency, $clientOrderId, 'market', 'sell', $btcSellOrdersNumber, null, []);
                        if($result['sCode'] == 0) {
                            echo "下单出售成功 \r\n";
                            $order_id = $result['ordId']; //返回的订单id
                            $orderDetails = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, null); //获取成交数量
                            $theDealPrice = $btcPrice; //成交均价
                            $clinch_number = 0; //累计成交数量
                            $dealPrice = 0; //最新成交价格
                            if($orderDetails && $orderDetails['accFillSz']) {
                                $clinch_number = $orderDetails['accFillSz']; //最新成交数量
                            }
                            if($orderDetails && $orderDetails['avgPx']) {
                                $theDealPrice = $orderDetails['avgPx']; //成交均价
                            }
                            if($orderDetails && $orderDetails['fillPx']) {
                                $dealPrice = $orderDetails['fillPx']; //最新成交价格
                            } else {
                                $dealPrice = $theDealPrice;
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
                                'order_id' => $order_id,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 2,
                                'order_type' => 'market',
                                'amount' => $btcSellOrdersNumber,
                                'clinch_number' => $clinch_number,
                                'price' => $dealPrice,
                                'profit' => $profit,
                                'pair' => $pairId,
                                'currency1' => $btcBalance,
                                'currency2' => $usdtBalance,
                                'balanced_valuation' => $usdtValuationPoise,
                                'make_deal_price' => $theDealPrice,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                            if ($insertId) {
                                echo "写入出售下单数据成功 \r\n";
                                if(isset($pairId) && $pairId > 0) {
                                    echo "出售下单配对成功 \r\n";
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
                        echo "下单出售 小于 最小下单量".$minSizeOrderNum." 停止下单 \r\n";
                        return false;
                    }
                }
                if($btcValuation < $usdtValuation) { //btc的估值低于usdt时，买btc，u换成btc
                    echo "GMX的估值小于BUSD 开始下单购买 \r\n";
                    // $usdtBuyNum = ($usdtValuation - $btcValuation) / 2;
                    $usdtBuyNum = $balanceRatioArr[1] * (($usdtValuation - $btcValuation) / ($balanceRatioArr[0] + $balanceRatioArr[1]));
                    $usdtSellOrdersNumber = $usdtBuyNum;
                    if($usdtSellOrdersNumber > $minSizeOrderNum) {
                        echo "下单购买 大于 最小下单量".$minSizeOrderNum." \r\n";
                        $result = $exchange->create_trade_order($transactionCurrency, $clientOrderId, 'market', 'buy', $usdtSellOrdersNumber, null, []);
                        if($result['sCode'] == 0) {
                            echo "下单购买成功 \r\n";
                            //获取上一次是否成对出现
                            $order_id = $result['ordId']; //返回的订单id
                            $orderDetails = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, null); //获取成交数量
                            $theDealPrice = $btcPrice; //成交均价
                            $clinch_number = 0; //累计成交数量
                            $dealPrice = 0; //最新成交价格
                            if($orderDetails && $orderDetails['accFillSz']) {
                                $clinch_number = $orderDetails['accFillSz']; //最新成交数量
                            }
                            if($orderDetails && $orderDetails['avgPx']) {
                                $theDealPrice = $orderDetails['avgPx']; //成交均价
                            }
                            if($orderDetails && $orderDetails['fillPx']) {
                                $dealPrice = $orderDetails['fillPx']; //最新成交价格
                            } else {
                                $dealPrice = $theDealPrice;
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
                                'order_id' => $order_id,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 1,
                                'order_type' => 'market',
                                'amount' => $usdtSellOrdersNumber,
                                'clinch_number' => $clinch_number,
                                'price' => $dealPrice,
                                'profit' => $profit,
                                'currency1' => $btcBalance,
                                'currency2' => $usdtBalance,
                                'balanced_valuation' => $usdtValuationPoise,
                                'make_deal_price' => $theDealPrice,
                                'pair' => $pairId,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            Db::startTrans();
                            $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                            if($insertId) {
                                echo "写入购买下单数据成功 \r\n";
                                if (isset($pairId) && $pairId > 0) {
                                    $isPair = Db::name('okx_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
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
                        return false;
                    }
                }
            } else {
                echo "涨跌幅度小于".$changeRatioNum."% 停止下单\r\n";
                return false;
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
            return false;
        }
    }

    /**
     * 平衡仓位 - 挂单
     * @author qinlh
     * @since 2023-01-12
     */
    public static function balancePendingOrder() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = self::gettTradingPairName('Okx'); //交易币种
        $symbol = str_replace("-",'', $transactionCurrency);
        $order_symbol = $transactionCurrency;
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
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
                $usdtValuation = $tradeValuation['usdtValuation'];
                $btcValuation = $tradeValuation['btcValuation'];
                $minMaxRes = bccomp($usdtValuation, $btcValuation);
                if($minMaxRes == 1) { //busd大
                    $perDiffRes = ($usdtValuation - $btcValuation) / $btcValuation * 100;
                } else {
                    $perDiffRes = ($btcValuation - $usdtValuation) / $usdtValuation * 100;
                }
                if($perDiffRes > 2) { //如果两个币种估值差大于2%的话 撤单->吃单->重新挂单
                    echo "两个币种估值差大于2% 开始全部撤单 \r\n";
                    $orderCancelRes = self::fetchCancelOpenOrder($order_symbol);
                    if($orderCancelRes) { //撤单成功 开始吃单
                        echo "撤单成功 开始吃单 \r\n";
                        Db::commit();
                        $toEatMeal = self::balancePositionOrder();
                        if($toEatMeal) { //如果吃单成功 重新挂单
                            Db::startTrans();
                            echo "吃单成功 重新挂单 \r\n";
                            $isPendingOrder = self::startPendingOrder($transactionCurrency);
                            if($isPendingOrder) {
                                echo "已重新挂单 \r\n";
                                Db::commit();
                                return true;
                            }
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
                if($buyClinchInfo) {
                    $orderAmount = $buyClinchInfo['sz']; //订单数量
                    $dealAmount = $buyClinchInfo['accFillSz']; //成交数量
                    $side_type = $buyClinchInfo['side']; //订单方向
                    $minOrderAmount = $orderAmount * 0.5; //最小成交数量
                    echo $side_type . "订单数量【" . $orderAmount . "】成交数量【". $dealAmount ."】\r\n";
                    if($dealAmount >= $minOrderAmount) { //如果已成交数量大于等于订单数量的50% 设置为已下单 撤销另一个订单
                        $make_side = 1;
                        $makeArray = $buyOrderData;
                    }
                }  
                
                //然后获取挂卖信息
                $sellClinchInfo = self::fetchTradeOrder($sellOrderData['order_id'], $sellOrderData['order_number'], $order_symbol); //获取挂买数据
                if($sellClinchInfo) {
                    $orderAmount = $sellClinchInfo['sz']; //订单数量
                    $dealAmount = $sellClinchInfo['accFillSz']; //成交数量
                    $side_type = $sellClinchInfo['side']; //订单方向
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
                        $orderAmount = $buyClinchInfo['sz']; //订单数量
                        $dealAmount = $buyClinchInfo['accFillSz']; //成交数量
                        $setBuyClinchRes = Db::name('okx_piggybank_pendord')->where('id', $buyOrderData['id'])->update(['status' => 2, 'clinch_amount' => $dealAmount, 'up_time' => date('Y-m-d H:i:s')]);
                        if($setBuyClinchRes) { //如果修改状态为已成交
                            echo "BUY 已成交，修改挂单状态为已挂单成功 \r\n";
                            $setSellClinchRes = Db::name('okx_piggybank_pendord')->where('id', $sellOrderData['id'])->update(['status' => 3, 'clinch_amount' => 0, 'up_time' => date('Y-m-d H:i:s')]);
                            if($setSellClinchRes !== false) {
                                echo "修改挂卖状态为已撤销挂单 \r\n";
                                //撤销所有订单
                                $revokeOrder = self::fetchCancelOpenOrder($order_symbol); //撤销当前交易对所有挂单
                                if($revokeOrder) { //已撤销全部挂单
                                    echo "该交易对所有挂单挂单已撤销 \r\n";
                                    $sql = "SELECT id,price,clinch_number FROM s_okx_piggybank WHERE `type`=2 AND pair = 0 ORDER BY `time` DESC,abs('$dealPrice'-`price`) LIMIT 1;";
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
                        $orderAmount = $sellClinchInfo['sz']; //订单数量
                        $dealAmount = $sellClinchInfo['accFillSz']; //成交数量
                        $setSellClinchRes = Db::name('okx_piggybank_pendord')->where('id', $sellOrderData['id'])->update(['status' => 2, 'clinch_amount' => $dealAmount, 'up_time' => date('Y-m-d H:i:s')]);
                        if($setSellClinchRes) { //如果修改状态为已成交
                            echo "SELL 已成交，修改挂单状态为已挂单成功 \r\n";
                            $setBuyClinchRes = Db::name('okx_piggybank_pendord')->where('id', $buyOrderData['id'])->update(['status' => 3, 'clinch_amount' => 0, 'up_time' => date('Y-m-d H:i:s')]);
                            if($setBuyClinchRes) {
                                echo "修改挂买状态为已撤销挂单 \r\n";
                                //撤销所有订单
                                $revokeOrder = self::fetchCancelOpenOrder($order_symbol); //撤销当前交易对所有挂单
                                if($revokeOrder) { //已撤销全部挂单
                                    echo "该交易对所有挂单挂单已撤销 \r\n";
                                    $sql = "SELECT id,price,clinch_number FROM s_okx_piggybank WHERE `type`=1 AND pair = 0 ORDER BY `time` DESC,abs('$dealPrice'-`price`) LIMIT 1;";
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
                        $btcBalanceNew = $tradeValuationNew['btcBalance']; //GMX余额
                        $busdBalanceNew = $tradeValuationNew['usdtBalance']; //BUSD余额
                        if((float)$buyOrderData['currency1'] !== $btcBalanceNew || (float)$buyOrderData['currency2'] !== $busdBalanceNew) {
                            echo "余额有变化，撤单重新挂单 \r\n";
                            echo "变化前 GMX余额:" . $buyOrderData['currency1'] . "BUSD余额:" . $buyOrderData['currency2'] . "\r\n";
                            echo "最新 GMX余额:" . $btcBalanceNew . "BUSD余额:" . $busdBalanceNew . "\r\n";
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
                        // $rubikStatTakerValume = $exchange->fetch_markets(['symbol'=>$symbol]);
                        $rubikStatTakerValume = $exchange->fetch_markets_by_type('SPOT', ['instId'=>$transactionCurrency]);
                        // p($rubikStatTakerValume);
                        $base_ccy = isset($rubikStatTakerValume[0]['info']['baseCcy']) ? $rubikStatTakerValume[0]['info']['baseCcy'] : ''; //交易货币币种
                        $quote_ccy = isset($rubikStatTakerValume[0]['info']['quoteCcy']) ? $rubikStatTakerValume[0]['info']['quoteCcy'] : ''; //计价货币币种
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
                            'currency1' => $balanceDetails['btcBalance'],
                            'currency2' => $balanceDetails['usdtBalance'],
                            'balanced_valuation' => $balanceDetails['usdtBalance'],
                            'pair' => $pairId,
                            'time' => date('Y-m-d H:i:s'),
                        ];
                        $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                        if($insertId) {
                            echo "记录订单成交数据成功 \r\n";
                            if (isset($pairId) && $pairId > 0) {
                                $isPair = Db::name('okx_piggybank')->where('id', $pairId)->update(['pair' => $pairId, 'profit' => $profit]);
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
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        // $order_symbol = str_replace("-",'/', $transactionCurrency);
        $order_symbol = $transactionCurrency;
        // $orderDetail = self::fetchTradeOrder('', "Zx22022112657545610", $order_symbol); //查询订单
        // p($orderDetail);
        // $orderDetail = self::fetchCancelOrder('', "Zx22022112657100495", $order_symbol); //撤销订单 status：已取消：CANCELED 未取消：NEW
        // p($orderDetail);
        
        if($transactionCurrency) {
            $symbol = str_replace("-",'', $transactionCurrency);
            // $order_symbol = str_replace("-",'/', $transactionCurrency);
            // $changeRatioNum = BinanceConfig::getChangeRatio(); //涨跌比例
            $changeRatioNum = 1; //涨跌比例 2%
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
            $btcBalance = $tradeValuation['btcBalance']; //GMX余额
            $usdtBalance = $tradeValuation['usdtBalance']; //BUSD余额
            $btcValuation = $tradeValuation['btcValuation'];
            $bifiSellValuation = $sellingPrice * $btcBalance; //GMX 出售估值
            $bifiBuyValuation = $buyingPrice * $btcBalance; //GMX 购买估值
            $usdtValuation = $tradeValuation['usdtValuation'];
            
            $clientBuyOrderId = 'Zx1'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            $clientSellOrderId = 'Zx2'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            echo "购买订单号：" . $clientBuyOrderId . "\r\n";
            echo "出售订单号：" . $clientSellOrderId . "\r\n";
            //挂单 购买
            // echo "GMX估值:" . $bifiBuyValuation . "BUSD估值:" . $usdtValuation . "\r\n";
            // p($bifiBuyValuation);  
            
            $buyNum = $balanceRatioArr[1] * (($usdtValuation - $bifiBuyValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
            $buyOrdersNumber = $buyNum / $buyingPrice; //购买数量
            // p($buyOrdersNumber);

            //挂单 出售
            $sellNum = $balanceRatioArr[0] * (($bifiSellValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
            $sellOrdersNumber = $sellNum / $sellingPrice;
            // p($sellOrdersNumber);
            
            if((float)$buyOrdersNumber < 0 || (float)$sellOrdersNumber < 0) {
                echo "购买出售出现负数，开始吃单 \r\n";
                $res = self::balancePositionOrder();
                if($res) {
                    Db::commit();
                    return true;
                }
            }

            $busdBuyClinchBalance = $usdtBalance - $buyNum; //挂买以后BUSD数量 BUSD余额 减去 购买busd数量
            $bifiBuyClinchBalance = $btcBalance + $buyOrdersNumber; //挂买以后GMX数量 GMX余额 加上 购买数量
            $busdSellClinchBalance = $usdtBalance + $sellNum; //挂卖以后BUSD数量 BUSD余额 加上 出售busd数量
            $bifiSellClinchBalance = $btcBalance - $sellOrdersNumber; //挂卖以后GMX数量 GMX余额 减去 出售数量

            $buyOrderDetailsArr = [];
            $sellOrderDetailsArr = [];
            if($btcValuation > $usdtValuation) { //GMX的估值超过BUSD时候，出售 GMX换成BUSDT
                $sellOrderDetails = $exchange->create_trade_order($order_symbol, $clientSellOrderId, 'limit', 'sell', $sellOrdersNumber, $sellingPrice, []);
                if($sellOrderDetails && count((array)$sellOrderDetails) > 0) { //如果挂单出售成功
                    echo "挂单出售成功" . "\r\n";
                    $sellOrderDetailsArr = $sellOrderDetails;
                    $sellOrderDetailsArr['amount'] = $sellOrdersNumber;
                    $sellOrderDetailsArr['price'] = $sellingPrice;
                    $buyOrderDetails = $exchange->create_trade_order($order_symbol, $clientBuyOrderId, 'limit', 'buy', $buyOrdersNumber, $buyingPrice, []);
                    if($buyOrderDetails && count((array)$buyOrderDetails) > 0) { //如果挂单购买成功
                        echo "挂单购买成功" . "\r\n";
                        $buyOrderDetailsArr = $buyOrderDetails;
                        $buyOrderDetailsArr['amount'] = $buyOrdersNumber;
                        $buyOrderDetailsArr['price'] = $buyingPrice;
                    }
                }
            }
            if($btcValuation < $usdtValuation) { //GMX的估值低于BUSD时，买GMX，换成BUSD
                $buyOrderDetails = $exchange->create_trade_order($order_symbol, $clientBuyOrderId, 'limit', 'buy', $buyOrdersNumber, $buyingPrice, []);
                if($buyOrderDetails && count((array)$buyOrderDetails) > 0) { //如果挂单购买成功
                    echo "挂单购买成功" . "\r\n";
                    $buyOrderDetailsArr = $buyOrderDetails;
                    $buyOrderDetailsArr['amount'] = $buyOrdersNumber;
                    $buyOrderDetailsArr['price'] = $buyingPrice;
                    $sellOrderDetails = $exchange->create_trade_order($order_symbol, $clientSellOrderId, 'limit', 'sell', $sellOrdersNumber, $sellingPrice, []);
                    if($sellOrderDetails && count((array)$sellOrderDetails) > 0) { //如果挂单出售成功
                        echo "挂单出售成功" . "\r\n";
                        $sellOrderDetailsArr = $sellOrderDetails;
                        $sellOrderDetailsArr['amount'] = $sellOrdersNumber;
                        $sellOrderDetailsArr['price'] = $sellingPrice;
                    }
                }
            }
            if($buyOrderDetailsArr && $sellOrderDetailsArr && count((array)$buyOrderDetailsArr) > 0 && count((array)$sellOrderDetailsArr) > 0) {
                $newBalanceDetailsInfo = self::getTradePairBalance($transactionCurrency); //获取最新余额
                $isSetBuyRes = self::setPiggybankPendordData(
                    $order_symbol, 
                    $transactionCurrency, 
                    $buyOrderDetailsArr['ordId'], 
                    $buyOrderDetailsArr['clOrdId'], 
                    1, 
                    'limit', 
                    $buyOrderDetailsArr['amount'], 
                    $buyOrderDetailsArr['price'], 
                    $newBalanceDetailsInfo['btcBalance'],
                    $newBalanceDetailsInfo['usdtBalance'], 
                    $bifiBuyClinchBalance,
                    $busdBuyClinchBalance
                ); //记录挂单购买订单数据
                if($isSetBuyRes) {
                    echo "挂单购买记录数据库成功" . "\r\n";
                    // //挂单 出售
                    $isSetSellRes = self::setPiggybankPendordData(
                        $order_symbol, 
                        $transactionCurrency, 
                        $sellOrderDetailsArr['ordId'], 
                        $sellOrderDetailsArr['clOrdId'], 
                        2, 
                        'limit', 
                        $sellOrderDetailsArr['amount'], 
                        $sellOrderDetailsArr['price'], 
                        $newBalanceDetailsInfo['btcBalance'],
                        $newBalanceDetailsInfo['usdtBalance'], 
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
        $currency1ClinchBalance=0,
        $currency2ClinchBalance=0
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
                'clinch_currency1' => $currency1ClinchBalance,
                'clinch_currency2' => $currency2ClinchBalance,
                'time' => date('Y-m-d H:i:s'),
                'up_time' => date('Y-m-d H:i:s'),
                'status' => 1
            ];
            $insertId = Db::name('okx_piggybank_pendord')->insertGetId($insertOrderData);
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
            $marketIndexTickers = self::fetchMarketIndexTickers($transactionCurrency); //获取交易BTC价格
            // $marketIndexTickers = $exchange->fetch_market_index_tickers($transactionCurrency); //获取交易BTC价格
            // p($marketIndexTickers);
            if($marketIndexTickers && isset($marketIndexTickers['last']) && $marketIndexTickers['last'] > 0) {
                $btcValuation = $btcBalance * (float)$marketIndexTickers['last'];
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
     * 撤销单一交易对全部挂单
     * @author qinlh
     * @since 2023-01-12
     */
    public static function fetchCancelOpenOrder($order_symbol='', $isEcho=true) {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            $undoneOrders = $exchange->fetch_open_orders($order_symbol, null, 100, ['ordType' => 'limit']); // 获取未成交订单列表
            // p($undoneOrders);
            $cancelNum = 0;
            if($undoneOrders && count((array)$undoneOrders) > 0) {
                foreach ($undoneOrders as $key => $val) {
                    $undoneOrderId = $val['info']['ordId'];
                    $tradeOrder = $exchange->cancel_order($undoneOrderId, $order_symbol);
                    if($tradeOrder && $tradeOrder['info'] && $tradeOrder['info']['sCode'] == 0) {
                        $cancelNum ++;
                    }
                }
            }
            if($cancelNum == count((array)$undoneOrders)) {
                if($isEcho) {
                    echo "修改挂单表 撤销全部挂单商品\r\n";
                }
                self::setRevokePendingOrder();
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
            echo $error_msg . "\r\n";
            return true;
        }
    }

    /**
     * 撤销全部挂单商品
     * @author qinlh
     * @since 2022-12-05
     */
    public static function setRevokePendingOrder() {
        $res = self::name('okx_piggybank_pendord')->where('status', 1)->find();
        if($res && count((array)$res) > 0) {
            @Db::name('okx_piggybank_pendord')->where('status', 1)->update(['status' => 3, 'up_time' => date('Y-m-d H:i:s')]); //修改撤销状态
        }
        return true;
    }

    /**
     * 获取订单数据
     * @author qinlh
     * @since 2022-08-19
     */
    public static function fetchTradeOrder($order_id='', $clientOrderId='', $transactionCurrency="") {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $className = "\ccxt\\okex5";
        $exchange  = new $className(array( //子账户
            'apiKey' => self::$apiKey,
            'secret' => self::$secret,
            'password' => self::$password,
        ));
        try {
            if($transactionCurrency !== '') {
                $tradeOrder = $exchange->fetch_trade_order($transactionCurrency, $clientOrderId, $order_id);
                return $tradeOrder;
            }
            return false;
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
        $usdtBalance = isset($balanceDetails['usdtBalance']) ? $balanceDetails['usdtBalance'] : 0;
        $btcBalance = isset($balanceDetails['btcBalance']) ? $balanceDetails['btcBalance'] : 0;
        $marketIndexTickers = self::fetchMarketIndexTickers($transactionCurrency); //获取交易BTC价格
        $btcPrice = 1;
        $btcValuation = 0;
        $usdtValuation = 0;
        if($marketIndexTickers && isset($marketIndexTickers['last']) && $marketIndexTickers['last'] > 0) {
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
     * 获取当前挂单数据
     * @author qinlh
     * @since 2022-11-25
     */
    public static function getOpenPeningOrder() {
        $data = Db::name('okx_piggybank_pendord')->where('status', 1)->order('id desc, time desc')->limit(2)->select();
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
        $data = Db::name('okx_piggybank_pendord')->where('status', 1)->order('id desc, time desc')->limit(2)->select();
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
        $sellPropr = ($changeRatioNum / $changeRatioNum) + ($changeRatioNum / 100); //出售比例
        $buyPropr = ($changeRatioNum / $changeRatioNum) - ($changeRatioNum / 100); //购买比例

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


        // $sellingPrice = $lastPrice * $sellPropr; //出售价格
        // $btcSellValuation = $sellingPrice * $btcBalance; //BTC 出售估值

        // $buyingPrice = $lastPrice * $buyPropr; //购买价格
        // $btcBuyValuation = $buyingPrice * $btcBalance; //BTC 购买估值

        // //计算购买数量
        // $buyNum = $balanceRatioArr[1] * (($usdtValuation - $btcBuyValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        // $buyOrdersNumber = $buyNum / $buyingPrice; //购买数量
        // $usdtBuyClinchBalance = $usdtBalance - $buyNum; //挂买以后USDT数量 USDT余额 减去 购买busd数量
        // $btcBuyClinchBalance = $btcBalance + $buyOrdersNumber; //挂买以后BTC数量 BTC余额 加上 购买数量

        // $result['pendingOrder']['buy']['price'] = $buyingPrice;
        // $result['pendingOrder']['buy']['amount'] = $buyOrdersNumber;
        // $result['pendingOrder']['buy']['btcValuation'] = $btcBuyClinchBalance * $buyingPrice;
        // $result['pendingOrder']['buy']['usdtValuation'] = $usdtBuyClinchBalance;

        // //计算 出售数量
        // $sellNum = $balanceRatioArr[0] * (($btcSellValuation - $usdtValuation) / ((float)$balanceRatioArr[0] + (float)$balanceRatioArr[1]));
        // $sellOrdersNumber = $sellNum / $sellingPrice;
        // $usdtSellClinchBalance = $usdtBalance + $sellNum; //挂卖以后USDT数量 USDT余额 加上 出售USDT数量
        // $btcSellClinchBalance = $btcBalance - $sellOrdersNumber; //挂卖以后GMX数量 BTC余额 减去 出售数量
        // $result['pendingOrder']['sell']['price'] = $sellingPrice;
        // $result['pendingOrder']['sell']['amount'] = $sellOrdersNumber;
        // $result['pendingOrder']['sell']['btcValuation'] = $btcSellClinchBalance * $sellingPrice;
        // $result['pendingOrder']['sell']['usdtValuation'] = $usdtSellClinchBalance;

        return $result;
        // echo "最小下单量: " . $minSizeOrderNum . "<br>";
        // echo "交易货币币种: " . $base_ccy . "<br>";
        // echo "计价货币币种: " . $quote_ccy . "<br>";
        // echo "GMX价格: " . $tradingPrice . "<br>";
        // echo "BUSD余额: " . $usdtBalance . "<br>BUSD估值: " . $usdtValuation . "<br>";
        // echo "GMX余额: " . $btcBalance . "<br>GMX估值: " . $btcValuation . "<br>";
        // echo "涨跌比例: " . $changeRatio . "<br>";
        // if($btcValuation > $usdtValuation) { //GMX的估值超过BUSD时候，卖GMX换成BUSDT
        //     echo "GMX出售数量: " . $bifiSellOrdersNumber . "<br>";
        // }
        // if($btcValuation < $usdtValuation) { //GMX的估值低于BUSD时，买GMX，换成BUSD
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