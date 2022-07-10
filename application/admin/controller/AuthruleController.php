<?php

/**
 * @author Ray
 * @date 2020-01-19
 * 权限角色功能
 */
namespace app\admin\controller;

use app\admin\model\AuthRule;
use think\Request;

class AuthruleController extends BaseController
{
    /**
    * 获取角色列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getAuthRuleList(Request $request) {
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
        $data = AuthRule::getMenuRuleList($where);
        $maxId = AuthRule::getMaxId();
        // p($data);
        // $count = $data['count'];
        // $allpage = $data['allpage'];
        // $lists = $data['lists'];
        // return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
        // return $this->as_json($data);
        return $this->as_json(['data' => $data, 'maxId' => $maxId]);
    }

    /**
    * 获取接口权限权限列表 带分页
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getAuthRuleListPage(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $title = $request->request('title', '', 'trim');
        $where = [];
        $data = AuthRule::getRulelist($page, $limits, $where);
        // p($data);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 添加接口权限
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function addAuthRuleData(Request $request) {
        $name = $request->request('name', '', 'trim');
        $path = $request->request('path', '', 'trim');
        $css = $request->request('css', '', 'trim');
        $sort = $request->request('sort', 1, 'intval');
        $pid_arr = input('post.pid/a');
        if($path == "" && $name == "" && $css == "" && $sort == "" && count((array)$pid_arr) <= 0) {
            return $this->as_json(70001, "参数有误");
        }
        $pid = 0;
        if($pid_arr && count((array)$pid_arr) > 0) {
            $pid = end($pid_arr);
        }
        // p($pid);
        $InsertData = [
            'name' => $name,
            'path' => $path,
            'css' => $css,
            'sort' => $sort,
            'pid' => $pid,
            'create_time' => time(),
        ];
        $res = AuthRule::addAuthRuleData($InsertData);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 修改接口权限
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function saveAuthRuleData(Request $request) {
        $id = $request->request('id', 0, 'intval');
        $name = $request->request('name', '', 'trim');
        $path = $request->request('path', '', 'trim');
        $css = $request->request('css', '', 'trim');
        $sort = $request->request('sort', 1, 'intval');
        $pid_arr = input('post.pid/a');
        if($id <= 0 && $name == "" && $css == "" && $sort == "" && count((array)$pid_arr) <= 0) {
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
            'css' => $css,
            'sort' => $sort,
            'pid' => $pid,
            'update_time' => time(),
        ];
        $res = AuthRule::saveAuthRuleData($id, $UpdateData);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 删除接口权限数据信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function delAuthRuleRow(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $res = AuthRule::delAuthRuleRow($id);
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
    public function startAuthRule(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $start = $request->request('start', 0, 'intval'); 
        $res = AuthRule::startAuthRule($id, $start);
        if($res) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }
}
