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
    public static function calcQuestionAnswer($userId=0, $answers=[], $times='', $language='zh', $is_relive=0) {
        self::startTrans();
        try {
            // $userId = User::getUserAddress($address);
            $userInfo = User::getUserInfoOne($userId);
            $userTicketId = UserTicket::getUserStartTicket($userId);
            $newAnswer = [];//用户选择的答案
            $questionIds = [];//题目id集
            // if(!$answers || count((array)$answers) < 0) {
            //     return [
            //         'correct_num' => 0,
            //         'score' => 0, 
            //         'times' => $times, 
            //         'is_possible_resurrection' => 0,
            //         'consumeNumber' => 0,
            //     ];
            // }
            foreach ($answers as $key => $val) {
                $newAnswer[$val['id']] = $val['answer'];
                $questionIds[] = $val['id'];
            }
            // p($newAnswer);
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
            // p($insertUserAnswerDetails);
            //批量写入用户作答记录明细表数据
            if ($insertUserAnswerDetails && count((array)$insertUserAnswerDetails) > 0) {
                $rowRes = AnswerRecord::insertAllAnswerRecordData($insertUserAnswerDetails);
            } else { //一道题没作答 不记录答题记录
                $rowRes = true;
            }
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
                    $is_possible_resurrection = 0; //是否可以复活 有门票才可以复活
                    $consumeNumber = 0; //复活消耗Token数量
                    if($userTicketId > 0) { //如果有门票
                        $is_possible_resurrection = 1;
                        $ticketDetails = UserTicket::getUserTicketDetail($userTicketId);
                        // $award_num = self::getAwardNumConfig($ticketDetails['capped'], $num, $is_relive);
                        $awardConfig = self::getAwardNumConfig($ticketDetails['capped'], $num, $is_relive, $userTicketId);
                        $consumeNumber = (float)$ticketDetails['capped'] * (float)config('award_config.resurrection'); //获取复活消耗Token数量 5%
                    } else { //如果没有门票
                        $ticketDetails = UserTicket::getUserTicketDetail(0);
                        $awardConfig = self::getAwardNumConfig($ticketDetails['capped'], $num, $is_relive, $userTicketId);
                    }
                    $awardRes = Award::setTodayUserAwardInfo($userId, $userTicketId, $num, $score, $awardConfig['award_num']);
                    if($awardRes) {
                        $isUserBalance = User::setUserIdCurrencyLocalBalance($userId, $awardConfig['award_num'], 1, 'h2o');
                        if($isUserBalance) {
                            self::commit();
                            return [
                                'correct_num' => $num,
                                'score' => $score, 
                                'times' => $times, 
                                'is_possible_resurrection' => $is_possible_resurrection,
                                'consumeNumber' => $consumeNumber,
                                'capped_num' => (float)$ticketDetails['capped'], //总的门票名义奖励值 分配奖励总数
                                'award_num' => $awardConfig['award_num'], //分配奖励数量
                                'award_rate' => $awardConfig['award_rate'], //分配奖励比例
                            ];
                        }
                    }
                }
            }
            self::rollback();
            return false;
        } catch (PDOException $e) {
            p($e);
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
    public static function getAwardNumConfig($capped=0, $num=0, $is_relive=0, $userTicketId=0) {
        $award_num = 0;
        $award_rate = 0;
        if($is_relive) { //如果是复活作答
            if($num == 5) {
                $award_num = config('award_config.resurrection_award');
                $award_rate = config('award_config.resurrection_award') * 100;
            }
        } else {
            if($capped && $capped > 0 && $userTicketId > 0) {
                switch ($num) {
                    case '1':
                        $award_num = $capped * config('award_config.award_completed');
                        $award_rate = config('award_config.award_completed') * 100;
                        break;
                    case '2':
                        $award_num = $capped * config('award_config.award_completed');
                        $award_rate = config('award_config.award_completed') * 100;
                        break;
                    case '3':
                        $award_num = $capped * config('award_config.award_completed');
                        $award_rate = config('award_config.award_completed') * 100;
                        break;
                    case '4':
                        $award_num = $capped * config('award_config.award_completed');
                        $award_rate = config('award_config.award_completed') * 100;
                        break;
                    case '5':
                        $award_num = $capped * config('award_config.all_award_correct');
                        $award_rate = config('award_config.all_award_correct') * 100;
                        break;
                    default:
                        $award_num = $capped * 0;
                        break;
                }
            } else {
                switch ($num) {
                    case '1':
                        $award_num = $capped * config('award_config.no_ticket_award_completed');
                        $award_rate = config('award_config.no_ticket_award_completed') * 100;
                        break;
                    case '2':
                        $award_num = $capped * config('award_config.no_ticket_award_completed');
                        $award_rate = config('award_config.no_ticket_award_completed') * 100;
                        break;
                    case '3':
                        $award_num = $capped * config('award_config.no_ticket_award_completed');
                        $award_rate = config('award_config.no_ticket_award_completed') * 100;
                        break;
                    case '4':
                        $award_num = $capped * config('award_config.no_ticket_award_completed');
                        $award_rate = config('award_config.no_ticket_award_completed') * 100;
                        break;
                    case '5':
                        $award_num = $capped * config('award_config.all_award_correct');
                        $award_rate = config('award_config.all_award_correct') * 100;
                        break;
                    default:
                        $award_num = 0;
                        $award_rate = 0;
                        break;
                }
            }
        }
        return ['award_num' => $award_num, 'award_rate' => $award_rate];
    }
}