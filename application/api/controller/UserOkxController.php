<?php
// +----------------------------------------------------------------------
// | 文件说明：用户 api
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2025 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-03-09
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\api\model\Notes;
use app\api\model\UserOkx;
use think\Request;
use think\Cookie;
use think\Config;
use think\Controller;
use lib\ClSms;
use cache\Rediscache;

class UserOkxController extends BaseController
{

    public function _initialize()
    {
        $token = request()->header('Authorization');
        $path = request()->pathinfo();
        $excludedRoutes = [
            'api/userokx/sendVerificationCode',
            'api/userokx/login',
            'api/userokx/index',
        ];
        if (!in_array($path, $excludedRoutes)) {
            if (!UserOkx::checkToken($token)) {
                return json([
                    'code' => '70001',
                    'message' => 'Token verification failed'
                ])->send();
            }
        }
    }

    public function index()
    {
        echo "Hello User";
    }

    /**
     * 获取用户信息
     * @Author qinlh
     * @param Request $request
     * @return \think\response\Json
     */
    public function getUserInfo(Request $request)
    {
        $userId = $request->request('userId', 0, 'intval');
        if ($address == '' && $userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $userInfo = UserOkx::getUserInfo($userId);
        if($userInfo) {
            return $this->as_json($userInfo);
        } else {
            return $this->as_json(70001, 'error');
        }
    }

     /**
     * 注册账号
     * @author qinlh
     * @since 2022-05-29
     */
    public function createAccount(Request $request) {
        $mobile = $request->post('mobile', '', 'trim');
        $password = $request->post('password', '', 'trim');
        $code = $request->post('code', '', 'trim');
        if ($mobile == '' || $password == '' || $code == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $cacheCode = Rediscache::getInstance()->get($mobile);
        if ($cacheCode == '') {
            return $this->as_json('70001', 'Verification code error');
        }
        if ($cacheCode != $code) {
            return $this->as_json('70001', 'Verification code error');
        }
        $isExistence = UserOkx::getMobileIsExit($mobile);
        if($isExistence) {
            return $this->as_json('10001', '用户名已存在');
        }
        $result = UserOkx::insertUserData($mobile, $password);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, '创建失败');
        }
    }

    /**
     * 用户登录
     * @author qinlh
     * @since 2022-05-30
     */
    public function login(Request $request) {
        $mobile = $request->post('mobile', '', 'trim');
        $password = $request->post('password', '', 'trim');
        if ($mobile == '' || $password == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $user = UserOkx::checkLogin($mobile, $password);
        if (is_object($user)) {
            $token = UserOkx::getToken($user->id);
            // p($auth_group);
            $return = [
                'token' => $token,
                'mobile' => $user->mobile,
                'uid' => $user->id          
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
     * 发送验证码
     * @param Request $request
     * @return mixed
     */
    public function sendVerificationCode(Request $request)
    {
        $phone = $request->post('mobile', '', 'trim');
        if ($phone == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $lastSendTime = Rediscache::getInstance()->get('last_send_time_' . $phone);
        if ($lastSendTime && time() - $lastSendTime < 60) {
            return $this->as_json('70001', 'Too frequent requests');
        }
        Rediscache::getInstance()->set('last_send_time_' . $phone, time(), 60);
        $code = mt_rand(1000, 9999);
        $res = ClSms::sendSms($phone, $code);
        if ($res && $res['code'] == 0) {
            Rediscache::getInstance()->set($phone, $code, 300);
            return $this->as_json('success');
        } else {
            return $this->as_json('70001', 'Send Error');
        }        
    }

    /**
     * 验证验证码
     * @param Request $request
     * @return mixed
     */
    public function checkVerificationCode(Request $request) {
        $phone = $request->post('mobile', '', 'trim');
        $code = $request->post('code', '', 'trim');
        if ($phone == '' || $code == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $cacheCode = Rediscache::getInstance()->get($phone);
        if ($cacheCode == $code) {
            return $this->as_json('success');
        } else {
            return $this->as_json('70001', 'Verification code error');
        }
    }

    /**
     * 修改密码
     * @param Request $request
     * @return mixed
     */
    public function changePassword(Request $request) {
        $phone = $request->post('mobile', '', 'trim');
        $password = $request->post('password', '', 'trim');
        if ($phone == '' || $password == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = UserOkx::changePassword($phone, $password);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json('70001', 'Change Error');
        }
    }
}
