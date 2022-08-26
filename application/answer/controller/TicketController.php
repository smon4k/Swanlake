<?php
// +----------------------------------------------------------------------
// | 文件说明：票
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-11
// +----------------------------------------------------------------------
namespace app\answer\controller;

use app\api\model\User;
use app\answer\model\FillingRecord;
use app\answer\model\Award;
use app\answer\model\Awardv2;
use app\answer\model\BrowseAward;
use app\answer\model\Ticket;
use app\answer\model\UserTicket;
use think\Request;
use think\Cookie;
use think\Config;
use lib\Filterstring;
use think\Controller;
use RequestService\RequestService;

class TicketController extends BaseController
{

    /**
     * 获取门票数据列表
     * @author qinlh
     * @since 2022-07-28
     */
    public function getTicketList(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        // if ($userId <= 0) {
        //     return $this->as_json('70001', 'Missing parameters');
        // }
        $where = [];
        $where['a.state'] = 1;
        $order = '';
        $result = Ticket::getTicketList($where, $page, $limit, $order);
        return $this->as_json($result);
    }

    /**
     * 获取门票数据详情
     * @author qinlh
     * @since 2022-07-28
     */
    public function getTicketDetail(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $ticketId = $request->request('ticketId', 0, 'intval');
        if ($ticketId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = Ticket::getTicketDetail($ticketId);
        return $this->as_json($result);
    }

    /**
     * 获取用户门票数据详情
     * @author qinlh
     * @since 2022-07-28
     */
    public function getUserTicketDetail(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $ticketId = $request->request('ticketId', 0, 'intval');
        $userTicketId = $request->request('userTicketId', 0, 'intval');
        if ($userTicketId <= 0 || $userId < 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = UserTicket::getUserTicketDetail($userTicketId);
        return $this->as_json($result);
    }

    /**
     * 开始购买门票
     * @author qinlh
     * @since 2022-07-29
     */
    public function startBuyTicket(Request $request) {
        $userId = $request->post('userId', 0, 'intval');
        $ticket_id = $request->post('ticket_id', 0, 'intval');
        $insurance_amount = $request->post('insurance_amount', '', 'trim');
        $type = $request->post('type', 1, 'intval');
        if($userId <= 0 || $ticket_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        // $userInfo = User::getUserAddressInfo($userId);
        $result = UserTicket::startBuyTicket($userId, $ticket_id, $insurance_amount, $type);
        return $this->as_json($result);
    }

    /**
     * 开始赎回门票
     * @author qinlh
     * @since 2022-07-29
     */
    public function startRedemptionTicket(Request $request) {
        $userId = $request->post('userId', 0, 'intval');
        $ticket_id = $request->post('ticket_id', 0, 'intval');
        $user_ticket_id = $request->post('user_ticket_id', 0, 'intval');
        if($userId <= 0 || $ticket_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        // $userInfo = User::getUserAddressInfo($address);
        $result = UserTicket::startRedemptionTicket($userId, $ticket_id, $user_ticket_id);
        return $this->as_json($result);
    }

    /**
     * 获取我的门票
     * @author qinlh
     * @since 2022-07-29
     */
    public function getMyTicketList(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $where = [];
        $order = 'a.is_start desc,a.id desc';
        if($userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        // $userInfo = User::getUserAddressInfo($address);
        $where['a.user_id'] = $userId;
        $result = UserTicket::getMyTicketList($where, $page, $limit, $order, $userId);
        return $this->as_json($result);
    }

    /**
     * 开启门票
     * @author qinlh
     * @since 2022-07-29
     */
    public function startTicket(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        $ticket_id = $request->request('ticket_id', 0, 'intval');
        $user_ticket_id = $request->request('user_ticket_id', 0, 'intval');
        if($userId <= 0 || $user_ticket_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        // $userInfo = User::getUserAddressInfo($address);
        $result = UserTicket::startTicket($userId, $ticket_id, $user_ticket_id);
        return $this->as_json($result);
    }

    /**
     * 查看用户门票收入奖励明细
     * @author qinlh
     * @since 2022-07-30
     */
    public function getUserTicketTodayAward(Request $request) {
        $userId = $request->request('userId', 0, 'intval');
        // $address = $request->request('address', '', 'trim');
        $user_ticket_id = $request->request('user_ticket_id', 0, 'intval');
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $where = [];
        if($userId <= 0 || $user_ticket_id <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        // $userInfo = User::getUserAddressInfo($address);
        $order = 'date desc';
        $where['user_id'] = $userId;
        $where['user_ticket_id'] = $user_ticket_id;
        $result = UserTicket::getUserTicketTodayAward($where, $page, $limit, $order);
        return $this->as_json($result);
    }
}