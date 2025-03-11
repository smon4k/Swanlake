<?php

// +----------------------------------------------------------------------
// | 文件说明：OKX用户模型
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2030 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2025-03-09
// +----------------------------------------------------------------------

namespace app\api\model;

use think\Model;
use lib\ClCrypt;
use think\Config;
use think\Cache;
use think\Loader;
use Aws\S3\S3Client;
use RequestService\RequestService;
use think\Cookie;
use cache\Rediscache;


class UserOkx extends Base
{
    /**
     * 获取用户信息
     * @author qinlh
     * @since 2025-03-09
     */
    public static function getUserInfo($userId=0)
    {
        if ($userId > 0) {
            $userinfo = self::where('id', $userId)->find();
            if ($userinfo && count((array)$userinfo) > 0) {
                return $userinfo->toArray();
            }
        }
        return [];
    }

    /**
     * 检查登录
     *
     * @param $user_name
     * @param $password
     * @return mixed
     */
    public static function checkLogin($mobile='', $password='')
    {
        if ($mobile !== '' && $password !== '') {
            $user_info = self::where('mobile', $mobile)->whereOr('username', $mobile)->find();
            if (!$user_info || empty($user_info)) {
                return -1;
            }
            if (encryptionPassword($password) === $user_info['password']) {
                return $user_info;
            } else {
                return -2;
            }
        }
    }

    /**
     * 获取用户token
     * @param $uid
     * @return string
     */
    public static function getToken($uid)
    {
        $token = Rediscache::getInstance()->get('token_' . $uid);
        $duration = 24 * 3600;
        if (empty($token)) {
            $token = ClCrypt::encrypt(json_encode([
                'uid' => $uid
            ]), self::CRYPT_KEY, $duration);
            //存储
            Rediscache::getInstance()->set('token_' . $uid, $token, $duration - 600);
        }
        return $token;
    }

    /**
     * 查询手机号是否已绑定
     * @author qinlh
     * @since 2025-03-09
     */
    public static function getMobileIsExit($mobile)
    {
        if ($mobile && $mobile !== '') {
            $userinfo = self::where('mobile', $mobile)->find();
            if ($userinfo && count((array)$userinfo) > 0) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取用户信息
     * @author qinlh
     * @since 2025-03-09
     */
    public static function getUserIdInfo($userId=0)
    {
        if ($userId > 0) {
            $data = [];
            $userinfo = self::where('id', $userId)->find();
            if ($userinfo && count((array)$userinfo) > 0) {
                $data = $userinfo->toArray();
                return $data;
            }
            return [];
        }
    }

    /**
     * 获取单条用户信息
     * @author qinlh
     * @since 2025-03-09
     */
    public static function getUserInfoOne($userId=0)
    {
        $userInfo = null;
        if ($userId > 0) {
            $userInfo = self::where('id', $userId)->find();
        }
        return $userInfo;
    }

    /**
     * 开始添加用户信息
     * @author qinlh
     * @since 2025-03-09
     */
    public static function insertUserData($mobile='', $password='')
    {
        if ($mobile !== '' && $password !== '') {
            self::startTrans();
            try {
                $insertData = [
                    'username' => '',
                    'password' => encryptionPassword($password),
                    'mobile' => $mobile,
                    'status' => 1,
                ];
                $userId = self::insertGetId($insertData);
                if ($userId > 0) {
                    self::commit();
                    $insertData['id'] = $userId;
                    return $insertData;
                }
                self::rollback();
                return false;
            } catch (PDOException $e) {
                self::rollback();
                return false;
            }
        }
        return false;
    }


    /**
     * 获取用户名是否已存在
     * @author qinlh
     * @since 2022-05-29
     */
    public static function getUserNameIsExistence($mobile='', $userId=0)
    {
        if ($username !== '') {
            if ($userId > 0) {
                $res = self::where('username', $username)->whereNotIn('id', $userId)->count('id');
            } else {
                $res = self::where('username', $username)->count('id');
            }
            if ($res && $res > 0) {
                return true;
            } else {
                return false;
            }
        }
        // return true;
    }

    /**
     * 根据用户名获取用户信息
     * @author qinlh
     * @since 2022-03-21
     */
    public static function getUsernameInfo($username='', $userId=0)
    {
        if ($username && $username !== '') {
            if ($userId > 0) {
                $res = self::where('username', $username)->whereNotIn("id", $userId)->find();
            } else {
                $res = self::where('username', $username)->find();
            }
            if ($res && count((array)$res) > 0) {
                return $res;
            } else {
                return [];
            }
        }
        return [];
    }

    /**
     * 检测token值是否过期或者对不对
     * @param $token
     * @return bool
     */
    public static function checkToken($token)
    {
        if (empty($token)) {
            return false;
        }
        $decryptedToken = ClCrypt::decrypt($token, self::CRYPT_KEY);
        if ($decryptedToken) {
            $tokenData = json_decode($decryptedToken, true);
            if (isset($tokenData['uid'])) {
                $uid = $tokenData['uid'];
                $cacheToken = Rediscache::getInstance()->get('token_' . $uid);
                if($cacheToken && $token === $cacheToken) {
                    return $uid;
                }
            }
        }
        return false;
    }
}
