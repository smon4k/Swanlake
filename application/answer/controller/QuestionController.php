<?php

// +----------------------------------------------------------------------
// | 文件说明：题库控制器
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-08
// +----------------------------------------------------------------------

namespace app\answer\controller;

use think\Request;
use think\Controller;
use app\answer\model\Question;
use app\answer\model\Answer;
use app\answer\model\AnswerRecord;
use app\api\model\User;
use RequestService\RequestService;

class QuestionController extends BaseController
{
    /**
     * 随机获取用户题目
     * @author qinlh
     * @since 2022-08-08
     */
    public function getUserQuestionList(Request $request)
    {
        $userId = $request->request('userId', 0, 'intval');
        $language = $request->request('language', '', 'trim');
        if ($userId <= 0 || $language == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        // $userId = User::getUserAddress($address);
        $questionList = Question::getUserQuestionList($language);
        $answeredArr = AnswerRecord::getUserAnsweredQuestion($userId); //用户已作答题目id
        // p($answeredArr);
        $newQuestionList = [];
        foreach ($questionList as $key => $val) {
            if ($answeredArr && count((array)$answeredArr) > 0) {
                $isAnswer = false; //该道题是否已做
                foreach ($answeredArr as $v) {
                    if ((int)$val['id'] == (int)$v) {
                        $isAnswer = true;
                    }
                }
                if (!$isAnswer) {
                    $newQuestionList[$val['id']] = $val;
                }
            } else {
                $newQuestionList[$val['id']] = $val;
            }
        }
        if(empty($newQuestionList) || count((array)$newQuestionList) < 5) {
            $newQuestionList = $questionList;
        }
        // p($newQuestionList);
        $temp = array_rand($newQuestionList, 5);
        // p($temp);
        $newQuestionArray = [];
        foreach($temp as $val) {
            $questionArr = $newQuestionList[$val];
            $questionArr['option'] = json_decode($questionArr['option'], true);
            $newQuestionArray[] = $questionArr;
        }
        // foreach ($questionList as $key => $val) {
        //     $questionList[$key]['option'] = json_decode($val['option'], true);
        // }
        return $this->as_json($newQuestionArray);
    }

    /**
     * 计算用户题目
     * @author qinlh
     * @since 2022-08-09
     */
    public function calcQuestionAnswer(Request $request)
    {
        $userId = $request->post('userId', 0, 'intval');
        // $address = $request->post('address', '', 'trim');
        $answers = $request->post('answers/a', '', []);
        $times = $request->post('times', '', 'trim');
        $language = $request->post('language', 'zh', 'trim');
        $is_relive = $request->post('is_relive', 0, 'intval');
        // if ($address == '' || count((array)$answers) <= 0 || $language == '') {
        //     return $this->as_json('70001', 'Missing parameters');
        // }
        $result = Question::calcQuestionAnswer($userId, $answers, $times, $language, $is_relive);
        return $this->as_json($result);
    }

    /**
     * 购买复活机会重新作答
     * @author qinlh
     * @since 2022-08-22
     */
    public function buyResurrection(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        if ($userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Answer::buyResurrection($userId);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, '购买失败');
        }
    }

    /**
     * 获取用户今日是否已做过题目
     * @author qinlh
     * @since 2022-08-09
     */
    public function getUserTodayIsAnswer(Request $request)
    {
        $address = $request->request('address', '', 'trim');
        $userId = $request->request('userId', 0, 'intval');
        if ($address == '' && $userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Answer::getUserTodayIsAnswer($userId);
        return $this->as_json($result);
    }
    
    /**
     * 获取总的排行榜数据
     * @author qinlh
     * @since 2022-08-10
     */
    public function getCountRankingData(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $page = $request->request('page', 1, 'intval');
        $where = [];
        if ($userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Answer::getCountRankingData($where);
        return $this->as_json($result);
    }

    /**
     * 获取排行榜今日数据列表
     * @author qinlh
     * @since 2022-08-10
     */
    public function getUserTodayLeaderboardList(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $times = $request->request('times', '', 'trim');
        $where = [];
        $whereTime = "";
        // $where['a.date'] = date('Y-m-d');
        if ($userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Answer::getUserTodayLeaderboardList($where, $times, $page, $limit);
        return $this->as_json($result);
    }
}
