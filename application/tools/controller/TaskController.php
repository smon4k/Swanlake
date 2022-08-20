<?php
namespace app\tools\controller;

use app\tools\model\Reptile;
use app\api\model\FundMonitoring;
use app\tools\model\MaticReptile;
use app\tools\model\Okx;
use app\api\model\Task;
use app\api\model\TaskContract;
use app\api\model\User;
use app\api\model\MyProduct;
use app\api\model\DayNetworth;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\CLFfmpeg;
use ClassLibrary\CLFile;
use RequestService\RequestService;
use think\Log;
use think\Config;

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

        $res3 = FundMonitoring::getAccountBalance(); //获取okex账户余额

        if($res1 && $res2 && $res3) {
            FundMonitoring::saveStatisticsData(); //统计数据
        }
        // var_dump($res);die;

        return (time() - $begin_time) . "s\n";
    }

    /**
     * 默认更新今天的净值数据 
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
     * Okx 平衡仓位 下单
     * @author qinlh
     * @since 2022-08-17
     */
    public function okxPiggybankOrder() {
        $begin_time = time();

        Okx::balancePositionOrder();

        return (time() - $begin_time) . "s\n";
    }

    /**
     * Okx 平衡仓位 每日数据统计
     * @author qinlh
     * @since 2022-08-17
     */
    public function okxPiggybankDate() {
        $begin_time = time();

        Okx::piggybankDate();

        return (time() - $begin_time) . "s\n";
    }

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
}
