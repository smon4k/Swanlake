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

class Okx extends Base
{
    /**
     * 平衡仓位 - 下单
     * @author qinlh
     * @since 2022-06-29
     */
    public static function balancePositionOrder() {
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);
        $transactionCurrency = "BTC-USDT"; //交易币种
        $className = "\ccxt\\okex5";
        // $exchange  = new $className(array( //母账户
        //     'apiKey' => '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
        //     'secret' => '89D37429D52C5F8B8D8E8BFB964D79C8',
        //     'password' => 'Zx112211@',
        // ));
        $exchange  = new $className(array( //子账户
            'apiKey' => '036e3cdd-7dbc-467b-aaa7-75618a3afb83',
            'secret' => '02AD3DEE63EA35889654616031797F22',
            'password' => 'Zx112211@',
        ));
        try { 
            $balanceDetails = $exchange->fetch_account_balance();
            // p($balance);
            $btcBalance = 0;
            $usdtBalance = 0;
            foreach ($balanceDetails['details'] as $k => $v) {
                if(isset($v['eq'])) {
                    if($v['ccy'] == 'BTC' || $v['ccy'] == 'USDT') {
                        if($v['ccy'] == 'BTC' && (float)$v['eq'] > 0) {
                            $btcBalance += (float)$v['eq'];
                        }
                        if($v['ccy'] == 'USDT' && (float)$v['eq'] > 0) {
                            $usdtBalance += (float)$v['eq'];
                        }
                    }
                }
            }
            // p($btcBalance);
            //获取最小下单数量
            $rubikStatTakerValume = $exchange->fetch_markets_by_type('SPOT', ['instId'=>$transactionCurrency]);
            $minSizeOrderNum = isset($rubikStatTakerValume[0]['info']['minSz']) ? $rubikStatTakerValume[0]['info']['minSz'] : 0; //最小下单数量
            $base_ccy = isset($rubikStatTakerValume[0]['info']['baseCcy']) ? $rubikStatTakerValume[0]['info']['baseCcy'] : ''; //交易货币币种
            $quote_ccy = isset($rubikStatTakerValume[0]['info']['quoteCcy']) ? $rubikStatTakerValume[0]['info']['quoteCcy'] : ''; //计价货币币种
            $btcValuation = 0; //BTC估值
            $usdtValuation = 0; //USDT估值
            $btcPrice = 0;//BTC价格
            $changeRatioNum = 1; //涨跌比例
            $marketIndexTickers = $exchange->fetch_market_index_tickers($transactionCurrency); //获取交易BTC价格
            if($marketIndexTickers && $marketIndexTickers['idxPx'] > 0) {
                $btcPrice = (float)$marketIndexTickers['idxPx'];
                $btcValuation = $btcBalance * (float)$marketIndexTickers['idxPx'];
                $usdtValuation = $usdtBalance;
            }
            // p($btcPrice);
            $changeRatio = 0;
            $changeRatio = abs($btcValuation / $usdtValuation);
            $clientOrderId = 'Zx'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
            if($changeRatio > $changeRatioNum) { //涨跌大于1%
                // p($usdtValuation);
                if($btcValuation > $usdtValuation) { //btc的估值超过usdt时候，卖btc换成u
                    $btcSellNum = ($btcValuation - $usdtValuation) / 2;
                    $btcSellOrdersNumber = $btcSellNum / $btcPrice;
                    if($btcSellOrdersNumber > $minSizeOrderNum) {
                        $result = $exchange->create_trade_order($transactionCurrency, $clientOrderId, 'market', 'sell', $btcSellOrdersNumber, []);
                        if($result['sCode'] == 0) {
                            //获取上一次是否成对出现
                            $isPair = false;
                            $res = Db::name('okx_piggybank')->order('id desc')->limit(1);
                            if($res && $res['type'] == 1) {
                                $isPair = true;
                            }
                            $insertOrderData = [
                                'product_name' => $transactionCurrency,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 2,
                                'order_type' => 'market',
                                'amount' => $btcSellOrdersNumber,
                                'price' => $btcPrice,
                                'currency1' => $btcBalance,
                                'currency2' => $usdtBalance,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                            if($insertId) {
                                return true;
                            }
                        }
                    } else {
                        return false;
                    }
                }
                if($btcValuation < $usdtValuation) { //btc的估值低于usdt时，买btc，u换成btc
                    $usdtBuyNum = ($usdtValuation - $btcValuation) / 2;
                    $usdtSellOrdersNumber = $usdtBuyNum;
                    if($usdtSellOrdersNumber > $minSizeOrderNum) {
                        $result = $exchange->create_trade_order($transactionCurrency, $clientOrderId, 'market', 'buy', $usdtSellOrdersNumber, []);
                        if($result['sCode'] == 0) {
                            //获取上一次是否成对出现
                            $isPair = false;
                            $res = Db::name('okx_piggybank')->order('id desc')->limit(1);
                            if($res && $res['type'] == 2) {
                                $isPair = true;
                            }
                            $insertOrderData = [
                                'product_name' => $transactionCurrency,
                                'order_number' => $clientOrderId,
                                'td_mode' => 'cross',
                                'base_ccy' => $base_ccy,
                                'quote_ccy' => $quote_ccy,
                                'type' => 1,
                                'order_type' => 'market',
                                'amount' => $usdtSellOrdersNumber,
                                'price' => $btcPrice,
                                'currency1' => $btcBalance,
                                'currency2' => $usdtBalance,
                                'time' => date('Y-m-d H:i:s'),
                            ];
                            $insertId = Db::name('okx_piggybank')->insertGetId($insertOrderData);
                            if($insertId) {
                                return true;
                            }
                        }
                    } else {
                        return false;
                    }
                }
            } else {
                return false;
            }
            return false;
        } catch (\Exception $e) {
            return array(0, $e->getMessage());
        }
    }
    
}