<?php
// +----------------------------------------------------------------------
// | 文件说明：合约-定时任务 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-06
// +----------------------------------------------------------------------
namespace app\api\model;

use think\Model;
use think\Config;
use RequestService\RequestService;

class TaskContract extends Base
{

    /**
    * 处理任务
    * @param int $id 执行的id
    * @return bool
    */
    public static function deal($id)
    {
        // $item = array();
        //处理数据
        if ($id <= 0) { //查询状态为未执行或者执行失败次数小于10次 并且大于上次执行失败3分钟后的数据
            $sql = 'SELECT
                        id,txid,command,`number`
                    FROM
                        s_task_contract
                    WHERE
                        ( `status` = 0 OR `status` = 3 ) 
                        AND `number` < 10 
                        AND now() > SUBDATE( `create_time`, INTERVAL - 5 MINUTE ) -- 创建成功以后 5 分钟以后执行
                        AND IF ( `end_time` IS NULL, 0 = 0, now() > SUBDATE( `end_time`, INTERVAL - 3 MINUTE ))  -- 3分钟执行一次
                    ORDER BY
                        `id` ASC 
                    LIMIT 1';
            // $item = self::where(['status' => [['eq', 0],['eq', 3], 'or'], 'number' => ['<', 10]])->order(['id' => 'asc'])->find()->toArray();
            $sqlRes = self::query($sql);
            if ($sqlRes && count($sqlRes) > 0) {
                $item = $sqlRes[0];
            } else {
                return true;
            }
            // p($item);
        } else {
            $sql = "SELECT id,txid,command,`number` FROM s_task_contract WHERE id = $id LIMIT 1";
            $sqlRes = self::query($sql);
            if ($sqlRes && count($sqlRes) > 0) {
                $item = $sqlRes[0];
            } else {
                return true;
            }
        }
        // p($item);
        //设置正在执行
        self::where([
            'id' => $item['id'],
        ])->setField([
            'start_time' => date('Y-m-d H:i:s'),
            'remark' => '',
            'status' => 1,
        ]);
        //执行
        log_info('task-start-' . $item['id']);
        try {
            $data = ['hash' => $item['txid']];
            $response_arr = RequestService::doJsonCurlPost(Config::get('www_reptile_contract')."/v1.0/get_transaction", json_encode($data));
            $returnArray = json_decode($response_arr, true);
            if ($returnArray && $returnArray['status'] && $returnArray['status'] == 1) { //如何合约已执行完成
                // p($returnArray);
                $EvalResult = eval('return '.$item['command'].';');
                if ($EvalResult && $EvalResult['code'] && $EvalResult['code'] == 1) {
                    //设置执行的结束时
                    // self::instance()->query('SET AUTOCOMMIT=0;');
                    self::where([
                        'id' => $item['id'],
                    ])->setField([
                        'remark' => $EvalResult['message'] === "ok" ? '异步执行成功' : $EvalResult['message'],
                        'end_time' => date("Y-m-d H:i:s"),
                        'status' => 2,
                    ]);
                } else {
                    self::where([
                        'id' => $item['id'],
                    ])->setField([
                        'remark' => $EvalResult['message'],
                        'end_time' => date("Y-m-d H:i:s"),
                        'status' => 3,
                        'number' => $item['number'] + 1
                    ]);
                }
            } elseif ($returnArray && $returnArray['status'] && $returnArray['status'] == 0) {
                self::where([
                    'id' => $item['id'],
                ])->setField([
                    'remark' => '交易被 EVM 还原',
                    'end_time' => date("Y-m-d H:i:s"),
                    'status' => 3,
                    'number' => 10
                ]);
            } else {
                self::where([
                    'id' => $item['id'],
                ])->setField([
                    'remark' => '合约未执行完毕',
                    'end_time' => date("Y-m-d H:i:s"),
                    'status' => 3,
                    'number' => $item['number'] + 1
                ]);
            }
        } catch (Exception $e) {
            $error_msg = json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE);
            self::where([
                'id' => $item['id'],
            ])->setField([
                'remark' => $error_msg,
                'end_time' => date("Y-m-d H:i:s"),
                'status' => 3,
                'number' => 10
            ]);
            log_info('task-error', $error_msg);
            if ($id > 0) {
                echo_info('task-error', $error_msg);
            }
        }
        //结束
        log_info('task-end-' . $item['id']);
        return true;
    }

    /**
     * 添加任务数据
     * @author qinlh
     * @since 2022-02-18
     */
    public static function addTaskData($address='', $hash='', $command='', $desc='')
    {
        if ($address !== '' && $command !== '') {
            $insertData = [
                'address' => $address,
                'txid' => $hash,
                'command' => $command,
                'desc' => $desc,
                'create_time' => date('Y-m-d H:i:s')
            ];
            $insertId = self::insertGetId($insertData);
            if ($insertId >= 0) {
                return $insertId;
            }
        }
        return 0;
    }

    /**
     * 查询hash值是否执行成功
     * 如果没有执行成功 设置为成功
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getHashSetIsSuccess($hash='')
    {
        if (!empty($hash)) {
            $res = self::where('txid', $hash)->find();
            if ($res && $res['status'] !== 2) { //如果没有执行成功的话
                self::where('txid', $hash)->setField(['status' => 2, 'remark' => '同步执行成功']);
                return true;
            }
        }
        return false;
    }
    
    /**
     * 查询任务ID是否执行成功
     * 如果没有执行成功 设置为成功
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getTaskIdIsSuccess($task_id='')
    {
        if ($task_id > 0) {
            $res = self::where('id', $task_id)->find();
            if ($res && count((array)$res) > 0) {
                // return $res;
                $data = json_decode($res['data'], true);
                return ['code' => 1, 'status' => $res['status'], 'data' => $data];
            }
        }
        return ['code' => 0, 'data'=>[]];
    }

    /**
     * 合约同步执行失败
     * 修改异步任务为执行失败 不再进行执行
     * @author qinlh
     * @since 2022-02-18
     */
    public static function setHashIsError($hash='')
    {
        if (!empty($hash)) {
            $res = self::where('txid', $hash)->find();
            if ($res) { //如果没有执行成功的话
                self::where('txid', $hash)->setField(['status' => 3, 'number' => 10, 'remark' => '同步执行失败']);
                return true;
            }
        }
        return false;
    }
}
