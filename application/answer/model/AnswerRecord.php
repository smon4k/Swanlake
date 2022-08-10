<?php
// +----------------------------------------------------------------------
// | 文件说明：题库-作答明细记录 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-09
// +----------------------------------------------------------------------
namespace app\answer\model;

use think\Model;
use think\Config;
use RequestService\RequestService;

class AnswerRecord extends Base
{
    /**
     * 批量插入用户作答明细记录
     * @author qinlh
     * @since 2022-08-09
     */
    public static function insertAllAnswerRecordData($insertData=[]) {
        if($insertData) {
            $row = self::name('a_answer_record')->insertAll($insertData);
            if($row) {
                return $row;
            }
        }
        return 0;
    }

    /**
     * 获取用户已作答题目
     * @author qinlh
     * @since 2022-08-09
     */
    public static function getUserAnsweredQuestion($user_id=0) {
        if($user_id > 0) {
            $data = self::name('a_answer_record')->where('user_id', $user_id)->group('question_id')->field('question_id')->select()->toArray();
            if($data && count((array)$data) > 0) {
                $questionArray = array_column($data, 'question_id');
                return $questionArray;
            }
        }
        return [];
    }
}