<?php
// +----------------------------------------------------------------------
// | 文件说明：门票统计 Model
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

class TicketStatistics extends Base
{

   /**
    * 记录今天数据记录
    * @author qinlh
    * @since 2022-08-03
    */
    public static function saveTodayData($date='', $insertData=[])
    {
        if ($date !== '') {
            $res = self::name('a_ticket_statistics')->where('date', $date)->find();
            if($res && count((array)$res) > 0) {
              $isRes = self::name('a_ticket_statistics')->where('date', $date)->update($insertData);
            } else {
              $insertData['date'] = $date;
              $isRes = self::wname('a_ticket_statistics')->here('date', $date)->insertGetId($insertData);
            }
            if($isRes) {
              return true;
            }
        }
        return false;
    }

    /**
     * 获取指定日期数据
     * @author qinlh
     * @since 2022-08-04
     */
    public static function getTodayData($date='') {
      if ($date !== '') {
        $data = self::name('a_ticket_statistics')->where('date', $date)->find();
        if($data && count((array)$data) > 0) {
          return $data->toArray();
        }
      }
      return [];
    }
}
