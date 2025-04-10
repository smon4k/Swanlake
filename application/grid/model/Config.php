<?php
// +----------------------------------------------------------------------
// | 文件说明：配置管理 
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-04-09
// +----------------------------------------------------------------------
namespace app\grid\model;

use think\Model;
use RequestService\RequestService;

class Config extends Base
{

    /**
    * 获取配置列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getConfigList($page, $where, $limits=0)
    {
        $count = 0;
        $allpage = 0;
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        $count = self::where($where)->count();//计算总页面
        $allpage = intval(ceil($count / $limits));
        $lists = self::where($where)->page($page, $limits)->order("id asc")->select()->toArray();
        // p($lists);
        if(!$lists) return false;
        // p($dataList);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 添加配置
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function addRobotConfig($data) {
        $data['create_time'] = date("Y-m-d H:i:s", time());
        $data['update_time'] = date("Y-m-d H:i:s", time());
        $res = self::insert($data);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'添加成功'];
        } else {
            return ['code'=>0, 'msg'=>'添加失败'];
        }
    }

    /**
    * 修改配置
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function updateRobotConfig($id, $UpdateData) {
        $res = self::where("id", $id)->find();
        if(!$res) {
            return ['code'=>0, 'msg'=>'该配置不存在'];
        }
        $res = self::where("id", $id)->update($UpdateData);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'修改成功'];
        } else {
            return ['code'=>0, 'msg'=>'修改失败'];
        } 
    }
}
