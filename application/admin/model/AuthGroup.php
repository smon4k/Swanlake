<?php

namespace app\admin\model;

use think\Cache;
use cache\Rediscache;

class AuthGroup extends Base
{
    /**
    * 获取用户对应权限id
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getUserRoules($roule_id=0) {
        if (empty($roule_id)) {
            return [];
        }
        $info = self::get(intval($roule_id));
        return $info;
    }

    /**
    * 获取权限角色列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getlist($export=0, $page=1, $limits=0, $where=[]) {
        $count = 0;
        $allpage = 0;
        if($export) {
            if($limits == 0) {
                $limits = config('paginate.list_rows');// 获取总条数
            }
            $count = self::where($where)->count();//计算总页面
            $allpage = intval(ceil($count / $limits));
            $lists = self::where($where)->page($page, $limits)->order("id asc")->select()->toArray();
            if(!$lists) return false;
            foreach ($lists as $key => $val) {
                $rules_arr = [];
                $rules_arr_select = [];
                $menu_rules_arr = [];
                $menu_rules_arr_select = [];
                if(!empty($val['rules'])) {
                    $rules_arr = AuthRule::getRuleList($val['id'], $val['rules']);
                    $rules_arr_select = self::RulesArrayMake(AuthRule::getRuleList($val['id'], $val['rules']));
                }
                // p($rules_arr);
                if(!empty($val['menu_rules'])) {
                    $menu_rules_arr = AuthMenuRule::getRuleList($val['id'], $val['menu_rules'], 1, 2);
                    $menu_rules_arr_select = self::RulesArrayMake(AuthMenuRule::getRuleList($val['id'], $val['menu_rules'], 1, 2));
                    // p($menu_rules_arr_select);
                }
                // $lists[$key]['rules_name'] = implode(",", $rules_arr);
                // $lists[$key]['menu_rules_name'] = implode(",", $menu_rules_arr);
                $lists[$key]['rules_array'] = $rules_arr;
                $lists[$key]['rules_arr_select'] = $rules_arr_select;
                $lists[$key]['menu_rules_array'] = $menu_rules_arr;
                $lists[$key]['menu_rules_array_select'] = $menu_rules_arr_select;
                $lists[$key]['create_time'] = date("Y-m-d H:i:s", $val['create_time']);
                $lists[$key]['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
            }
            // p($lists);
            return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
        } else {
            $lists = self::where($where)->select()->toArray();
            // p($lists);
            // $menu_rules_arr = [];
            // foreach ($lists as $key => $val) {
            //     $rules_arr_select = [];
            //     if(!empty($val['rules'])) {
            //         if($val['id'] > 1) {
            //             $rules_arr_select = self::RulesArrayMake(AuthRule::getRuleList($val['id'], $val['rules']));
            //             $lists[$key]['rules_arr_select'] = $rules_arr_select;
            //         }
            //     }
            // }
            // p($lists);
            return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
        }
    }

    /**
    * 用户接口权限进行判断子元素进行组合到新数组中
    * 递归筛选
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    // public static function RulesArrayMake($rules_arr, $pid=0) {
    //     $arr = [];
    //     foreach ($rules_arr as $key => $val) {
    //         if($val['child'] && count($val['child']) > 0) {
    //             echo $pid . "&&&" . $val['id'];
    //             echo "</br>";
    //             if($val['id'] > 0) {
    //                 $arr[$key] = [$val['id']];
    //                 $arr = array_merge(self::RulesArrayMake($val['child'], $val['id']), $arr);
    //                 p($arr);
    //             } else {
    //                 $arr = array_merge(self::RulesArrayMake($val['child'], $val['id']), $arr);
    //             }
    //             // p($arr); 
    //         } else {
    //             echo $pid . "///" . $val['id'];
    //             echo "</br>";
    //             if($pid > 0) {
    //                 $arr[$key] = [$pid, $val['id']];
    //             } else {
    //                 $arr[$key] = [$val['id']];
    //             }
    //         }  
    //     }
    //     return $arr;
    // }

    /**
    * 用户接口权限进行判断子元素进行组合到新数组中
    * 递归筛选
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function RulesArrayMake($rules_arr=[], &$result=[]) {
        if(empty($rules_arr)) return [];
        foreach ($rules_arr as $key => $val) {
            if($val['child'] && count((array)$val['child']) > 0) {
                self::RulesArrayMake($val['child'], $result);
            } else {
                if($val['pid_value'] && count((array)$val['pid_value']) > 0) {
                    array_push($val['pid_value'], $val['id']);
                    $result[] = $val['pid_value'];
                } else {
                    $result[] = [$val['id']];
                }
            }  
        }
        return $result;
    }

    /**
    * 添加角色信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function AddGroupAuthData($InsertData) {
        $res = self::where("title", $InsertData['title'])->find();
        if($res) {
            return ['code'=>0, 'msg'=>'该角色已经存在'];
        }
        $res = self::insert($InsertData);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'添加成功'];
        } else {
            return ['code'=>0, 'msg'=>'添加失败'];
        }
    }

    /**
    * 修改角色信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function UpdateGroupAuthData($id=0, $UpdateData=[]) {
        $res = self::where("id", $id)->find();
        if(!$res) {
            return ['code'=>0, 'msg'=>'该角色不存在'];
        }
        $res = self::where("id", $id)->update($UpdateData);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'修改成功'];
        } else {
            return ['code'=>0, 'msg'=>'修改失败'];
        }
    }

    /**
    * 更改关键字状态值
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function startAuthGroupRule($id, $start) {
        return self::where("id", $id)->setField(['status'=>$start]);
    }

    /**
    * 删除角色信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function delAuthGroupRow($id) {
        if($id) {
            $is_admin_group = AdminUser::getUserGroupId($id);
            if($is_admin_group) {
                return ['code'=>0, 'msg'=>'该角色下存在管理员信息，不能删除该角色'];
            } else {
                $res = self::where("id",$id)->delete();
                if(true == $res) {
                    return ['code'=>1, 'msg'=>'删除成功'];
                } else {
                    return ['code'=>0, 'msg'=>'删除失败'];
                }
            }
        }
    }
}