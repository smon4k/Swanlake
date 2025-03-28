<?php

namespace app\api\controller;

use app\api\model\UserOkx;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\ClString;
use ClassLibrary\ClVerify;
use think\Controller;
use think\Cookie;
use think\Cache;
use think\Request;

class QuantifybaseController extends Controller
{
    protected static $_uid = 0;
    public function _initialize()
    {
        $token = request()->header('authorization');
        $path = request()->pathinfo();
        $excludedRoutes = [
            'api/userokx/sendVerificationCode',
            'api/userokx/login',
            'api/userokx/createAccount',
            'api/userokx/checkVerificationCode',
            'Api/QuantifyAccount/deleteQuantityAccount'
            // 'Api/QuantifyAccount/addQuantityAccount'
            // 'Api/QuantifyAccount/test'
            // 'api/userokx/index',
        ];
        if (!in_array($path, $excludedRoutes)) {
            $uid = UserOkx::checkToken($token);
            self::$_uid = $uid;
            if (!$uid) {
                exit(json([
                    'code' => '80001',
                    'message' => 'Token verification failed'
                ])->send());
            }
        }
    }

    //缓存相关
    protected static function setCache($cache_key, $cache_content, $cache_time = 3600) {
        $cache_key = implode('.',$cache_key);
        Cache::set($cache_key, $cache_content, $cache_time * 8);
    }

    protected static function getCache($cache_key){
        $res = [];
        $cache_key = implode('.',$cache_key);
        $res = Cache::get($cache_key);
        return $res;
    }

    protected static function delCache($cache_key){
        $cache_key = implode('.',$cache_key);
        $res = Cache::rm($cache_key);
        return $res;
    }

    /**
     * @Author sunbobin
     *
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return \think\response\Json
     */
    protected function as_json($code = 10000, $msg = 'ok', $data = [])
    {
        if (count((array)func_get_args()) == 1) {
            return json(['code' => 10000, 'msg' => 'ok', 'data' => $code]);
        } else {
            return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
        }
    }

    /**
     * 返回信息
     * @param int $code 返回码
     * @param array $data 返回的值
     * @param string $example 例子，用于自动生成api文档
     * @param bool $is_log
     * @return \think\response\Json|\think\response\Jsonp
     */
    protected function ar($code, $data = [], $example = '', $is_log = false) {
        $status = sprintf('%s/%s/%s/%s', request()->module(), request()->controller(), request()->action(), $code);
        //格式化
        $status = ClString::toArray($status);
        foreach ($status as $k_status => $v_status) {
            if (ClVerify::isAlphaCapital($v_status)) {
                $status[$k_status] = '_' . strtolower($v_status);
            }
        }
        //转换为字符串
        $status = implode('', $status);
        $status = str_replace('/_', '/', $status);
        $data   = is_array($data) ? $data : [$data];
        return json_return(array_merge([
            'status'      => $status,
            'status_code' => $code
        ], $data), $is_log);
    }

    /**
    * 对象转数组格式
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    protected static function ObjectToArray(&$object) {
        $object = json_decode( json_encode( $object),true);
        return  $object;
    }
}
