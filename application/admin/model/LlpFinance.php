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
            $btc_price = self::getBtcPrice();
            $timeFrames = self::getTimeFrames();
            $llp_price = $timeFrames['price'] ? $timeFrames['price'] : 1;
            $totalProfit = self::sum('netProfit');
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
                'netProfit' => (float)$timeFrames['valueMovement']['valueChange'] + (float)$timeFrames['valueMovement']['fee'],
                'totalProfit' => (float)$totalProfit - (float)$timeFrames['valueMovement']['valueChange'],
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
        $from = strtotime(date('Y-m-d') . "00:00:00");
        $to = strtotime(date('Y-m-d') . "08:00:00");
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
        if($data && count((array)$data) > 0) {
            $dataArray = $data['data'][0];
            $btc_price = self::getBtcPrice();
            $timeFrames = self::getTimeFrames();
            $llp_price = $timeFrames['price'] ? $timeFrames['price'] : 1;
            $totalProfit = self::sum('netProfit');
            $saveData = [
                'from_time' => date('Y-m-d H:i:s', $dataArray['from']),
                'to_time' => date('Y-m-d H:i:s', $dataArray['to']),
                'amount' => $dataArray['amount'],
                'value' => $dataArray['value'],
                'valueChange' => $dataArray['valueMovement']['valueChange'],
                'fee' => $dataArray['valueMovement']['fee'],
                'pnl' => $dataArray['valueMovement']['pnl'],
                'price' => $dataArray['valueMovement']['price'],
                'totalChange' => $dataArray['totalChange'],
                'nominalApr' => $dataArray['nominalApr'],
                'netApr' => $dataArray['netApr'],
                'btc_price' => $btc_price,
                'llp_price' => $llp_price,
                'netProfit' => (float)$dataArray['valueMovement']['valueChange'] + (float)$dataArray['valueMovement']['fee'],
                'totalProfit' => (float)$totalProfit - (float)$dataArray['valueMovement']['valueChange'],
            ];
            $res = self::setTimeFramesDetails($saveData);
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
    public static function setTimeFramesDetails($data=[]) {
        if($data) {
            try {
                $date = date('Y-m-d');
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
                        'netProfit' => (float)$data['valueChange'] + (float)$data['fee'],
                        'totalProfit' => $data['totalProfit'],
                        'time' => date('Y-m-d H:i:s'),
                    ];
                    $res = self::where('id', $IsResData['id'])->update($updateData);
                    if($res) {
                        return true;
                    }
                } else {
                    $insertData = [
                        'date' => $date,
                        'from_time' => $data['from_time'],
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
                        'netProfit' => (float)$data['valueChange'] + (float)$data['fee'],
                        'totalProfit' => $data['totalProfit'],
                        'time' => date('Y-m-d H:i:s'),
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
    public static function getBtcPrice() {
        $url = "https://www.h2ofinance.pro/getPoolBtc";
        $poolBtc =
        $params = [];
        $response_string = RequestService::doCurlGetRequest($url, $params);
        $btc_currency_price = 0;
        if($response_string && $response_string[0]) {
            $btc_currency_price = $response_string[0]['currency_price'] ? (float)$response_string[0]['currency_price'] : 1;
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