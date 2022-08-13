<?php
// +----------------------------------------------------------------------
// | 文件说明：票 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2022 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-08-11
// +----------------------------------------------------------------------
namespace app\answer\model;

use think\Model;
use RequestService\RequestService;

class Ticket extends Base
{

     /**
     * [getTicketList] [查询票列表]
     * @param [$where] [查询条件]
     * @return [array] [数组]
     * @author [qinlh] [WeChat QinLinHui0706]
     */
    public static function getTicketList($where=[], $notesId=0, $limit=10)
    {
        try {
            // $limit = 10;
            $data = self::name('a_ticket')
                        ->alias("a")
                        ->where($where)
                        ->field("a.*")
                        ->limit($limit)
                        ->order("a.id asc")
                        ->select();
            if ($data == array()) {
                return json_encode(['code'=>1005,'msg'=>'Query Data Error']);
            }
            if (false == $data) {
                return ['code'=>1003,'msg'=>'Query Error'];
            }
            // p($data->toArray());
            // $base = new BaseModel();
            // $arr = $base->RecursionTree($data,0,"comment_id","leave_pid");
            // p($arr->toArray());
            return ['code'=>1001,'lists'=>$data,'msg'=>'Query Success'];
        } catch (PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
    * [getTicketDetail] [获取门票详情]
    * @param [$where] [查询条件]
    * @return [array] [数组]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getTicketDetail($ticketId=0)
    {
        try {
            if ($ticketId) {
                $data = self::name('a_ticket')->where('id', $ticketId)->find();
            } else {
                $data = self::name('a_ticket')->where('id', 1)->find();
            }
            if ($data && count((array)$data) > 0) {
                return $data->toArray();
            }
            return [];
        } catch (PDOException $e) {
            p($e->getMessage());
            return false;
        }
    }

    /**
    * 获取我的门票列表
    * @author qinlh
    * @since 2022-07-29
    */
    public static function getMyTicketList($where, $page, $limit, $order='id desc', $userId=0)
    {
        if ($limit <= 0) {
            $limit = config('paginate.list_rows');// 获取总条数
        }
        $count = self::name('a_ticket')->alias('a')->join('s_a_ticket b', 'a.ticket_id = b.id')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('a_ticket')
                    ->alias('a')
                    ->join('s_a_ticket b', 'a.ticket_id = b.id')
                    ->field("a.*,b.name")
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 获取用户门票数量
     * @author qinlh
     * @since 2022-07-29
     */
    public static function getUserTicketNum($userId=0)
    {
        if ($userId > 0) {
            $num = self::name('a_ticket')->where('user_id', $userId)->count();
            if ($num) {
                return $num;
            }
        }
        return 0;
    }

    /**
     * 修改出售数量
     * @author qinlh
     * @since 2022-07-30
     */
    public static function setSellNumber($ticket_id=0)
    {
        if ($ticket_id > 0) {
            $incSellRes = self::name('a_ticket')->where('id', $ticket_id)->setInc('sell_number');
            if ($incSellRes) {
                return true;
            }
        }
        return false;
    }

    /**
     * 添加日志操作记录数据
     * @author qinlh
     * @since 2022-07-30
     */
    public static function addTicketLogData($ticket_id=0, $userId='', $user_ticket_id=0, $price=0, $data=[], $type=0, $desc='', $status=1)
    {
        if ($userId > 0 && $ticket_id >= 0 && count((array)$data) > 0 && $type > 0) {
            $insertData = [
                'ticket_id' => $ticket_id,
                'user_ticket_id' => $user_ticket_id,
                'price' => $price,
                'desc' => $desc,
                'type' => $type,
                'user_id' => $userId,
                'data' => json_encode($data),
                'ip' => getRealIp(),
                'hash' => '',
                'time' => date('Y-m-d H:i:s'),
                'status' => $status,
            ];
            $insertId = self::name('a_ticket_log')->insert($insertData);
            if ($insertId >= 0) {
                return true;
            }
        }
        return false;
    }
}
