<?php
// +----------------------------------------------------------------------
// | 文件说明：用户门票 Model
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
use app\api\model\User AS UserV2;
use RequestService\RequestService;

class UserTicket extends Base
{

    /**
     * 开始购买门票
     * @param [$address 钱包地址]
     * @param [$ticket_id 门票id]
     * @param [$insurance_amount 保险金]
     * @param [$type 1：购买 2：赎回]
     * @author qinlh
     * @since 2022-07-28
     */
    public static function startBuyTicket($userId=0, $ticket_id=0, $insurance_amount=0, $type=0) {
        if ($userId > 0 && $ticket_id > 0 && $type > 0) {
            $userInfo = UserV2::getUserInfoOne($userId);
            $ticketInfo = Ticket::getTicketDetail($ticket_id);
            // p($userId);
            $buyPrice = (float)$ticketInfo['new_price'];
            self::startTrans();
            try {
                $userTicketNum = self::getUserTicketNum($userId);
                $insertData  = [
                  'user_id' => $userId,
                  'ticket_id' => $ticket_id,
                  'insurance_amount' => $insurance_amount,
                  'buy_price' => $buyPrice,
                  'time' => date('Y-m-d H:i:s'),
                  'state' => 1,
                  'is_start' => $userTicketNum <= 0 ? 1 : 0,
                ];
                self::name('a_user_ticket')->insert($insertData);
                $insertId = self::name('a_user_ticket')->getLastInsID();
                if($insertId) {
                    if($insurance_amount > 0) {
                      $clinchPrice = $buyPrice + ($buyPrice * ((float)$insurance_amount / 100)); //计算购买USDT数量
                    } else {
                      $clinchPrice = $buyPrice;
                    }
                    // $params = [
                    //     'address' => $userInfo['address'],
                    //     'amount' => $buyPrice,
                    //     'type' => 2,
                    // ];
                    if(isset($userInfo['address']) && $userInfo['address'] !== '') {
                      $dataArr = UserV2::setUserLocalBalance($userInfo['address'], $clinchPrice, 2, false);
                    } else {
                      $dataArr = true;
                    }
                    // p($dataArr);
                    // $dataArr = json_decode($response_string, true);
                    if($dataArr) {
                      $incSellRes = Ticket::setSellNumber($ticket_id);
                      if($incSellRes) {
                        $logArray = [
                          'user_id' => $userId,
                          'address' => isset($userInfo['address']) ? $userInfo['address'] : '',
                          'ticket_id' => $ticket_id,
                          'insurance_amount' => $insurance_amount,
                          'is_start' => $userTicketNum <= 0 ? 1 : 0,
                          'buy_price' => $buyPrice,
                          'price' => $clinchPrice,
                          'type' => 1,
                        ];
                        @Ticket::addTicketLogData($ticket_id, $userId, $insertId, $buyPrice, $logArray, 1, "购买门票");
                        self::commit();
                        return true;
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
        return false;
    }

     /**
     * 开始赎回门票
     * @param [$address 钱包地址]
     * @param [$ticket_id 门票id]
     * @param [$user_ticket_id 用户门票ID]
     * @author qinlh
     * @since 2022-07-30
     */
    public static function startRedemptionTicket($userId=0, $ticket_id=0, $user_ticket_id=0) {
      if ($userId > 0 && $ticket_id > 0 && $user_ticket_id > 0) {
          $userInfo = UserV2::getUserInfoOne($userId);
          // p($userId);
          self::startTrans();
          $userTicketInfo = self::getUserTicketDetail($user_ticket_id);
          if($userTicketInfo['insurance_amount'] <= 0) {
            return false;
          }
          try {
              if($userTicketInfo['insurance_amount'] > 0) {
                $redemptionPrice = (float)$userTicketInfo['buy_price'] * ((float)$userTicketInfo['insurance_amount'] * 10 / 100); //计算赎回USDT数量
              }
              $dataArr = UserV2::setUserLocalBalance($userInfo['address'], $redemptionPrice, 1, false);
              // $dataArr = json_decode($response_string, true);
              if($dataArr) {
                $res = self::name('a_user_ticket')->where('id', $user_ticket_id)->update(['is_start' => 0, 'is_ransom' => 2]);
                if($res) {
                  $logArray = [
                    'user_id' => $userId,
                    'address' => $userInfo['address'],
                    'ticket_id' => $ticket_id,
                    'insurance_amount' => $userTicketInfo['insurance_amount'],
                    'is_start' => 0,
                    'price' => $redemptionPrice,
                    'type' => 2,
                  ];
                  $isLog = Ticket::addTicketLogData($ticket_id, $userId, $user_ticket_id, $redemptionPrice, $logArray, 2, "赎回门票");
                  if($isLog) {
                    self::commit();
                    return true;
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
      return false;
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
        $count = self::name('a_user_ticket')->alias('a')->join('s_a_ticket b', 'a.ticket_id = b.id')->where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limit));
        $lists = self::name('a_user_ticket')
                    ->alias('a')
                    ->join('s_a_ticket b', 'a.ticket_id = b.id')
                    ->field("a.*,b.name,b.price,b.annualized,b.capped,b.denomination")
                    ->where($where)
                    ->page($page, $limit)
                    ->order($order)
                    ->select()
                    ->toArray();
        if (!$lists) {
            ['count'=>0,'allpage'=>0,'lists'=>[]];
        }
        // p($lists);
        foreach ($lists as $key => $val) {
          $userTicketIsAnswer = Answer::getUserTicketIdIsAnswer($val['id']);
          $lists[$key]['is_answer'] = $userTicketIsAnswer;
        }
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 获取用户门票数量
     * @author qinlh
     * @since 2022-07-29
     */
    public static function getUserTicketNum($userId=0) {
      if($userId > 0) {
        $num = self::name('a_user_ticket')->where('user_id', $userId)->count();
        if($num) {
          return $num;
        }
      }
      return 0;
    }

    /**
     * 开启门票
     * @author qinlh
     * @since 2022-07-29
     */
    public static function startTicket($user_id=0, $ticket_id=0, $user_ticket_id=0) {
        if($user_id > 0 && $ticket_id > 0 && $user_ticket_id > 0) {
          self::startTrans();
            try {
              $isUpdateRes = self::name('a_user_ticket')->where('user_id', $user_id)->setField('is_start', 0); //首先关闭所有
              if($isUpdateRes !== false) {
                $res = self::name('a_user_ticket')->where(['id' => $user_ticket_id])->setField('is_start', 1); //开启指定门票
                if($res !== false) {
                  self::commit();
                  return true;
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
    }

    /**
     * 获取用户当前使用的门票id
     * @author qinlh
     * @since 2022-07-29
     */
    public static function getUserStartTicket($user_id=0) {
      if($user_id > 0) {
        $res = self::name('a_user_ticket')->where(['user_id' => $user_id, 'is_start' => 1])->find();
        if($res && count((array)$res) > 0) {
          return $res['id'];
        }
      }
      return 0;
    }

    /**
     * 根据用户门票id获取门票详情
     * @author qinlh
     * @since 2022-07-29
     */
    public static function getUserTicketDetail($user_ticket_id=0) {
      try {
        if($user_ticket_id) {
          $data = self::name('a_user_ticket')
                      ->where('a.id', $user_ticket_id)
                      ->alias('a')
                      ->join('s_a_ticket b', 'a.ticket_id=b.id')
                      ->field('a.ticket_id,a.time,a.insurance_amount,a.buy_price,b.*')
                      ->find();
        } else {
          $data = self::name('a_ticket')->where('id', 1)->field('*,id as ticket_id')->find();
        }
        if($data && count((array)$data) > 0) {
            return $data->toArray();
        }
        return [];
      } catch ( PDOException $e) {
        p($e->getMessage());
        return false;
      }
  }

  /**
   * 查看用户门票收入奖励明细
   * @author qinlh
   * @since 2022-07-30
   */
  public static function getUserTicketTodayAward($where=0, $page, $limit, $order='id desc') {
      if ($limit <= 0) {
        $limit = config('paginate.list_rows');// 获取总条数
      }
      $count = self::name('a_award')->where($where)->count();//计算总页面
      $allpage = intval(ceil($count / $limit));
      $lists = self::name('a_award')
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
   * 获取今日所有门票总的封顶值
   * @author qinlh
   * @since 2022-08-02
   */
  public static function getTicketAllCountAward($date='') {
    if(empty($date) || $date == '') {
      $date = date('Y-m-d');
    }
    $data = self::name('award_consumer')->alias('a')->join('m_user_ticket b', 'a.user_ticket_id=b.id')->where(['a.date' => $date, 'a.ticket_id' => ['>', 1], 'b.is_ransom' => 1])->select()->toArray();
    if($data && count((array)$data) > 0) {
      $count_award = 0;
      foreach ($data as $key => $val) {
        $browse_award = $val['browse_num'] > 0 ? $val['browse_num'] * $val['browse_amount'] : 0;
        $comment_award = $val['comment_num'] > 0 ? $val['comment_num'] * $val['comment_amount'] : 0;
        $like_award = $val['like_num'] > 0 ? $val['like_num'] * $val['like_amount'] : 0;
        $reward_award = $val['reward_num'] > 0 ? $val['reward_num'] * $val['reward_amount'] : 0;
        $count_award += $browse_award + $comment_award + $like_award + $reward_award;
      }
      return $count_award;
    }
  }

    /**
     * 异步获取每天门票明细数据
     * @author qinlh
     * @since 2022-08-03
     */
    public static function asyncCalcTicketTodayData() {
      $date = date('Y-m-d');
      // $date = '2022-08-03';
      $start_date = $date . ' 00:00:00';
      $end_date = $date . ' 23:59:59';
      $where = [];
      $insertData = [];
      $ticketCountCapped = self::getTodayTicketCountCapped();
      $insertData['ticket_count_capped'] = $ticketCountCapped;
      // p($insertData);
      $where['time'] = ['between time', [$start_date, $end_date]];
      $ticketList = self::name('a_user_ticket')->where($where)->alias('a')->select()->toArray();
      $ticket_sell_num = 0;
      $ticket_redeem_num = 0;
      $ticket_sales = 0; //每天的销售额
      $ticket_sales_premium = 0; //每天的销售保费
      foreach ($ticketList as $key => $val) {
        // $ticket_sell_num ++;
        if($val['is_ransom'] == 2) {
          $ticket_redeem_num ++;
        }
        $tickerDetail = Ticket::getTicketDetail($val['ticket_id']);
        $ticket_sales += (float)$tickerDetail['price'];
        $ticket_sales_premium += (float)$tickerDetail['price'] * ((float)$val['insurance_amount'] / 100);

      }
      $ticketCountNum = self::getTodayTicketCountNum();
      $ticketCountPrice = self::getTodayTicketCountPrice();
      $insertData['ticket_sell_num'] = $ticketCountNum;
      $insertData['ticket_redeem_num'] = $ticket_redeem_num;
      $insertData['ticket_sales'] = $ticket_sales;
      $insertData['count_ticket_sales'] = $ticketCountPrice;
      $insertData['ticket_sales_premium'] = $ticket_sales_premium;
      $insertData['update_time'] = date('Y-m-d H:i:s');
      if($insertData && count((array)$insertData) > 0) {
        TicketStatistics::saveTodayData($date, $insertData);
        return true;
      }
      return false;
    } 

    /**
     * 获取所有有效门票总的封顶值
     * @author qinlh
     * @since 2022-08-03
     */
    public static function getTodayTicketCountCapped() {
      $data = self::name('a_user_ticket')->where('a.is_ransom', 1)->alias('a')->join('m_ticket b', 'a.ticket_id=b.id')->field('a.ticket_id, b.capped')->select();
      $count_capped = 0;
      if($data) {
        foreach ($data as $key => $value) {
          $count_capped += $value['capped'];
        }
      }
      return $count_capped;
    }

    /**
     * 获取所有有效门票总数量
     * @author qinlh
     * @since 2022-08-03
     */
    public static function getTodayTicketCountNum() {
      $count = self::name('a_user_ticket')->where('a.is_ransom', 1)->alias('a')->count();
      return $count;  
    }

    /**
     * 获取所有有效门票总价格
     * @author qinlh
     * @since 2022-08-03
     */
    public static function getTodayTicketCountPrice() {
      $count = self::name('a_user_ticket')->where('a.is_ransom', 1)->alias('a')->join('m_ticket b', 'a.ticket_id=b.id')->field('a.ticket_id, b.capped')->sum('b.price');
      return $count;
    }
}
