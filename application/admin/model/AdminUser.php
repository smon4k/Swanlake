<?php

namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;

class AdminUser extends Base
{
    /**
     * 密码生成
     * @param $password
     * @return string
     */
    public static function passwordGenerate($password)
    {
        return md5($password);
    }

    /**
     * 校验密码
     * @param $input_password
     * @param $password
     * @return bool
     */
    public static function checkPassword($input_password, $password)
    {
        return md5($input_password) == $password;
    }

    /**
     * 按id获取
     * @param $id
     * @return mixed
     */
    public static function getById($id)
    {
        if (empty($id)) {
            return [];
        }
        if (is_array($id)) {
            $items = [];
            foreach ($id as $each_id) {
                $info = self::getById($each_id);
                if (!empty($info)) {
                    array_push($items, $info);
                }
            }
            return $items;
        } else {
            $info = self::get(intval($id));
            return $info;
        }
    }



    /**
     * 检查登录
     *
     * @param $user_name
     * @param $password
     * @return mixed
     */
    public static function checkLogin($user_name, $password)
    {
        $user_info = AdminUser::get(['user_name' => $user_name]);
        if (empty($user_info)) {
            return -1;
        }
        if (self::checkPassword($password, $user_info->password)) {
            return $user_info;
        } else {
            return -2;
        }
    }

    /**
     * 获取用户token
     * @param $uid
     * @return string
     */
    public static function getToken($uid)
    {
        $token = Cache::get('token_' . $uid);
        $duration = 24 * 3600;
        if (empty($token)) {
            $token = ClCrypt::encrypt(json_encode([
                'uid' => $uid
            ]), self::CRYPT_KEY, $duration);
            //存储
            Cache::set('token_' . $uid, $token, $duration - 600);
        }
        return md5($token) . $token;
    }


    /**
     * 解密token
     * @param $token
     * @return int
     */
    public static function getUidByToken($token)
    {
        $token_md5 = substr($token, 0, 32);
        $token = substr($token, 32);
        if (md5($token) != $token_md5) {
            return 0;
        }
        $token_array = ClCrypt::decrypt($token, self::CRYPT_KEY);
        //判断是否有效，缓存类型不一致，存储内容可能是array或json
        if (!is_array($token_array)) {
            $token_array = json_decode($token_array, true);
        }
        if (empty($token_array) || !isset($token_array['uid'])) {
            return 0;
        }
        $uid = $token_array['uid'];
        if (Cache::get('token_' . $uid) != $token) {
            return 0;
        }
        return $uid;
    }

    /**
    * 获取管理员列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getAdminUserList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::where($where)->alias("au")->join("s_auth_group ag", "au.groupid=ag.id")->count();//计算总页面
        $allpage = intval(ceil($count / $limits));
        $lists = self::name('admin_user')
                    ->alias("au")
                    ->join("s_auth_group ag", "au.groupid=ag.id")
                    ->field("au.id,au.user_name,au.real_name,au.ceil_phone,au.email,au.groupid,au.create_at,ag.title as group_name")
                    ->where($where)
                    ->page($page, $limits)
                    ->order("au.id asc")
                    ->select();
        // p($lists);
        foreach ($lists as $key => $val) {
            $lists[$key]['update_time'] = date("Y-m-d H:i:s", $val['create_at']);
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
    * 删除管理员信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function delAdminUseRow($id) {
        if($id) {
            $res = self::where("id",$id)->delete();
            if(true == $res) {
                return ['code'=>1, 'msg'=>'删除成功'];
            } else {
                return ['code'=>0, 'msg'=>'删除失败'];
            }
        }
    }

    /**
    * 添加管理员信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function AddAdminUser($user_name='', $real_name='', $password='', $ceil_phone='', $email='', $groupid=0) {
        $res = self::where("user_name", $user_name)->find();
        if($res) {
            return ['code'=>0, 'msg'=>'该账号已经存在'];
        }
        $InsertData = [
            'user_name' => $user_name,
            'real_name' => $real_name,
            'ceil_phone' => $ceil_phone,
            'email' => $email,
            'password' => self::passwordGenerate($password),
            'groupid' => $groupid,
        ];
        $res = self::insert($InsertData);
        if(true == $res) {
            return ['code'=>1, 'msg'=>'添加成功'];
        } else {
            return ['code'=>0, 'msg'=>'添加失败'];
        }
    }

    /**
    * 修改管理员信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function UpdateAdminUser($id, $user_name='', $real_name='', $password='', $ceil_phone='', $email='', $groupid=0) {
        $UpdateData = [
            'user_name' => $user_name,
            'real_name' => $real_name,
            'ceil_phone' => $ceil_phone,
            'email' => $email,
            'password' => self::passwordGenerate($password),
            'groupid' => $groupid,
        ];
        $res = self::where("id", $id)->update($UpdateData);
        if(false !== $res) {
            return ['code'=>1, 'msg'=>'修改成功'];
        } else {
            return ['code'=>0, 'msg'=>'修改失败'];
        }
    }

    /**
    * 根据角色id查询是否存在用户
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getUserGroupId($groupid=0) {
        if($groupid) {
            return self::where("groupid", $groupid)->find();
        }
    }

    /**
    * 获取用户信息及角色信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getUserInfoGroupName($user_id=0) {
        if($user_id && $user_id > 0) {
            // $where = [''];
            $return = self::alias("u")->where(['u.id'=>$user_id])->join("s_auth_group ag", "u.groupid=ag.id")->field("u.real_name,u.user_name,ag.title")->find();
            if($return) {
                return $return;
            } else {
                return [];
            }
        }
    }
}
