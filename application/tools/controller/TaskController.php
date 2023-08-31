<?php
namespace app\tools\controller;

use app\tools\model\Reptile;
use app\api\model\FundMonitoring;
use app\tools\model\MaticReptile;
use app\tools\model\Okx;
use app\tools\model\Binance;
use app\api\model\Task;
use app\api\model\TaskContract;
use app\api\model\User;
use app\api\model\MyProduct;
use app\api\model\DayNetworth;
use app\api\model\BscAddressStatistics;
use app\api\model\QuantifyAccount;
use app\admin\model\LlpFinance;
use app\answer\model\DayNetworth as DayNetworthAnswer;
use app\admin\model\Piggybank;
use app\admin\model\BinancePiggybank;
use app\power\model\PowerUser;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\CLFfmpeg;
use ClassLibrary\CLFile;
use RequestService\RequestService;
use think\Log;
use think\Config;
use cache\Rediscache;

/**
 * 任务
 * Class TaskController
 * @package app\tools\controller
 */
class TaskController extends ToolsBaseController
{

    /**
     * 初始化函数
     */
    public function _initialize()
    {
        if (!request()->isCli()) {
            echo_info('只能命令行访问');
            exit;
        }
        $id = get_param('id', ClFieldVerify::instance()->fetchVerifies(), '任务主键id', 0);
        if ($id == 0) {
            //取消日志相关兼容处理
            Log::init(['level' => ['task_run'], 'allow_key' => ['task_run']]);
            Log::key(time());
        }
    }

    /**
     * 处理普通实时任务
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function deal()
    {
        $params = $this->ArgvParamsArr();
        $begin_time = time();
        $id         = isset($params['id']) ? $params['id'] : 0;
        // p($id);
        //处理数据
        Task::deal($id);

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 处理合约任务
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function contractDeal()
    {
        $params = $this->ArgvParamsArr();
        $begin_time = time();
        $id         = isset($params['id']) ? $params['id'] : 0;
        // p($id);
        //处理数据
        TaskContract::deal($id);

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 定时获取每天的投注明细
     * @author qinlh
     * @since 2022-07-10
     */
    public function saveUserProductData() {
        $params = $this->ArgvParamsArr();
        $begin_time = time();
        MyProduct::saveProductListData();
        MyProduct::saveUserProductData();
        return (time() - $begin_time) . "s\n";
    }

    /**
     * 定时获取每天的资金监控余额
     * @author qinlh
     * @since 2022-07-16
     */
    public function getFundMonitoring() {
        $begin_time = time();

        $res1 = FundMonitoring::getHuobiAccountBalance(); //获取火币账户余额
        // var_dump($res);die;

        $res2 = FundMonitoring::getOkexAccountBalance(); //获取okex账户余额

        $res3 = FundMonitoring::getAccountBalance(); //获取okex 指定 账户余额

        if($res1 && $res2 && $res3) {
            FundMonitoring::saveStatisticsData(); //统计数据
        }
        // var_dump($res);die;

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 天鹅湖 - 默认更新今天的净值数据 
     * 如果今天净值数据不存在 利润默认给0
     * @author qinlh
     * @since 2022-07-31
     */
    public function saveTodayNetWorth() {
        $begin_time = time();
        $isNetWorth = DayNetworth::getTodayIsNetWorth();
        if(!$isNetWorth) {
            $address = "0x7DCBFF9995AC72222C6d46A45e82aA90B627f36D";
            DayNetworth::saveDayNetworth($address, 0, 1);
        }
        return (time() - $begin_time) . "s\n";
    }
    
    /**
     * 一站到底 - 默认更新今天的净值数据 
     * 如果今天净值数据不存在 利润默认给0
     * @author qinlh
     * @since 2022-07-31
     */
    public function saveAnswerTodayNetWorth() {
        $begin_time = time();
        $isNetWorth = DayNetworthAnswer::getTodayIsNetWorth();
        if(!$isNetWorth) {
            $address = "0x0000000000000000000000000000000000000000";
            DayNetworthAnswer::saveDayNetworth($address, 0, 1);
        }
        return (time() - $begin_time) . "s\n";
    }


    /**
     * Okx 平衡仓位 
     * 下单
     * @author qinlh
     * @since 2022-08-17
     */
    public function okxPiggybankOrder() {
        $begin_time = time();

        Okx::balancePositionOrder();

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Okx 平衡仓位 
     * 挂单
     * @author qinlh
     * @since 2023-01-12
     */
    public function okxPiggybankPendingOrder() {
        $begin_time = time();

        $key = "Okx:PendingOrder:Lock";
        $key_01 = "Okx:PendingOrderTimes:Lock";

        $isPendingOrderLock = Rediscache::getInstance()->get($key); 
        $pendingOrderTimes = Rediscache::getInstance()->get($key_01); 

        if(!$isPendingOrderLock || (float)$pendingOrderTimes > 5) {
            Rediscache::getInstance()->set($key, 1); 
            Rediscache::getInstance()->del($key_01); 
    
            Okx::balancePendingOrder();

            Rediscache::getInstance()->del($key); 
        } else {
            echo "有任务没执行完... ".$pendingOrderTimes."次 \r\n";
            $pendingOrderTimesNum = $pendingOrderTimes + 1;
            Rediscache::getInstance()->set($key_01, $pendingOrderTimesNum); 
        }

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Binance 平衡仓位 
     * 下单
     * @author qinlh
     * @since 2022-08-17
     */
    public function binancePiggybankOrder() {
        $begin_time = time();

        Binance::balancePositionOrder();

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Binance 平衡仓位 
     * 挂单
     * @author qinlh
     * @since 2022-08-17
     */
    public function binancePiggybankPendingOrder() {
        $begin_time = time();

        $key = "Binance:PendingOrder:Lock";
        $key_01 = "Binance:PendingOrderTimes:Lock";

        $isPendingOrderLock = Rediscache::getInstance()->get($key); 
        $pendingOrderTimes = Rediscache::getInstance()->get($key_01); 

        if(!$isPendingOrderLock || (float)$pendingOrderTimes > 5) {
            Rediscache::getInstance()->set($key, 1); 
            Rediscache::getInstance()->del($key_01); 
    
            Binance::balancePendingOrder();

            Rediscache::getInstance()->del($key); 
        } else {
            echo "有任务没执行完... ".$pendingOrderTimes."次 \r\n";
            $pendingOrderTimesNum = $pendingOrderTimes + 1;
            Rediscache::getInstance()->set($key_01, $pendingOrderTimesNum); 
        }

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Binance 平衡仓位 
     * 取消订单
     * @author qinlh
     * @since 2022-08-17
     */
    public function cancelOrder() {
        $begin_time = time();

        Binance::cancelOrder();

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Binance 撤单 
     * 取消订单
     * @author qinlh
     * @since 2022-08-17
     */
    public function fetchCancelOpenOrder() {
        $begin_time = time();

        Okx::fetchCancelOpenOrder('GMX-USDT');

        return (time() - $begin_time) . "s\n";
    }

    /**
     * BTC-USDT https://www.okx.com/trade-spot/btc-usdt
     * 登录账号密码：smon4k08/Zx112211@
     * Okx 按照比例平衡仓位 出售赎回
     * 每日数据统计
     * @author qinlh
     * @since 2022-08-17
     */
    public function okxPiggybankDate() {
        $begin_time = time();

        Okx::piggybankDate();

        return (time() - $begin_time) . "s\n";
    }

    /**
     * BIFI/BUSD https://www.binancezh.top/zh-CN/trade/BIFI_BUSD?theme=dark&type=spot
     * 登录账号密码：smon4k08/Zx112211@
     * Binance 按照比例平衡仓位 出售赎回
     * 每日数据统计
     * @author qinlh
     * @since 2022-08-17
     */
    public function binancePiggybankDate() {
        $begin_time = time();

        Binance::piggybankDate();

        return (time() - $begin_time) . "s\n";
    }

     /**
     * Okx 出入金 币种统计 U本文 币本位计算
     * @author qinlh
     * @since 2022-08-17
     */
    public function calcDepositAndWithdrawal() {
        $begin_time = time();
        $transactionCurrency = Okx::gettTradingPairName('Okx'); //交易币种
        // $transactionCurrency = "BTC-USDT"; //交易币种
        Piggybank::calcDepositAndWithdrawal($transactionCurrency, 1, 0);

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Binance 出入金 币种统计 U本文 币本位计算
     * @author qinlh
     * @since 2022-08-17
     */
    public function calcBinanceDepositAndWithdrawal() {
        $begin_time = time();

        // $transactionCurrency = "BIFI-BUSD"; //交易币种
        $transactionCurrency = Okx::gettTradingPairName('Binance'); //交易币种
        BinancePiggybank::calcDepositAndWithdrawal($transactionCurrency, 1, 0);

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 获取持仓列表未平仓数据检测是否已平仓
     * @author qinlh
     * @since 2023-05-07
     */
    public function getPositionsClosedPosition() {
        $begin_time = time();
        $accountList = QuantifyAccount::getAccountList(['is_position' => 1]);
        $account_id = 0;
        foreach ($accountList as $key => $val) {
            $account_id = $val['id'];
            QuantifyAccount::getPositionsClosedPosition($account_id, 'GMX');
        }
        return (time() - $begin_time) . "s\n";
    }



    //  /**
    //  * Okx 出入金 币种统计
    //  * 实时更新币种总结余及价格
    //  * @author qinlh
    //  * @since 2022-08-17
    //  */
    // public function saveUpdateDayTotalBalance() {
    //     $begin_time = time();

    //     $transactionCurrency = "BTC-USDT"; //交易币种
    //     Piggybank::saveUpdateDayTotalBalance($transactionCurrency);

    //     return (time() - $begin_time) . "s\n";
    // }

    public function awsUpload() {
        $video_url = DOCUMENT_ROOT_PATH . "/upload/h2o-media/2022/06/08/qinlh.mp4";
        $videoInfo = CLFfmpeg::getVideoInfo($video_url);
        $awsUpload = User::AwsS3MultipartUpload($video_url, "qinlh.mp4", $videoInfo['size']);
        p($awsUpload);
    }

    public function catchRemote() {
        $begin_time = time();

        // $remote_file_url = "https://h2o-finance-images.s3.amazonaws.com/h2oMediaDev/image/2022-06-12/205901_1103894208.mp4"; //15M 2S
        $remote_file_url = "https://h2o-finance-images.s3.amazonaws.com/h2oMediaDev/image/2022-06-11/215225_1345024498.mp4"; //61M
        $local_absolute_file = DOCUMENT_ROOT_PATH . "/upload/h2o-media/download/111.mp4";
        $res = CLFile::catchRemote($remote_file_url,$local_absolute_file);
        echo $res . "s\n";
        return (time() - $begin_time) . "s\n";
    }

    /**
     * Okx 获取订单信息
     * @author qinlh
     * @since 2022-08-17
     */
    public function fetchTradeOrder() {
        $begin_time = time();

        Okx::fetchTradeOrder();

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 定时去获取指定币种供应量及价格
     * @author qinlh
     * @since 2022-10-22
     */
    public function getBscscanTokenHolders() {
        $begin_time = time();
        // $tokens = BscAddressStatistics::getTokensList();
        $tokens = array(
            // ['name' => 'Cake', 'token' => '0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82', 'chain' => 'bscscan'],
        //     ['name' => 'BNB', 'token' => '0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c', 'chain' => 'bscscan'],
        //     ['name' => 'BSW', 'token' => '0x965F527D9159dCe6288a2219DB51fc6Eef120dD1', 'chain' => 'bscscan'],
        //     ['name' => 'BABY', 'token' => '0x53E562b9B7E5E94b81f10e96Ee70Ad06df3D2657', 'chain' => 'bscscan'],
        //     ['name' => 'Alpaca', 'token' => '0x8F0528cE5eF7B51152A59745bEfDD91D97091d2F', 'chain' => 'bscscan'],
        //     ['name' => 'BIFI', 'token' => '0xCa3F508B8e4Dd382eE878A314789373D80A5190A', 'chain' => 'bscscan'],
        //     ['name' => 'QUICK', 'token' => '0xb5c064f955d8e7f38fe0460c556a72987494ee17', 'chain' => 'polygonscan'],
        //     ['name' => 'SNS', 'token' => '0xD5CBaE3F69B0640724A6532cC81BE9C798A755A7', 'chain' => 'bscscan'],
        //     ['name' => 'XVS', 'token' => '0xcF6BB5389c92Bdda8a3747Ddb454cB7a64626C63', 'chain' => 'bscscan'],
            // ['name' => 'Guru', 'token' => '0xF1932eC9784B695520258F968b9575724af6eFa8', 'chain' => 'bscscan'],
        //     ['name' => 'GMT', 'token' => '0x3019BF2a2eF8040C242C9a4c5c4BD4C81678b2A1', 'chain' => 'bscscan'],
        //     ['name' => 'CHESS', 'token' => '0x20de22029ab63cf9A7Cf5fEB2b737Ca1eE4c82A6', 'chain' => 'bscscan'],
            // ['name' => 'BabyDoge', 'token' => '0xc748673057861a797275CD8A068AbB95A902e8de', 'chain' => 'bscscan'],
            // ['name' => 'POT', 'token' => '0x3B5E381130673F794a5CF67FBbA48688386BEa86', 'chain' => 'bscscan'],
            // ['name' => 'BlueDoge', 'token' => '0xc7489D3383bD7E7772b03548C30DC979e612E8dE', 'chain' => 'bscscan'],
        //     ['name' => 'H2O', 'token' => '0xC446c2B48328e5D2178092707F8287289ED7e8D6', 'chain' => 'bscscan'],
            // ['name' => 'YOOSHI', 'token' => '0x02fF5065692783374947393723dbA9599e59F591', 'chain' => 'bscscan'],
            // ['name' => 'BNX', 'token' => '0x8C851d1a123Ff703BD1f9dabe631b69902Df5f97', 'chain' => 'bscscan'],
            // ['name' => 'PinkSale', 'token' => '0x602bA546A7B06e0FC7f58fD27EB6996eCC824689', 'chain' => 'bscscan'],
            // ['name' => 'FORTUNE', 'token' => '0x2d716831D82d837C3922aD8c10FE70757b5814FE', 'chain' => 'bscscan'],
            // ['name' => 'MYPO', 'token' => '0xd0BA1Cad35341ACd1CD88a85E16B054bA9ccC385', 'chain' => 'bscscan'],
            // ['name' => 'JUMP', 'token' => '0x130025eE738A66E691E6A7a62381CB33c6d9Ae83', 'chain' => 'bscscan'],
            // ['name' => 'HYPR', 'token' => '0x03D6BD3d48F956D783456695698C407A46ecD54d', 'chain' => 'bscscan'],
            // ['name' => 'AUDIO', 'token' => '0xb0B2d54802B018B393A0086a34DD4c1f26F3D073', 'chain' => 'bscscan'],
            // ['name' => 'GUT', 'token' => '0x36E714D63B676236B72a0a4405F726337b06b6e5', 'chain' => 'bscscan'],
            // ['name' => 'TON(ETH)', 'token' => '0x582d872A1B094FC48F5DE31D3B73F2D9bE47def1', 'chain' => 'etherscan'],
        //     // ['name' => 'H2O', 'token' => '0xC446c2B48328e5D2178092707F8287289ED7e8D6'],
        //     // ['name' => 'Guru', 'token' => '0xF1932eC9784B695520258F968b9575724af6eFa8'],
            ['name' => 'BTC(稳定币)', 'token' => '', 'chain' => 'defillama'],
        );
        // p($tokens);
        // p(Config::get('www_bscscan_contract'));
        foreach ($tokens as $key => $val) {
            $name = $val['name'];
            $params = ['name' => $name, 'token' => $val['token'], 'chain' => $val['chain']];
            $returnArray = [];
            if($name == "BTC(稳定币)") {
                $api_url = "https://stablecoins.llama.fi/stablecoins?includePrices=true";
                $response_string = CurlGetRequest($api_url, []);
                $returnArray = json_decode($response_string, true);
                $TotalStablecoins = 0;
                $TotalStablecoinsArr = [];
                foreach ($returnArray['peggedAssets'] as $key => $vv) {
                    // p($val);
                    if(isset($vv['circulating'][$vv['pegType']]) || isset($vv['circulating'][$vv['pegType']])) {
                        if($vv['pegMechanism'] === 'fiat-backed' || $vv['pegMechanism'] === 'crypto-backed') {
                            if($vv['pegType'] == "peggedEUR") {
                                $TotalStablecoins += (float)$vv['circulating'][$vv['pegType']] * 1.09;
                            } else {
                                $TotalStablecoins += (float)$vv['circulating'][$vv['pegType']];
                            }
                            if($vv['symbol'] == 'USDT' || $vv['symbol'] == 'USDC' || $vv['symbol'] == 'DAI' || $vv['symbol'] == 'TUSD' || $vv['symbol'] == 'BUSD' || $vv['symbol'] == 'FRAX' || $vv['symbol'] == 'USDD' || $vv['symbol'] == 'USDP' || $vv['symbol'] == 'FDUSD' || $vv['symbol'] == 'GUSD') {
                                // $TotalStablecoins += isset($vv['circulating'][$vv['pegType']]) ? (float)$vv['circulating'][$vv['pegType']] : 0;
                                if(isset($TotalStablecoinsArr[$vv['symbol']])) {
                                    if($vv['pegType'] == "peggedEUR") {
                                        $TotalStablecoinsArr[$vv['symbol']] += (float)$vv['circulating'][$vv['pegType']] * 1.09;
                                    } else {
                                        $TotalStablecoinsArr[$vv['symbol']] += (float)$vv['circulating'][$vv['pegType']];
                                    }
                                } else {
                                    if($vv['pegType'] == "peggedEUR") {
                                        $TotalStablecoinsArr[$vv['symbol']] = (float)$vv['circulating'][$vv['pegType']] * 1.09;
                                    } else {
                                        $TotalStablecoinsArr[$vv['symbol']] = (float)$vv['circulating'][$vv['pegType']];
                                    }
                                }
                            }
                        }
                    }
                }
                // p($TotalStablecoins);
                $date = date('Y-m-d');
                $btcPrice = LlpFinance::getBtcPrice($date);
                $params = [
                    'price' => $btcPrice,
                    'holders' => $TotalStablecoins,
                    'balance' => 0,
                    'value' => 0,
                    'other_data' => json_encode($TotalStablecoinsArr)
                ];
                // p($TotalStablecoinsArr);
                $setData = BscAddressStatistics::setBscAddressStatistics($name, $val['token'], $params);
                if($setData) {
                    echo "====== Success 写入" . $name . "数据成功 ======" . "\n\n";
                } else {
                    echo "====== Error 写入" . $name . "数据失败 ======" . "\n\n";
                }
            } else {
                $response_string = RequestService::doJsonCurlPost(Config::get('www_bscscan_contract').Config::get('reptile_service')['get_bsc_token_holders'], json_encode($params));
                if($response_string) {
                    // p($response_string);
                    echo "====== Success 爬取" . $name . "数据成功 ======" . "\n";
                    $returnArray = json_decode($response_string, true);
                    $setData = BscAddressStatistics::setBscAddressStatistics($name, $val['token'], $returnArray);
                    if($setData) {
                        echo "====== Success 写入" . $name . "数据成功 ======" . "\n\n";
                    } else {
                        echo "====== Error 写入" . $name . "数据失败 ======" . "\n\n";
                    }
                } else {
                    echo "====== Error 爬取" . $name . "数据失败 ======" . "\n";
                }
            }
        }

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 短期算力币 发放收益
     * @author qinlh
     * @since 2022-12-18
     */
    public function powerSendingIncome() {
        $begin_time = time();

        PowerUser::startSendingIncome();
        
        return (time() - $begin_time) . "s\n";
    }

    /**
     * 异步获取追踪数据
     * @author qinlh
     * @since 2022-12-18
     */
    public function asyncUpdateFramesDetails() {
        $begin_time = time();

        LlpFinance::asyncUpdateFramesDetails();
        
        return (time() - $begin_time) . "s\n";
    }

    /**
     * 计算量化账户监控数据统计
     * @author qinlh
     * @since 2023-01-31
     */
    public function calcQuantifyAccountData() {
        $begin_time = time();
        $accountList = QuantifyAccount::getAccountList();
        $account_id = 0;
        // $balance = QuantifyAccount::getTradePairBalance([
        //     'api_key' => 'ix047O4nUsrMjl7RNNfkEsB1BlACOq6JceXwDbkUusHWgrJTLjuLPWP7kQc8F3gI',
        //     'secret_key' => 'tb7K9G7gIIZx0XwPDPsL2xOvLs9RJ1BdRv8Y0TbU2QzY09KSYMRXlQ62SdmZzsga',
        // ]);
        // p($balance);
        // QuantifyAccount::calcQuantifyAccountData(1);die;
        foreach ($accountList as $key => $val) {
            $account_id = $val['id'];
            QuantifyAccount::calcQuantifyAccountData($account_id);
        }
            
        
        return (time() - $begin_time) . "s\n";
    }









    public function AnalogData() {
        $begin_time = time();

        BscAddressStatistics::AnalogData();

        return (time() - $begin_time) . "s\n";
    }

    public function delTimeData() {
        $begin_time = time();

        BscAddressStatistics::delTimeData();

        return (time() - $begin_time) . "s\n";
    }

    // public function startRecoverData() {
    //     $begin_time = time();

    //     Binance::startRecoverData();

    //     return (time() - $begin_time) . "s\n";
    // }
}
