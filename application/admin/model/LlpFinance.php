<?php

namespace app\admin\model;

use think\Model;
use RequestService\RequestService;

class LlpFinance extends Base {


    /**
     * 获取跟踪数据数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getLlpFinanceList($where, $page, $limit, $order='date desc')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $date = date('Y-m-d');
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
        if($page == 1) {
            $returnArray = [];
            $btc_price = self::getBtcPrice($date);
            $timeFrames = self::getTimeFrames();
            $llp_price = $timeFrames['price'] ? $timeFrames['price'] : 1;
            $totalProfit = self::whereTime('date', '<=', $date)->sum('netProfit');
            $netProfit = (float)$timeFrames['valueMovement']['pnl'] + (float)$timeFrames['valueMovement']['fee']; //净利润
            // $totalValueChange = self::whereTime('date', '<=', $date)->sum('valueChange');
            $returnArray[0] = [
                'from_time' => date('Y-m-d H:i:s', $timeFrames['from']),
                'to_time' => date('Y-m-d H:i:s', $timeFrames['to']),
                'amount' => $timeFrames['amount'],
                'value' => $timeFrames['value'],
                'valueChange' => $timeFrames['valueMovement']['valueChange'],
                'fee' => $timeFrames['valueMovement']['fee'],
                'pnl' => $timeFrames['valueMovement']['pnl'],
                'price' => $timeFrames['valueMovement']['price'],
                'totalChange' => $timeFrames['totalChange'],
                'nominalApr' => $timeFrames['nominalApr'],
                'netApr' => $timeFrames['netApr'],
                'btc_price' => $btc_price,
                'llp_price' => $llp_price,
                'netProfit' => $netProfit,
                'totalProfit' => (float)$totalProfit + (float)$netProfit,
                // 'totalProfit' => (float)$totalProfit,
            ];
            foreach ($lists as $key => $val) {
                $returnArray[$key + 1] = $val;
            }
        } else {
            $returnArray = $lists;
        }
        // p($returnArray);
        return ['count'=>$count, 'allpage'=>$allpage, 'lists'=>$returnArray];
    }

    /**
     * 异步获取追踪数据
     * @author qinlh
     * @since 2023-05-18
     */
    public static function asyncUpdateFramesDetails() {
        $wallet = "0x3e0d064e079f93b3ed7a023557fc9716bcbb20ae";
        $tranche = "0xcC5368f152453D497061CB1fB578D2d3C54bD0A0";
        $page = 1;
        $size = 1000;
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $from = strtotime($date . "00:00:00");
        $to = strtotime($date . "08:00:00");
        $sort = 'desc';
        // p($to);
        $params = [
            'wallet' => $wallet,
            'tranche' => $tranche,
            'page' => $page,
            'size' => $size,
            'from' => $from,
            'to' => $to,
            'sort' => $sort,
        ];
        $url = "https://llp-api.level.finance/time-frames";
        $url = $url . '?' . http_build_query($params);
        // $data = RequestService::doCurlGetRequest($url, $params);
        $dataJson = file_get_contents($url);
        $data = json_decode($dataJson, true);
        if($data && count((array)$data['data']) > 0) {
            $dataArray = $data['data'][0];
            $btc_price = self::getBtcPrice($date);
            $timeFrames = self::getTimeFrames();
            $llp_price = $timeFrames['price'] ? $timeFrames['price'] : 1;
            $totalProfit = self::whereTime('date', '<=', $date)->sum('netProfit');
            $netProfit = (float)$dataArray['valueMovement']['pnl'] + (float)$dataArray['valueMovement']['fee']; //净利润
            // $totalValueChange = self::whereTime('date', '<=', $date)->sum('valueChange');
            $saveData = [
                'from_time' => date('Y-m-d H:i:s', $dataArray['from']),
                'to_time' => date('Y-m-d H:i:s', $dataArray['to']),
                'amount' => $dataArray['amount'], //流动性
                'value' => $dataArray['value'], //估值
                'valueChange' => $dataArray['valueMovement']['valueChange'], //出入金
                'fee' => $dataArray['valueMovement']['fee'], //手续费
                'pnl' => $dataArray['valueMovement']['pnl'], //输赢
                'price' => $dataArray['valueMovement']['price'], //资产估值变动
                'totalChange' => $dataArray['totalChange'], //总变化
                'nominalApr' => $dataArray['nominalApr'],//名义利润率
                'netApr' => $dataArray['netApr'], //净利润率
                'btc_price' => $btc_price,//比特币价
                'llp_price' => $llp_price,//LLP价
                'netProfit' => $netProfit, //净利润 = 手续费+输赢
                'totalProfit' => (float)$totalProfit, //总近利
            ];
            $res = self::setTimeFramesDetails($saveData, $date, $time);
            if($res) {
                return true;
            }
        }
        return false;
    }

    /**
     * 写入跟踪数据
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
    public static function setTimeFramesDetails($data=[], $date='', $time='') {
        if($data) {
            try {
                $IsResData = self::where(['date' => $date])->find();
                if($IsResData && count((array)$IsResData) > 0) {
                    $updateData = [
                        'from_time' => $data['from_time'],
                        'to_time' => $data['to_time'],
                        'amount' => $data['amount'],
                        'value' => $data['value'],
                        'valueChange' => $data['valueChange'],
                        'fee' => $data['fee'],
                        'pnl' => $data['pnl'],
                        'price' => $data['price'],
                        'totalChange' => $data['totalChange'],
                        'nominalApr' => $data['nominalApr'],
                        'netApr' => $data['netApr'],
                        'btc_price' => $data['btc_price'],
                        'llp_price' => $data['llp_price'],
                        'netProfit' => $data['netProfit'],
                        'totalProfit' => $data['totalProfit'],
                        'time' => $time,
                    ];
                    $res = self::where('id', $IsResData['id'])->update($updateData);
                    if($res) {
                        return true;
                    }
                } else {
                    $insertData = [
                        'date' => $date,
                        'from_time' => $data['from_time'],
                        'to_time' => $data['to_time'],
                        'amount' => $data['amount'],
                        'value' => $data['value'],
                        'valueChange' => $data['valueChange'],
                        'fee' => $data['fee'],
                        'pnl' => $data['pnl'],
                        'price' => $data['price'],
                        'totalChange' => $data['totalChange'],
                        'nominalApr' => $data['nominalApr'],
                        'netApr' => $data['netApr'],
                        'btc_price' => $data['btc_price'],
                        'llp_price' => $data['llp_price'],
                        'netProfit' => $data['netProfit'],
                        'totalProfit' => $data['totalProfit'],
                        'time' => $time,
                    ];
                    self::insert($insertData);
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
     * 获取BTC价格
     * @author qinlh
     * @since 2023-05-18
     */
    public static function getBtcPrice($date='') {
        $url = "https://apiy.h2ofinance.pro/api/apy/getBetNewPrice";
        $poolBtc =
        $params = [];
        $response_string = file_get_contents($url);
        $response_string_arr = json_decode($response_string, true);
        $btc_currency_price = 0;
        if($response_string_arr && $response_string_arr['data']) {
            $btc_currency_price = $response_string_arr['data'];
        }
        return $btc_currency_price;
    }

    /**
     * 获取LLP价格
     * @author qinlh
     * @since 2023-05-18
     */
    public static function getTimeFrames() {
        $url = "https://llp-api.level.finance/time-frames/live";
        $poolBtc =
        $params = [
           'wallet' => "0x3e0d064e079f93b3ed7a023557fc9716bcbb20ae", 
           'tranche' => "0xcC5368f152453D497061CB1fB578D2d3C54bD0A0", 
        ];
        $url = $url . '?' . http_build_query($params);
        // $data = RequestService::doCurlGetRequest($url, $params);
        $dataJson = file_get_contents($url);
        $dataArr = json_decode($dataJson, true);
        $btc_currency_price = 0;
        if($dataArr && $dataArr['data']) {
            return $dataArr['data'];
        }
        return [];
    }
}