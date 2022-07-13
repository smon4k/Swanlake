<?php
namespace app\tools\controller;

use app\tools\model\Reptile;
use app\tools\model\Huobi;
use app\tools\model\MaticReptile;
use app\api\model\Task;
use app\api\model\User;
use app\api\model\MyProduct;
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
     * 处理任务
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

    public function huobiDemo() {
        Huobi::getAccountBalance();
    }
}
