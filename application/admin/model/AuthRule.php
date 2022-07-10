<?php

namespace app\admin\model;

use think\Cache;
use cache\Rediscache;

class AuthRule extends Base
{
    /**
    * 获取用户对应导航权限id
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getRuleList($auth_group_id=0, $rule_ids='', $status='') {
        $where = [];
        if($status && $status !== '') {
            $where['status'] = $status;
        }
        if($auth_group_id > 1) { //除了超级管理员以外
            $where['id'] = ['in', $rule_ids];
        }
        $filed = ['id','path','name','css','pid','sort','status'];
        $menu_list = self::where($where)->field($filed)->order("sort asc")->select()->toArray();
        foreach ($menu_list as $key => $value) {
            $menu_list[$key]['pid_value'] = array_column(self::get_top_parentid($menu_list, $value['pid']), "id");
        }
        $data = self::getListTree($menu_list);
        return $data;
    }

    /**
    * 获取接口权限角色列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getMenuRulelist($where=[]) {
        // $filed = ['id','path','name','icon','pid','sort','status'];
        $menu_list = self::where($where)->order("id asc")->select()->toArray();
        foreach ($menu_list as $key => $value) {
            $menu_list[$key]['pid_value'] = array_column(self::get_top_parentid($menu_list, $value['pid']), "id");
            $menu_list[$key]['create_time'] = date("Y-m-d H:i:s", $value['create_time']);
            $menu_list[$key]['update_time'] = date("Y-m-d H:i:s", $value['update_time']);
        }
        $data = self::getListTree($menu_list);
        return $data;
    }

    /**
    * 获取最大的id值
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getMaxId() {
        return self::max("id");
    }


    /**
    * 递归根据子元素获取父元素
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function get_top_parentid($cate, $id) {
        $arr = array();
        foreach($cate as $v) {
            if($v['id'] == $id){
                $arr[] = $v;// $arr[$v['id']]=$v['name'];
                $arr = array_merge(self::get_top_parentid($cate, $v['pid']), $arr);
            }
        }
        return $arr;
    }

    /**
    * 获取菜单权限角色列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getRulelistPage($page=1, $limits=0, $where=[]) {
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
        $rules_arr = [];
        $menu_rules_arr = [];
        foreach ($lists as $key => $val) {
            $lists[$key]['create_time'] = date("Y-m-d H:i:s", $val['create_time']);
            $lists[$key]['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
        }
        $dataList = self::getListTree($lists);
        // p($dataList);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$dataList];
    }

    /**
    * 按层级整理菜单列表数据 只能到二级菜单
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getList($param){
        $parent = []; //父类
        $child = [];  //子类
        foreach($param as $key=>$vo){
            if($vo['pid'] == 0){
                if($vo['path'] == "#") {
                    $vo['path'] = "#" . strval($vo['id']);
                }
                // $vo['path'] = '#';
                $parent[] = $vo;
            }else{
                $child[] = $vo;
            }
        }
        foreach($parent as $key=>$vo){
            foreach($child as $k=>$v){
                if($v['pid'] == $vo['id']){
                    $parent[$key]['child'][] = $v;
                }
            }
        }
        unset($child);
        return $parent;
    }

    /**
    * 添加管理员信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function addAuthRuleData($InsertData) {
        $res = self::where("name", $InsertData['name'])->find();
        if($res) {
            return ['code'=>0, 'msg'=>'该菜单已经存在'];
        }
        $res = self::insert($InsertData);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'添加成功'];
        } else {
            return ['code'=>0, 'msg'=>'添加失败'];
        }
    }

    /**
    * 修改菜单信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function saveAuthRuleData($id=0, $UpdateData=[]) {
        $res = self::where("id", $id)->find();
        if(!$res) {
            return ['code'=>0, 'msg'=>'该菜单不存在'];
        }
        $res = self::where("id", $id)->update($UpdateData);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'修改成功'];
        } else {
            return ['code'=>0, 'msg'=>'修改失败'];
        }
    }

    /**
    * 删除菜单信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function delAuthRuleRow($id) {
        if($id) {
            $is_pid = self::where("pid", $id)->find();
            if($is_pid) {
                return ['code'=>0, 'msg'=>'该级下面存在子级元素，请先删除子级元素数据'];
            } else {
                $res = self::where("id", $id)->delete();
                if(true == $res) {
                    return ['code'=>1, 'msg'=>'删除成功'];
                } else {
                    return ['code'=>0, 'msg'=>'删除失败'];
                }
            }
        }
    }

    /**
    * 更改关键字状态值
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function startAuthRule($id, $start) {
        return self::where("id", $id)->setField(['status'=>$start]);
    }
}