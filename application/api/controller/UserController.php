<?php
// +----------------------------------------------------------------------
// | 文件说明：用户 api
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-07-08
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\api\model\Notes;
use app\api\model\User;
use app\api\model\FillingRecord;
use app\api\model\MyProduct;
use think\Request;
use think\Cookie;
use think\Config;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\ClFile;
use ClassLibrary\ClString;
use ClassLibrary\ClHttp;
use ClassLibrary\ClImage;
use ClassLibrary\ClDate;
use ClassLibrary\CLAwsUpload;
use lib\Filterstring;
use think\Controller;
use RequestService\RequestService;

class UserController extends BaseController
{
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
        $address = $request->request('address', '', 'trim');
        $userId = $request->request('userId', 0, 'intval');
        if ($address == '' && $userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        if($address && $address !== '') {
            $userInfo = User::getUserAddressInfo($address);
        } else {
            $userInfo = User::getUserInfo($userId);
        }
        if($userInfo) {
            // p($favorite_num);
            // if($userInfo && (float)$userInfo['wallet_balance'] <= 0) {
            if(($userInfo || (float)$userInfo['wallet_balance'] <= 0) && $userInfo['address'] !== '') {
                $rewardBalance = User::getUserContractBalance($userInfo['address']);
                if ($rewardBalance) {
                    $userInfo['wallet_balance'] = $rewardBalance;
                    @User::resetUserRewardBalance($address, $rewardBalance);
                }
            }
            $userInfo['total_invest'] = 0;
            $userInfo['total_number'] = 0;
            $UserTotalInvest = MyProduct::getUserTotalInvest($userInfo['id']);
            if($UserTotalInvest) {
                $userInfo['total_invest'] = $UserTotalInvest['total_invest'];
                $userInfo['total_number'] = $UserTotalInvest['total_number'];
            }
            $cumulativeIncomeRes = MyProduct::getAllCumulativeIncome($userInfo['id']);
            $userInfo['cumulative_income'] = $cumulativeIncomeRes;
            $balanceData = MyProduct::getAllProductBalance($userInfo['id']);
            $userInfo['count_balance'] = $balanceData;
            return $this->as_json($userInfo);
        } else {
            return $this->as_json(70001, '添加失败');
        }
    }

     /**
     * 注册账号
     * @author qinlh
     * @since 2022-05-29
     */
    public function createAccount(Request $request) {
        $username = $request->post('username', '', 'trim');
        $password = $request->post('password', '', 'trim');
        $qr_password = $request->post('qr_password', '', 'trim');
        $invite_address = $request->post('invite_address', '', 'trim'); //邀请人地址
        if ($username == '' || $password == '' || $qr_password == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        if($password !== $qr_password) {
            return $this->as_json('70001', 'The two passwords are inconsistent');
        }
        $isExistence = User::getUserNameIsExistence($username);
        if($isExistence) {
            return $this->as_json('10001', '用户名已存在');
        }
        $result = User::createAccount($username, $password, $invite_address);
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
        $username = $request->post('username', '', 'trim');
        $password = $request->post('password', '', 'trim');
        if ($username == '' || $password == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $user = User::checkLogin($username, $password);
        if (is_object($user)) {
            $token = User::getToken($user->id);
            // p($auth_group);
            $return = [
                'token' => $token,
                'address' => $user->address,
                'uid' => $user->id,
                'user_name' => $user->username,
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
     * 修改用户信息
     * @author qinlh
     * @since 2022-05-22
     */
    public function saveUserInfo(Request $request)
    {
        $address = $request->post('address', '', 'trim');
        $userId = $request->post('userId', 0, 'intval');
        $username = $request->post('username', '', 'trim');
        $password = $request->post('password', '', 'trim');
        $nickname = $request->post('nickname', '', 'trim');
        $images_key = $request->post('images_key', '', 'trim');
        if ($address == '' && $userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        if($address && $address !== '') {
            $userInfo = User::getUserAddressInfo($address);
        } else {
            $userInfo = User::getUserInfo($userId);
        }
        $avatar_img_url = "";
        if ($images_key && $images_key !== '') {
            if($images_key == "avatar") {
                $fileResult        = ClFile::uploadDealClient(); //上传文件
                if ($fileResult && $fileResult['result']) { //如果本地文件上传成功的话 开始上传到Aws 云端文件存储
                    $file_size = input('post.file_size', $_FILES['file']['size'], 'trim');
                    $file_name = input('post.file_name', $_FILES['file']['name'], 'trim,strval');
                    $root_path = sprintf('%s/', 'Swanlake/avatar');
                    $save_file = $root_path . date('His') . '_' . (ClString::toCrc32($file_size . $file_name)) . '_' . ClFile::getSuffix($userInfo['username']) . ClFile::getSuffix($file_name);
                    $awsResult = CLAwsUpload::AwsS3PutObject($fileResult['file'], $file_name, $file_size, $save_file);
                    if ($awsResult && $awsResult['result']) {
                        $avatar_img_url = $awsResult['file'];
                        @unlink($fileResult['file']); //删除旧目录下的文件
                    } else {
                        return $this->as_json('70001', '$2 图片上传失败');
                    }
                } else {
                    return $this->as_json('70001', '$1 图片上传失败');
                }
            }
        }
        $updateArr = [];
        if($nickname && $nickname !== '') {
            $updateArr = ['nickname' => $nickname];
        }
        if($username && $username !== '') {
            if($userInfo['username'] && $userInfo['username'] !== '') {
                return $this->as_json('10002', '该用户已经绑定用户名，不能重复绑定');
            }
            $res = User::getUsernameInfo($username);
            if($res) {
                if($res['address'] && $res['address'] !== '') {
                    return $this->as_json('10002', '用户名已被绑定');
                } else {
                    return $this->as_json('10001', '用户名已存在');
                }
            }
            $updateArr = ['username' => $username];
        }
        if($password && $password !== '') {
            $updateArr = ['password' => encryptionPassword($password)];
        }
        if($avatar_img_url && $avatar_img_url !== '') {
            $updateArr = ['avatar' => $avatar_img_url];
        }
        $result = User::saveUserInfo($userInfo['id'], $updateArr);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, '添加失败');
        }
    }

    /**
     * 根据钱包地址获取用户信息
     * @author qinlh
     * @since 2022-05-29
     */
    public function getUserAddressInfo(Request $request) {
        $address = $request->request('address', '', 'trim');
        $invite_address = $request->request('invite_address', '', 'trim'); //邀请人地址
        if($address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::getUserAddressInfo($address, $invite_address);
        if($result) {
            $result['is_admin'] = getAdminAddress($address);
            return $this->as_json($result);
        } else {
            return $this->as_json(70001, 'Error');
        }
    }

    /**
     * [setUserLocalBalance] [修改用户本地余额 外部调用]
     * @param [$artId] [文章ID]
     * @return [type] [description]
     * @author [qinlh] [WeChat QinLinHui0706]
     */
    public function setUserLocalBalance(Request $request) {
        $address = $request->post('address', '', 'trim');
        $amount = $request->post('amount', '', 'trim');
        $type = $request->post('type', 0, 'intval');
        if ($amount <= 0 || $amount == '' || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::setUserLocalBalance($address, $amount, $type, false);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json('70001', 'Save Error');
        }
    }

    /**
     * 重置用户链上余额 外部调用
     * @author qinlh
     * @since 2022-06-15
     */
    public function resetUserRewardBalance(Request $request) {
        $address = $request->post('address', '', 'trim');
        $amount = $request->post('amount', '', 'trim');
        if ($amount <= 0 || $amount == '' || $address == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        $result = User::resetUserRewardBalance($address, $amount);
        if($result) {
            return $this->as_json($result);
        } else {
            return $this->as_json('70001', 'Reset Error');
        }
    }

    /**
     * 获取我邀请的数据列表
     * @author qinlh
     * @since 2022-06-29
     */
    public function getiIinviteList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limit = $request->request('limit', 20, 'intval');
        $userId = $request->request('userId', 0, 'intval');
        if ($userId <= 0) {
            return $this->as_json('70001', 'Missing parameters');
        }
        $where = [];
        $order = 'a.time desc';
        $where['a.fid'] = $userId;
        $result = UserLevel::getMyInvitationList($where, $page, $limit, $order);
        return $this->as_json($result);
    }
}
