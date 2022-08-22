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
    public static function calcQuestionAnswer($address='', $answers=[], $times='', $language='zh', $is_relive=0) {
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
                    $score += 20;
                    $qScore = 20;
                }
                $userTicketId = UserTicket::getUserStartTicket($userId);
                $insertUserAnswerDetails[] = [
                    'user_id' => $userId,
                    'user_ticket_id' => $userTicketId,
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
                        'user_ticket_id' => $userTicketId,
                        'is_relive' => 0,
                        'score' => $score,
                        'language' => $language,
                        'consuming' => $times,
                        'date' => date('Y-m-d'),
                        'time' => date("Y-m-d H:i:s"),
                        'up_time' => date("Y-m-d H:i:s")
                    ];
                    $answerRes = Answer::insertAnswerData($insertUserAnswer);
                    if($answerRes) {
                        //开始分配奖励
                        if($userTicketId > 0) { //如果有门票
                            $ticketDetails = UserTicket::getUserTicketDetail($userTicketId);
                            $award_num = self::getAwardNumConfig($ticketDetails['capped'], $num, $is_relive);
                        } else { //如果没有门票
                            $award_num = self::getAwardNumConfig(0, $num, $is_relive);
                        }
                        $awardRes = Award::setTodayUserAwardInfo($userId, $userTicketId, $num, $score, $award_num);
                        if($awardRes) {
                            $isUserBalance = User::setUserCurrencyLocalBalance($address, $award_num, 1, 'h2o');
                            if($isUserBalance) {
                                self::commit();
                                return ['correct_num' => $num, 'score' => $score, 'times' => $times];
                            }
                        }
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

    /**
     * 根据答对题目数量获取奖励
     * // 答对1道题就给70%，答对2到80%；答对3到90%，答对4道95%，答对5道题给100%
     * 完成答题奖励80%，全部答对5道题给100%
     * @author qinlh
     * @since 2022-08-12
     */
    public static function getAwardNumConfig($capped=0, $num=0, $is_relive=0) {
        $award_num = 0;
        if($is_relive) { //如果是复活作答
            if($num == 5) {
                $award_num = $capped * 0.2;
            }
        } else {
            if($capped && $capped > 0) {
                switch ($num) {
                    case '1':
                        $award_num = $capped * 0.8;
                        break;
                    case '2':
                        $award_num = $capped * 0.8;
                        break;
                    case '3':
                        $award_num = $capped * 0.8;
                        break;
                    case '4':
                        $award_num = $capped * 0.8;
                        break;
                    case '5':
                        $award_num = $capped * 1;
                        break;
                    default:
                        $award_num = $capped * 0;
                        break;
                }
            } else {
                switch ($num) {
                    case '1':
                        $award_num = 0.01;
                        break;
                    case '2':
                        $award_num = 0.01;
                        break;
                    case '3':
                        $award_num = 0.01;
                        break;
                    case '4':
                        $award_num = 0.01;
                        break;
                    case '5':
                        $award_num = 0.1;
                        break;
                    default:
                        $award_num = 0;
                        break;
                }
            }
        }
        return $award_num;
    }
}