<?php
// +----------------------------------------------------------------------
// | 文件说明：订单管理 
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-07-28
// +----------------------------------------------------------------------
namespace app\grid\model;

use think\Model;
use RequestService\RequestService;

class Strategy extends Base
{

    /**
    * 获取策略列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getStrategyList($page, $where, $limits=0)
    {
        if ($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::alias("a")
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::alias("a")
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*')
                    ->order("id desc")
                    ->select()
                    ->toArray();
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 获取所有策略列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getAllStrategyList()
    {
        $lists = self::where("status", 1)->select()->toArray();
        return $lists;
    }

    /**
    * 更新策略最大最小仓位数
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function updateStrategyMaxMinPosition($id, $data)
    {
        $res = self::where("id", $id)->update($data);
        if($res !== false) {
            return ['code'=>1, 'msg'=>'Update success'];
        } else {
            return ['code'=>0, 'msg'=>'Update failed'];
        }
    }

    /**
    * 根据ID获取策略
    * @param int $id
    * @return mixed
    */
    public static function getStrategyById($id)
    {
        return self::where('id', $id)->find();
    }

    /**
    * 判断策略名是否已存在
    * @param string $name
    * @param int $excludeId
    * @return bool
    */
    public static function existsByName($name, $excludeId = 0)
    {
        $query = self::where('name', $name);
        if ($excludeId > 0) {
            $query = $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
    * 删除策略
    * @param int $id
    * @return array
    */
    public static function deleteStrategy($id)
    {
        $strategy = self::where('id', $id)->find();
        if (!$strategy) {
            return ['code'=>0, 'msg'=>'该策略不存在'];
        }
        $res = self::where('id', $id)->delete();
        if ($res) {
            return ['code'=>1, 'msg'=>'删除成功'];
        }
        return ['code'=>0, 'msg'=>'删除失败'];
    }

    /**
    * 新增策略
    * @param array $data
    * @return array
    */
    public static function addStrategy($data)
    {
        $exists = self::where('name', $data['name'])->find();
        if ($exists) {
            return ['code'=>0, 'msg'=>'Strategy already exists'];
        }
        $res = self::insert($data);
        if($res) {
            return ['code'=>1, 'msg'=>'Add success'];
        } else {
            return ['code'=>0, 'msg'=>'Add failed'];
        }
    }


}
