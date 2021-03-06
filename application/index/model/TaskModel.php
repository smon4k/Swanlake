<?php
/**
 * Created by PhpStorm.
 * User: SmartInit
 * Date: 2018/08/10
 * Time: 10:27:59
 */

namespace app\index\model;

use app\index\map\TaskMap;
use think\Exception;
use think\Model;

/**
 * 后台任务 Model
 */
class TaskModel extends TaskMap
{

    /**
     * 缓存清除触发器
     * @param $item
     */
    protected function cacheRemoveTrigger($item)
    {
        //先执行父类
        parent::cacheRemoveTrigger($item);
    }

    /**
     * 处理任务
     * @param int $id 执行的id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function deal($id = 0)
    {
        //处理数据
        if ($id == 0) {
            $is_executing = self::instance()->where([self::F_START => 1, self::F_TYPE => 1])->find(); //查询是否存在正在分批执行中的进程
            if($is_executing && count((array)$is_executing) > 0) {
                return true;
            } else {    
                $item = self::instance()->where([self::F_START => 0])->order([self::F_ID => self::V_ORDER_ASC])->find();
                if (empty($item)) {
                    return true;
                }
            }
        } else {
            $item = self::getById($id);
        }
        // p($item);
        //设置正在执行
        self::instance()->where([
            self::F_ID => $item[self::F_ID],
        ])->setField([
            self::F_START_TIME => time(),
            self::F_START => 1,
        ]);
        //执行
        log_info('task-start-' . $item[self::F_ID]);
        try {
            $EvalResult = eval('return '.$item[self::F_COMMAND].';');
            if($EvalResult) {
                if(isset($EvalResult['code']) && $EvalResult['code'] == 0 && isset($EvalResult['message'])) {
                    $error_msg = json_encode([
                        'message' => $EvalResult['message'],
                        'code' => $EvalResult['code'],
                    ], JSON_UNESCAPED_UNICODE);
                    self::instance()->where([
                        self::F_ID => $item[self::F_ID],
                    ])->setField([
                        self::F_REMARK => $error_msg,
                        self::F_END_TIME => time(),
                        self::F_START => 3,
                    ]);
                    log_info('task-error', $error_msg);
                } else {
                    //设置执行的结束时
                    // self::instance()->query('SET AUTOCOMMIT=0;');
                    self::instance()->where([
                        self::F_ID => $item[self::F_ID],
                    ])->setField([
                        self::F_END_TIME => time(),
                        self::F_START => 2,
                    ]);
                    self::instance()->query("commit"); 
                }
            } else {
                self::instance()->where([
                    self::F_ID => $item[self::F_ID],
                ])->setField([
                    // self::F_REMARK => $error_msg,
                    self::F_END_TIME => time(),
                    self::F_START => 3,
                ]);
            }
        } catch (Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            self::instance()->where([
                self::F_ID => $item[self::F_ID],
            ])->setField([
                self::F_REMARK => $error_msg,
                self::F_END_TIME => time(),
                self::F_START => 3,
            ]);
            log_info('task-error', $error_msg);
            if ($id > 0) {
                echo_info('task-error', $error_msg);
            }
        }
        //结束
        log_info('task-end-' . $item[self::F_ID]);
        return true;
    }

    /**
     * 创建任务
     * @param string $command 类似任务命令:app\index\model\AdminLoginLogModel::sendEmail();
     * @param int $within_seconds_ignore_this_cmd 在多长时间内忽略该任务，比如某些不需要太精确的统计任务，可以设置为60秒，即60秒内只执行一次任务
     * @return bool|int|string
     */
    public static function createTask($command, $within_seconds_ignore_this_cmd = 0)
    {
        $is_insert = true;
        if ($within_seconds_ignore_this_cmd > 0) {
            $last_create_time = self::instance()->where([
                self::F_COMMAND => $command,
            ])->order([self::F_ID => self::V_ORDER_DESC])->value(self::F_CREATE_TIME);
            if (!is_numeric($last_create_time) || time() - $last_create_time > $within_seconds_ignore_this_cmd) {
                $is_insert = true;
            } else {
                $is_insert = false;
            }
        }
        if ($is_insert) {
            //新增
            return self::instance()->insert([
                self::F_COMMAND => $command,
            ]);
        } else {
            return false;
        }
    }

    /**
     * 查看推送任务是否完成
     * @param  [post] [description]
     * @return [type] [description]
     * @author [qinlh] [WeChat QinLinHui0706]
     */
    public static function showStart($id)
    {
        return self::instance()->where("id", $id)->find();
        // return self::instance()->where(['id' => $id, 'start' => 2])->find();
    }

    /**
    * 查询已完成的
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function showTaskRunCompletedStart($ids='') {
        $sql = "SELECT count(id) as num FROM ssc_task WHERE id IN ($ids) AND `start` = 2";
        return self::instance()->query($sql);
    }

    /**
    * 获取进程集是否开始运行
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getTaskRunIsFunction($ids='') {
        $sql = "SELECT count(id) as num FROM ssc_task WHERE id IN ($ids) AND (`start` = 1 OR `start` = 2 OR `start` = 3)";
        return self::instance()->query($sql);
    }

    /**
    * 查询任务是否有正在进行中的或者未完成的任务
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getProjectTaskIsHaveInHand() {
        $sql = "SELECT id FROM ssc_task WHERE `start`=0 OR `start`=1 ORDER BY id ASC LIMIT 1";
        $res = self::instance()->query($sql);
        if($res) {
            return true;
        }
        return false;
    }

    /**
    * 查询异常进行数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function showTaskErrorMsg($ids='') {
        $sql = "SELECT id,`start`,remark FROM ssc_task WHERE id IN ($ids) AND `start` = 3";
        return self::instance()->query($sql);
    }
}
