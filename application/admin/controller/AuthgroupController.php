<?php

/**
 * @author Ray
 * @date 2020-01-17
 * 权限角色功能
 */
namespace app\admin\controller;

use app\admin\model\AuthGroup;
use think\Request;

class AuthgroupController extends BaseController
{
    /**
    * 获取角色列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getAuthGroupList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $title = $request->request('title', '', 'trim');
        $export = $request->request('export', 0, 'intval'); // 0 表示导出
        $status = $request->request('status', '', 'trim');
        $where = [];
        if($title && $title !== '') {
            $where['title'] = ['like',"%" . $title . "%"];
        }
        if($status && $status !== '') {
            $where['status'] = $status;
        }
        if($export) {
            $data = AuthGroup::getlist(true, $page, $limits, $where);
        } else {
            $data = AuthGroup::getlist(false, false, false, $where);
        }
        // p($data);    
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 添加角色信息数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function addAuthGroupInfo(Request $request) {
        $title = $request->request('title', '', 'trim');
        $rules = input('post.rules/a');
        $menu_rules = input('post.menu_rules/a');
        $rules_arr = [];
        $menu_rules_arr = [];
        if($rules && !empty($rules)) {
            $rules_arr = array_reduce($rules, function ($result, $value) {
                return array_merge($result, array_values($value));
            }, array());
        }
        if($menu_rules && !empty($menu_rules)) {
            $menu_rules_arr = array_reduce($menu_rules, function ($result, $value) {
                return array_merge($result, array_values($value));
            }, array());
        }
        if($title == "" && count((array)$menu_rules_arr) <= 0) {
            return $this->as_json(70001, "参数有误");
        }
        $InsertData = [
            'title' => $title,
            'rules' => implode(",", $rules_arr),
            'menu_rules' => implode(",", $menu_rules_arr),
            'create_time' => time()
        ];
        $res = AuthGroup::AddGroupAuthData($InsertData);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 修改角色信息数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function sevaAuthGroupInfo(Request $request) {
        $id = $request->request('id', 0, 'intval');
        $title = $request->request('title', '', 'trim');
        $rules = input('post.rules/a');
        $menu_rules = input('post.menu_rules/a');
        $rules_arr = [];
        $menu_rules_arr = [];
        if($rules && !empty($rules)) {
            $rules_arr = array_reduce($rules, function ($result, $value) {
                if(is_array($value)) {
                    return array_merge($result, array_values($value));
                } else {
                    return array_merge($result, [$value]);
                }
            }, array());
        }
        if($menu_rules && !empty($menu_rules)) {
            $menu_rules_arr = array_reduce($menu_rules, function ($result, $value) {
                if(is_array($value)) {
                    return array_merge($result, array_values($value));
                } else {
                    return array_merge($result, [$value]);
                }
            }, array());
        }
        if($id <= 0 && $title == "" && count((array)$menu_rules_arr) <= 0) {
            return $this->as_json(70001, "参数有误");
        }
        $UpdateData = [
            'title' => $title,
            'rules' => implode(",", $rules_arr),
            'menu_rules' => implode(",", $menu_rules_arr),
            'update_time' => time()
        ];
        $res = AuthGroup::UpdateGroupAuthData($id, $UpdateData);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 修改状态
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function startAuthGroupRule(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $start = $request->request('start', 0, 'intval'); 
        $res = AuthGroup::startAuthGroupRule($id, $start);
        if($res) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 删除角色信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function delAuthGroupRow(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $res = AuthGroup::delAuthGroupRow($id);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }
}
