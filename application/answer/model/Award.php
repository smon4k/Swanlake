<?php
// +----------------------------------------------------------------------
// | 文件说明：奖励 市场 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2022 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-12
// +----------------------------------------------------------------------
namespace app\answer\model;

use think\Model;
use RequestService\RequestService;

class Award extends Base
{

    /**
     * 写入作答奖励数据
     * @author qinlh
     * @since 2022-08-12
     */
    public static function setTodayUserAwardInfo($userId=0, $userTicketId=0, $question_num=0, $score=0, $award_num=0)
    {
        if ($userId > 0) {
            $timeRes = self::getDateTime();
            $res = self::name('a_award')->where(['date'=>$timeRes, 'user_id' => $userId, 'user_ticket_id' => $userTicketId])->find();
            if($res) {
                $isRes = self::name('a_award')->where(['date'=>$timeRes, 'user_id' => $userId, 'user_ticket_id' => $userTicketId])->update(['question_num' => $question_num, 'score' => $score, 'award_num' => $award_num]);
            } else {
                $insertData = [
                    'user_id' => $userId,
                    'user_ticket_id' => $userTicketId,
                    'date' => $timeRes,
                    'question_num' => $question_num,
                    'score' => $score,
                    'award_num' => $award_num,
                    'status' => 1,
                    'up_time' => date('Y-m-d H:i:s')
                ];
                $isRes = self::name('a_award')->insertGetId($insertData);
            }
            if ($isRes && $isRes !== false) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * 查询今天用户记录是否存在
     * @author qinlh
     * @since 2022-04-30
     */
    public static function getTodayUserAwardInfo($userId=0, $userTicketId=0)
    {
        if ($userId > 0 && $userTicketId > 0) {
            $timeRes = self::getDateTime();
            $sql = "SELECT * FROM s_a_award WHERE `date` = '$timeRes' AND `user_id` = '$userId' AND ’user_ticket_id‘ = '$userTicketId' LIMIT 1";
            $data = self::query($sql);
            if ($data && count($data) > 0) {
                return $data[0];
            } else {
                $data = self::insertTodayData($userId, $userTicketId);
            }
            return $data;
        }
    }

    /**
     * 获取用户每天的奖励数据
     * @author qinlh
     * @since 2022-04-30
     */
    public static function getUserAwardList($where, $page, $limit=1000, $order='')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        // $begin = ($page - 1) * $limit;
        // $sql = "SELECT
        //             a.*,
        //             b.nickname,
        //             b.avatar
        //         FROM
        //             (
        //                 ( SELECT id, `user_id`, date, publish_images_num, publish_video_num, browse_num, 0 AS be_browse_num, '' AS up_time FROM m_award ) UNION ALL
        //                 ( SELECT id, `user_id`, date, 0 AS publish_images_num, 0 AS publish_video_num, 0 AS browse_num, num as be_browse_num,up_time FROM m_browse_award )
        //             ) AS a
        //         INNER JOIN m_user AS b ON a.user_id=b.id ORDER BY a.date desc";
        // $count = count(self::query($sql));
        $count = self::where($where)->alias('a')->join('m_user b', 'a.user_id=b.id')->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::where($where)->page($page, $limit)->alias('a')->join('m_user b', 'a.user_id=b.id')->field('a.*, b.nickname,b.avatar')->order($order)->select()->toArray();
        // $sql .= " LIMIT {$begin},{$limit}";
        // $lists = self::query($sql);
        if (!$lists) {
            return false;
        }
        $start_award_date = strtotime("2022-06-27 00:00:00");
        foreach ($lists as $key => $val) {
            $publish_images_award = $val['publish_images_num'] > 0 ? (float)$val['publish_images_num'] * (float)$val['publish_images_amount'] : 0;
            $publish_video_award = $val['publish_video_num'] > 0 ? (float)$val['publish_video_num'] * (float)$val['publish_video_amount'] : 0;
            $be_browse_award = $val['be_browse_num'] > 0 ?(float) $val['be_browse_num'] * (float)$val['be_browse_amount']: 0;
            $browse_award = $val['browse_num'] > 0 ? $val['browse_num'] * $val['browse_amount'] : 0;
            // if(time() > $start_award_date) {
            //     $browse_award = $val['browse_num'] > 0 ? $val['browse_num'] * config('award_config.browse_works') * 0.7 : 0;
            // } else {
            //     $browse_award = $val['browse_num'] > 0 ? $val['browse_num'] * config('award_config.browse_works') : 0;
            // }
            $lists[$key]['publish_images_award'] = $publish_images_award;
            $lists[$key]['publish_video_award'] = $publish_video_award;
            $lists[$key]['browse_award'] = $browse_award;
            $lists[$key]['be_browse_num'] = $val['be_browse_num'];
            $lists[$key]['be_browse_award'] = $be_browse_award;
            $lists[$key]['count_award'] = (float)$publish_images_award + (float)$publish_video_award + (float)$browse_award + (float)$be_browse_award;
            if (empty($val['avatar']) || $val['avatar'] == '') {
                $lists[$key]['avatar'] = "https://h2o-finance-images.s3.amazonaws.com/h2oMedia/default_avatar.png";
            }
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 获取挖矿排行 单日已核算完的奖励数据
     * @author qinlh
     * @since 2022-05-01
     */
    public static function getMiningRankingAwardList($where, $page, $limit=1000)
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::where($where)->page($page, $limit)->select()->toArray();
        if (!$lists) {
            return false;
        }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 记录今天的数据记录
     * @author qinlh
     * @since 2022-04-30
     */
    public static function insertTodayData($userId=0, $userTicketId=0)
    {
        if ($userId > 0) {
            $date = self::getDateTime();
            $insertData = [
                'user_id' => $userId,
                'user_ticket_id' => $user_ticket_id,
                'date' => $date,
                'question_num' => 0,
                'score' => 0,
                'award_num' => 0,
                'status' => 0,
                'up_time' => date('Y-m-d H:i:s')
            ];
            $insertId = self::insertGetId($insertData);
            $insertData['id'] = $insertId;
            return $insertData;
        }
        return false;
    }

    /**
     * 判断当前时间是否查过中午12点
     * @author qinlh
     * @since 2022-04-30
     */
    public static function getIsExceedTimes()
    {
        $h = date('H');
        if ($h >= self::H_TIME) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取时间 开始与结束时间
     * @author qinlh
     * @since 2022-04-30
     */
    public static function getStartEndTime()
    {
        $isTime = self::getIsExceedTimes();
        // $hTime = "10:00:00";
        if ($isTime) {
            $start_time = date("Y-m-d " . self::H_TIME . ":00:00", strtotime("-1 day"));
            $end_time = date('Y-m-d ' . self::H_TIME . ":00:00");
        } else {
            $start_time = date('Y-m-d ' . self::H_TIME . ":00:00");
            $end_time = date("Y-m-d " . self::H_TIME . ":00:00", strtotime("+1 day"));
        }
        return ['start_time'=>$start_time, 'end_time'=>$end_time];
    }

    /**
     * 获取时间 今天时间 12点为折点
     * @author qinlh
     * @since 2022-04-30
     */
    public static function getDateTime()
    {
        // $timeRes = self::getIsExceedTimes();
        $date = null;
        // if ($timeRes) { //12点之前
        //     $date = date("Y-m-d", strtotime("-1 day"));
        // } else { //超过12点
        $date = date("Y-m-d");
        // }
        return $date;
    }

    /**
     * 获取用户今日奖励总数
     * @author qinlh
     * @since 2022-06-28
     */
    public static function getUserDayCountAwardAmount($userId=0)
    {
        $count_award = 0;
        if ($userId > 0) {
            $timeRes = self::getDateTime();
            $data = self::where(['user_id'=>$userId, 'date'=>$timeRes])->find();
            if ($data && count((array)$data) > 0) {
                $publish_images_award = (float)$data['publish_images_num'] * (float)$data['publish_images_amount'];
                $publish_video_award = (float)$data['publish_video_num'] * (float)$data['publish_video_amount'];
                $browse_num_award = (float)$data['browse_num'] * (float)$data['browse_amount'];
                $be_browse_award = (float)$data['be_browse_num'] * (float)$data['be_browse_amount'];
                $count_award = (float)$publish_images_award + (float)$publish_video_award + (float)$browse_num_award + (float)$be_browse_award;
            }
        }
        return $count_award;
    }

    /**
     * 获取所有用户每天的奖励数据
     * @author qinlh
     * @since 2022-07-25
     */
    public static function getAllTodayAwardList($where, $page, $limit=1000, $order='')
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::name('a_award')->where($where)->alias('a')->join('s_user b', 'a.user_id=b.id')->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('a_award')->where($where)->page($page, $limit)->alias('a')->join('s_user b', 'a.user_id=b.id')->field('a.*, b.nickname,b.avatar')->order($order)->select()->toArray();
        if (!$lists) {
            return [];
        }
        foreach ($lists as $key => $val) {
            if (empty($val['avatar']) || $val['avatar'] == '') {
                $lists[$key]['avatar'] = "https://h2o-finance-images.s3.amazonaws.com/h2oMedia/default_avatar.png";
            }
        }
        // $num_count = array_column($lists, 'count_award');//返回数组中指定的一列
        // array_multisort($num_count, SORT_DESC, $lists);//对多个数组或多维数组进行排序
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }
}
