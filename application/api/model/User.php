<?php
// +----------------------------------------------------------------------
// | 文件说明：用户 笔记 Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-05-18
// +----------------------------------------------------------------------
namespace app\api\model;

use think\Model;
use lib\ClCrypt;
use think\Config;
use think\Cache;
use think\Loader;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use ClassLibrary\ClFile;
use ClassLibrary\ClString;
use ClassLibrary\CLFfmpeg;
use ClassLibrary\CLAwsUpload;
use RequestService\RequestService;

class User extends Base
{
    /**
     * 获取用户信息
     * @author qinlh
     * @since 2022-03-18
     */
    public static function getUserInfo($userId=0)
    {
        if ($userId > 0) {
            $userinfo = self::where('id', $userId)->find();
            if ($userinfo && count((array)$userinfo) > 0) {
                return $userinfo->toArray();
            } 
            // else {
            //     $userinfo = self::insertUserData($address);
            //     return $userinfo;
            // }
        }
        return [];
    }

    /**
     * 获取用户是否存在
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function getUserAddress($address='')
    {
        if ($address && $address !== '') {
            $res = self::where("address", $address)->find();
            if ($res) {
                return $res['id'];
            } else {
                return 0;
            }
        }
        return 0;
    }

    /**
     * 检查登录
     *
     * @param $user_name
     * @param $password
     * @return mixed
     */
    public static function checkLogin($user_name='', $password='')
    {
        if($user_name !== '' && $password !== '') {
            $user_info = self::where('username', $user_name)->find();
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
     * 查询用户钱包地址是否已绑定
     * @author qinlh
     * @since 2022-06-18
     */
    public static function getAddressIsExit($address) {
        if ($address && $address !== '') {
            $userinfo = self::where('address', $address)->find();
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
     * @since 2022-03-18
     */
    public static function getUserAddressInfo($address='')
    {
        if ($address && $address !== '') {
            $userinfo = self::where('address', $address)->find();
            if ($userinfo && count((array)$userinfo) > 0) {
                return $userinfo->toArray();
            } else {
                // $userinfo = self::insertUserData($address, $invite_address);
                $userinfo = self::insertUserData($address);
                return $userinfo;
            }
        }
        return [];
    }

    /**
     * 获取单条用户信息
     * @author qinlh
     * @since 2022-03-22
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
     * @since 2022-03-18
     */
    public static function insertUserData($address='', $invite_address='')
    {
        if ($address !== '') {
            self::startTrans();
            try {
                $insertData = [
                    'username' => '',
                    'password' => '',
                    'address' => $address,
                    'nickname' => '',
                    'introduction' => '',
                    'avatar' => '',
                    'sex' => 0,
                    'birthday' => '',
                    'background_img' => '',
                    'time' => date('Y-m-d H:i:s'),
                    'local_balance' => 0,
                    'status' => 1
                ];
                $userId = self::insertGetId($insertData);
                if($userId > 0) {
                    // if($invite_address && $invite_address !== '') { //如果含有邀请人地址的话 创建推荐关系
                    //     self::createRecommend($userId, $invite_address);
                    // } 
                    self::commit();
                    $insertData['id'] = $userId;
                    return $insertData;
                }
                self::rollback();
                return false;
            } catch ( PDOException $e) {
                p($e);
                self::rollback();
                return false;
            }
        }
        return false;
    }

    /**
     * 添加邀请用户日志记录
     * @Author qinlh
     * @param Request $request
     * @return \think\response\int
     */
    public static function addUserInviteLog($user_id='', $invitees_id='', $hash='')
    {
        if ($invitees_id !== '') {
            self::name("invite_log")->insert(['user_id'=>$invitees_id, 'invitees_id'=>$user_id, 'ip'=> getRealIp(),'time' => date("Y-m-d H:i:s"), 'hash' => $hash]);
        }
        return true;
    }

    /**
     * 创建推荐关系
     * @author qinlh
     * @since 2022-06-27
     */
    public static function createRecommend($user_id='', $re_address='') {
        $invitees_id = self::getUserAddress($re_address); //获取邀请人id
        if ($invitees_id <= 0) { //如果邀请人不存在
            return false;
            // $invitees_id = self::insertUserData($re_address);
        }
        if ($user_id > 0 && $invitees_id > 0) {
            // UserLevel::saveUserLevel($user_id, 0, 1); //先判断是否需要添加一级用户信息
            if ($invitees_id !== $user_id) {  //邀请用户和被邀请用户不能相同
                // p($user_id);
                // p($user_id);
                $userLevelId = UserLevel::saveUserLevel($user_id, $invitees_id, 2); //先判断是否需要添加二级用户信息
                if ($userLevelId) {
                    $inviteLog = self::addUserInviteLog($invitees_id, $user_id);
                    if($inviteLog) {
                        return true;
                    }
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 修改用户余额
     * @params address 用户地址
     * @params amount 数量
     * @params type 1：加 2：减
     * @author qinlh
     * @since 2022-06-14
     */
    public static function setUserLocalBalance($address='', $amount=0, $type=0)
    {
        if ($address && $address !== '' && $amount > 0 && $type > 0) {
            $userInfo = self::getUserAddressInfo($address);
            if($userInfo && count((array)$userInfo)) {
                if($type == 1) {
                    $res = self::where('address', $address)->setInc('local_balance', $amount);
                }
                if($type == 2) {
                    $res = self::where('address', $address)->setDec('local_balance', $amount);
                }
                if($res) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取用户名是否已存在
     * @author qinlh
     * @since 2022-05-29
     */
    public static function getUserNameIsExistence($username='', $userId=0) {
        if($username !== '') {
            if($userId > 0) {
                $res = self::where('username', $username)->whereNotIn('id', $userId)->count('id');
            } else {
                $res = self::where('username', $username)->count('id');
            }
            if($res && $res > 0) {
                return true;
            } else {
                return false;
            }
        }
        // return true;
    }



    /**
     * 开始充提中 修改状态
     * status 1：充提中 0：非充提中
     * isGsGetBalance 是否通知GS更新余额 true：更新 false：不更新
     * @author qinlh
     * @since 2022-03-19
     */
    public static function saveNotifyStatus($address='', $status=0, $isGsGetBalance=true)
    {
        if ($address !== '' && $status <= 1) {
            $res = self::where('address', $address)->setField('dw_status', $status);
            if (false !== $res) {
                if ($status == 0 && $isGsGetBalance) { //如果是非重提状态 且 允许通知NFT更新打赏余额
                    $rewardBalance = self::getUserContractBalance($address);
                    if ($rewardBalance) {
                        @self::resetUserRewardBalance($address, $rewardBalance);
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * 获取余额 调用方合约获取
     * @author qinlh
     * @since 2022-03-21
     */
    public static function getUserContractBalance($address='')
    {
        $balance = 0;
        if ($address !== '') {
            $params = array('address' => $address);
            $response_string = RequestService::doJsonCurlPost(Config::get('www_reptile_game_filling').Config::get('reptile_service')['filling_uri'], json_encode($params));
            $balance = (float)json_decode($response_string, true);
            if ($balance) {
                return $balance;
            }
        }
        return 0;
    }

    /**
     * 重置用户链上余额
     * @author qinlh
     * @since 2022-04-25
     */
    public static function resetUserRewardBalance($address='', $balance=0)
    {
        if ($address && $address !== '') {
            return self::where('address', $address)->update(['wallet_balance'=>$balance]);
        }
    }

    /**
     * 获取用户余额
     * @author qinlh
     * @since 2022-06-23
     */
    public static function getUserBalance($userId=0) {
        $balance = 0;
        if($userId > 0) {
            $wallet_balance = 0;
            $local_balance = 0;
            $userInfo = self::getUserInfo($userId);
            if($userInfo['wallet_balance'] <= 0) {
                $wallet_balance = self::getUserContractBalance($userInfo['address']);
            } else {
                $wallet_balance = $userInfo['wallet_balance'];
            }
            if($userInfo['local_balance'] > 0) {
                $local_balance = $userInfo['local_balance'];
            }
            $balance = (float)$wallet_balance + (float)$local_balance;
        }
        return $balance;
    }

    /**
     * 获取用户信息
     * 获取用户余额
     * 获取用户是否有提取进行中的记录
     * 获取用户是否打赏中
     * @author qinlh
     * @since 2022-03-18
     */
    public static function getRewardUserInfo($address='')
    {
        if ($address !== '') {
            $result = [];
            $userinfo = self::where('address', $address)->find();
            if ($userinfo && count($userinfo) > 0) {
                $result = $userinfo->toArray();
            } else {
                $userinfo = self::insertUserData($address);
                $result = $userinfo;
            }
            $result['status'] = $userinfo['dw_status'];
            if ($result && count($result) > 0) {
                $resBlanceArr = FillingRecord::getUserBalance($address);
                // p($resBlanceArr);
                $result['gsBalance'] = $resBlanceArr['gsBalance'];
                $result['csBalance'] = $resBlanceArr['csBalance'];
                // $result['isGame'] = self::getIsInTheGame($address);
                $isDeWithdrawRes = FillingRecord::getUserIsInWithdraw($address); //是否充提中的记录
                $result['isDeWith'] = false; //是否充提中的记录
                $result['isDeWithStatusId'] = 0; //正在充提中的状态id
                $result['isDeWithType'] = '';
                $result['isDeWithHash'] = '';
                // p($isDeWithdrawRes);
                if ($isDeWithdrawRes && count($isDeWithdrawRes) > 0) {
                    $result['isDeWith'] = true;
                    $result['isDeWithStatusId'] = $isDeWithdrawRes['id'];
                    $result['isDeWithType'] = $isDeWithdrawRes['type'];
                    $result['isDeWithHash'] = $isDeWithdrawRes['hash'];
                }
                return $result;
            }
        }
        return [];
    }
    
}
