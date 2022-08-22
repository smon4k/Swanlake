<?php
// +----------------------------------------------------------------------
// | 文件说明：题库-作答记录 Model
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
use app\api\model\User;
use RequestService\RequestService;

class Answer extends Base
{
    /**
     * 写入用户作答记录
     * @author qinlh
     * @since 2022-08-09
     */
    public static function insertAnswerData($insertData=[]) {
        if($insertData) {
            self::name('a_answer')->insert($insertData);
            $insertId = self::name('a_answer')->getLastInsID();
            if($insertId && $insertId > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取用户今日是否作答完成
     * @author qinlh
     * @since 2022-08-09
     */
    public static function getUserTodayIsAnswer($address='') {
        $date = date('Y-m-d');
        $userId = User::getUserAddress($address);
        $userTicketId = UserTicket::getUserStartTicket($userId);
        if($userTicketId >= 0) {
            $data = self::name('a_answer')->where(['user_id' => $userId, 'user_ticket_id' => $userTicketId, 'date'=>$date])->find();
            if($data && count((array)$data) > 0) {
                return 3;
            } else {
                return 1;
            }
        } else {
            return 2;
        }
    }

    /**
     * 获取用户今日排行榜数据
     * @author qinlh
     * @since 2022-08-10
     */
    public static function getUserTodayLeaderboardList($where, $page, $limit, $order='id desc') {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::name('a_answer')->alias('a')->join('s_user b', 'a.user_id=b.id')->where($where)->field('a.*,b.avatar,b.nickname')->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('a_answer')
                        ->alias('a')
                        ->join('s_user b', 'a.user_id=b.id')
                        ->where($where)
                        ->page($page, $limit)
                        ->order('a.time desc,a.score desc')
                        ->field('a.*,b.avatar,b.nickname')
                        ->select()
                        ->toArray();
        if($lists && count((array)$lists) > 0) {
            $userArray = [];
            foreach ($lists as $key => $val) {
                $userArray[$val['user_id']][] = $val;
            }
            // p($newArray);
            $newArray = [];
            foreach ($userArray as $key => $val) {
                $count_score = 0;
                $arr = [];
                foreach ($val as $kk => $vv) {
                    $count_score += $vv['score'];
                    $arr['id'] = $vv['id'];
                    $arr['user_id'] = $vv['user_id'];
                    $arr['language'] = $vv['language'];
                    $arr['consuming'] = $vv['consuming'];
                    $arr['date'] = $vv['date'];
                    $arr['avatar'] = $vv['avatar'];
                    $arr['nickname'] = $vv['nickname'];
                    if(empty($vv['avatar']) || $vv['avatar'] == '') {
                        $arr['avatar'] = "https://h2o-finance-images.s3.amazonaws.com/h2oMedia/default_avatar.png";
                    }
                }
                $arr['score'] = $count_score;
                $newArray[] = $arr;
                // if(empty($val['avatar']) || $val['avatar'] == '') {
                //     $lists[$key]['avatar'] = "https://h2o-finance-images.s3.amazonaws.com/h2oMedia/default_avatar.png";
                // }
            }
            $ages = array_column($newArray, 'score');
            array_multisort($ages, SORT_DESC, $newArray);
            return ['count'=>$count,'allpage'=>$allpage,'lists'=>$newArray];
        }
    }
}