<?php

namespace app\admin\controller;
use think\Cookie;
use think\Request;
use lib\ClCrypt;
use app\admin\model\AdminUser;
use app\admin\model\AuthGroup;
use app\admin\model\AuthMenuRule;
use think\Env;

/**
 * @authoro Ray
 * 后台管理平台用户表
 * Class AdminuserController
 * @package app\api\controller
 */
class AdminuserController extends BaseController
{
    public function chkLogin(Request $request)
    {
        $user_name = $request->post('user_name', '', 'trim');
        $password = $request->post('password', '', 'trim');
        if ($user_name == '' || $password === '') {
            return $this->as_json(70001, '用户名和密码不能为空');
        }
        $user = AdminUser::checkLogin($user_name, $password);
        if (is_object($user)) {
            $token = AdminUser::getToken($user->id);
            $auth_group = AuthGroup::getUserRoules($user->groupid);
            // p($auth_group);
            $return = [
                'token' => $token,
                'server_ip' => Env::get('DOMAIN.SERVERIP'),
                'uid' => $user->id,
                'user_name' => $user->real_name. "-" . $auth_group->title,
                'token_duration' => 24 * 3600
            ];
            Cookie::set('token', $token);
            return $this->as_json($return);
        } else {
            if ($user == -1) {
                return $this->as_json(70002, '账号不存在');
            } else {
                return $this->as_json(70003, '密码错误');
            }
        }
    }

    /**
    * 获取用户前台导航菜单数据
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getUserMenu(Request $request) {
        $token = $request->request('token', '', 'trim');
        $status = $request->request('status', '', 'trim');
        $is_format = $request->request('is_format', 0, 'intval');
        if ($token == '') {
            return $this->as_json(70001, 'token不能为空');
        }
        $uid = AdminUser::getUidByToken($token);
        if(!$uid && $uid < 0) {
            return $this->as_json(70001, '获取用户信息失败');
        }
        $AdminInfo = AdminUser::getById($uid);
        if(!$AdminInfo) {
            return $this->as_json(70001, '获取用户信息失败');
        }
        $MenuRules = AuthGroup::getUserRoules($AdminInfo->groupid);
        // p($MenuRules );
        $MenuList = AuthMenuRule::getRuleList($MenuRules->id, $MenuRules->menu_rules, $status, $is_format);
        // p($MenuList);
        return $this->as_json($MenuList);
    }

    /**
    * 获取管理员列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function AdminUserList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $user_name = $request->request('user_name', '', 'trim');
        $real_name = $request->request('real_name', '', 'trim');
        $where = [];
        if($user_name && $user_name !== "") {
            $where['au.user_name'] = $user_name;
        }
        if($real_name && $real_name !== "") {
            $where['au.real_name'] = ['like',"%" . $real_name . "%"];
        }
        $data = AdminUser::getAdminUserList($page, $where, $limits);
        // p($data);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 添加管理员信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function addAdminUserInfo(Request $request) {
        $user_name = $request->request('user_name', '', 'trim');
        $real_name = $request->request('real_name', '', 'trim');
        $ceil_phone = $request->request('ceil_phone', '', 'trim');
        $email = $request->request('email', '', 'trim');
        $password = $request->request('password', '', 'trim');
        $groupid = $request->request('groupid', '', 'intval');
        $res = AdminUser::AddAdminUser($user_name, $real_name, $password, $ceil_phone, $email, $groupid);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {    
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 修改管理员信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function sevaAdminUserInfo(Request $request) {
        $id = $request->request('id', 0, 'intval');
        $user_name = $request->request('user_name', '', 'trim');
        $real_name = $request->request('real_name', '', 'trim');
        $ceil_phone = $request->request('ceil_phone', '', 'trim');
        $email = $request->request('email', '', 'trim');
        $password = $request->request('password', '', 'trim');
        $groupid = $request->request('groupid', '', 'intval');
        $res = AdminUser::UpdateAdminUser($id, $user_name, $real_name, $password, $ceil_phone, $email, $groupid);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 删除用户信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function delAdminUseRow(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $res = AdminUser::delAdminUseRow($id);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }
}
