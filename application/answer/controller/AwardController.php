<?php
// +----------------------------------------------------------------------
// | 文件说明：作答奖励
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-12
// +----------------------------------------------------------------------
namespace app\answer\controller;

use app\answer\model\User;
use app\answer\model\Award;
use app\answer\model\UserTicket;
use think\Request;
use think\Cookie;
use think\Config;
use lib\Filterstring;
use think\Controller;
use RequestService\RequestService;

class AwardController extends BaseController
{
    /**
     * 获取所有用户每天的奖励数据
     * @author qinlh
     * @since 2022-08-12
     */
    public function getMiningRankingAwardList(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 10000, 'intval');
        $where = [];
        $order = "a.date desc,a.id desc";
        $result = Award::getAllTodayAwardList($where, $page, $limit, $order);
        // p($result);
        $newResult = [];
        $newSortResultArr = [];
        if ($result) {
            foreach ($result['lists'] as $key => $info) {
                $newResult[$info['date']]['list'][] = $info;
            }
            // p($newResult);
            foreach ($newResult as $key => $val) {
                // 根据count_award 从大到小排序
                $ages = array_column($val['list'], 'award_num');
                array_multisort($ages, SORT_DESC, $val['list']);
                // $newSortResultArr[$key]['list'] = $val['list'];
                $count_amount = 0;
                $userArray = [];
                $userTicketArray = [];
                foreach ($val['list'] as $k => $v) {
                    $count_amount += $v['award_num'];
                    $userArray[$v['user_id']] = $v['user_id'];
                    $userTicketArray[$v['user_id']][] = $v;
                    $newSortResultArr[$key]['list'][$v['user_id']]['nickname'] = $v['nickname'];
                    $newSortResultArr[$key]['list'][$v['user_id']]['avatar'] = $v['avatar'];
                    $newSortResultArr[$key]['list'][$v['user_id']]['count_award'] = 0;
                }

                $browse_num = 0;
                // p($userTicketArray);
                foreach ($userTicketArray as $kk => $vv) {
                    // $userTicketId = UserTicket::getUserStartTicket($kk); //获取用户门票id
                    // $ticketDetail = UserTicket::getUserTicketDetail($userTicketId);
                    $countAward = 0;
                    foreach ($vv as $kkk => $vvv) {
                        $countAward += (float)$vvv['award_num'];
                    }
                    $newSortResultArr[$key]['list'][$kk]['count_award'] = $countAward;
                }
                // p($userTicketArray);
                $newSortResultArr[$key]['count_amount'] = $count_amount;
                $newSortResultArr[$key]['number_user'] = count($userArray);
            }
            // p($newSortResultArr);
            return $this->as_json(['count'=>$result['count'],'allpage'=>$result['allpage'],'lists'=>$newSortResultArr]);
        } else {
            return $this->as_json([]);
        }
    }
}