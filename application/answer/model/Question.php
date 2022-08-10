<?php
// +----------------------------------------------------------------------
// | 文件说明：题库Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-08
// +----------------------------------------------------------------------
namespace app\answer\model;

use think\Model;
use think\Config;
use app\api\model\User;
use RequestService\RequestService;

class Question extends Base
{
    /**
     * 获取题库数据
     * @author qinlh
     * @since 2022-08-08
     */
    public static function getUserQuestionList($language='zh', $limit = 10000) {
        $table = "a_question_{$language}";
        $data = self::name($table)->where('state', 1)->order('id desc')->limit($limit)->field('id,title,option,type,state')->select()->toArray();
        if($data && count((array)$data) > 0) {
            return $data;
        }
        return [];
    }

    /**
     * 获取指定题目答案
     * @author qinlh
     * @since 2022-08-09
     */
    public static function getQuestionAnswer($language='zh', $answers=[]) {
        $table = "a_question_{$language}";
        if(!empty($answers)) {
            $where['id'] = ['in', $answers];
            $data = self::name($table)->where($where)->field('id, answer')->select()->toArray();
            if($data && count((array)$data) > 0) {
                $newArray = [];
                foreach ($data as $key => $val) {
                    $newArray[$val['id']] = json_decode($val['answer'], true);;
                }
                return $newArray;
            }
        }
        return [];
    }

    /**
     * 计算用户题目答案分数
     * @author qinlh
     * @since 2022-08-09
     */
    public static function calcQuestionAnswer($address='', $answers=[], $times='', $language='zh') {
        self::startTrans();
        try {
            $userId = User::getUserAddress($address);
            $newAnswer = [];//用户选择的答案
            $questionIds = [];//题目id集
            foreach ($answers as $key => $val) {
                $newAnswer[$val['id']] = $val['answer'];
                $questionIds[] = $val['id'];
            }
            $questionAnswerList = self::getQuestionAnswer($language, $questionIds); //获取答案
            $num = 0; //答对题目数量
            $score = 0; //最后所得分数
            $insertUserAnswerDetails = [];//用户作答明细表
            $insertUserAnswer = [];//用户作答记录表
            foreach ($newAnswer as $questionId => $val) {
                $qScore = 0;
                if(!array_diff($val, $questionAnswerList[$questionId]) && !array_diff($questionAnswerList[$questionId], $val)) {
                    $num ++;
                    $score += 10;
                    $qScore = 10;
                }
                $insertUserAnswerDetails[] = [
                    'user_id' => $userId,
                    'question_id' => $questionId,
                    'answer' => json_encode($val),
                    'score' => $qScore,
                    'language' => $language,
                    'date' => date('Y-m-d'),
                    'time' => date('Y-m-d H:i:s')
                ];
            }
            //批量写入用户作答记录明细表数据
            if($insertUserAnswerDetails && count((array)$insertUserAnswerDetails) > 0) {
                $rowRes = AnswerRecord::insertAllAnswerRecordData($insertUserAnswerDetails);
                if($rowRes) {
                    $insertUserAnswer = [
                        'user_id' => $userId,
                        'score' => $score,
                        'language' => $language,
                        'consuming' => $times,
                        'date' => date('Y-m-d'),
                        'time' => date("Y-m-d H:i:s")
                    ];
                    $answerRes = Answer::insertAnswerData($insertUserAnswer);
                    if($answerRes) {
                        self::commit();
                        return ['correct_num' => $num, 'score' => $score, 'times' => $times];
                    }
                }
            }
            self::rollback();
            return false;
        } catch (PDOException $e) {
            self::rollback();
            return false;
        }
    }
}