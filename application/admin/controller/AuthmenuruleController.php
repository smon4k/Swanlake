<?php

/**
 * @author Ray
 * @date 2020-01-19
 * 权限角色功能
 */
namespace app\admin\controller;

use app\admin\model\AuthMenuRule;
use think\Request;

class AuthmenuruleController extends BaseController
{
    /**
    * 获取角色列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getAuthMenuRuleList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $name = $request->request('name', '', 'trim');
        $status = $request->request('status', '', 'trim');
        $where = [];
        if($name && $name !== '') {
            $where['name'] = ['like',"%" . $name . "%"];
        }
        if($status && $status !== '') {
            $where['status'] = $status;
        }
        // $export = $request->request('export', 0, 'intval'); // 0 表示导出
        $data = AuthMenuRule::getMenuRulelist($where);
        $maxId = AuthMenuRule::getMaxId();
        // p($data);
        // $count = $data['count'];
        // $allpage = $data['allpage'];
        // $lists = $data['lists'];
        // return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
        return $this->as_json(['data' => $data, 'maxId' => $maxId]);
    }

    /**
    * 获取菜单权限列表 带分页
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getAuthMenuRuleListPage(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $title = $request->request('title', '', 'trim');
        $where = [];
        $data = AuthMenuRule::getMenuRulelist($page, $limits, $where);
        // p($data);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 添加菜单
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function addAuthMenuRuleData(Request $request) {
        $name = $request->post('name', '', 'trim');
        $path = $request->post('path', '', 'trim');
        $icon = $request->post('icon', '', 'trim');
        $sort = $request->post('sort', 1, 'intval');
        $pid_arr = $request->post('pid/a', [], 'trim');
        if($path == "" && $name == "" && $icon == "" && $sort == "") {
            return $this->as_json(70001, "参数有误");
        }
        $pid = 0;
        if(isset($pid_arr) && count((array)$pid_arr) > 0) {
            $pid = end($pid_arr);
        }
        $InsertData = [
            'name' => $name,
            'path' => $path,
            'icon' => $icon,
            'sort' => $sort,
            'pid' => $pid,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $res = AuthMenuRule::addAuthMenuRuleData($InsertData);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 修改菜单
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function saveAuthMenuRuleData(Request $request) {
        $id = $request->request('id', 0, 'intval');
        $name = $request->request('name', '', 'trim');
        $path = $request->request('path', '', 'trim');
        $icon = $request->request('icon', '', 'trim');
        $sort = $request->request('sort', 1, 'intval');
        $pid_arr = input('post.pid/a');
        if($id <= 0 && $name == "" && $icon == "" && $sort == "" && count((array)$pid_arr) <= 0) {
            return $this->as_json(70001, "参数有误");
        }
        $pid = 0;
        if($pid_arr && count((array)$pid_arr) > 0) {
            $pid = end($pid_arr);
        }
        if($id == $pid) {
            return $this->as_json(70001, "不能选择本身级别值");
        }
        $UpdateData = [
            'name' => $name,
            'path' => $path,
            'icon' => $icon,
            'sort' => $sort,
            'pid' => $pid,
            'update_time' => time(),
        ];
        $res = AuthMenuRule::saveAuthMenuRuleData($id, $UpdateData);
        if($res['code'] == 1) {
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
    public function delAuthMenuRuleRow(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $res = AuthMenuRule::delAuthMenuRuleRow($id);
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
    public function startAuthMenuRule(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $start = $request->request('start', 0, 'intval'); 
        $res = AuthMenuRule::startAuthMenuRule($id, $start);
        if($res) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }
}
