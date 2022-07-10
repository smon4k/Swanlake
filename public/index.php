<?php
header('Content-Type:text/html;charset=utf-8'); 
header("Access-Control-Allow-Origin:*");
header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers:DNT,X-Mx-ReqToken,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type, Accept-Language, Origin, Accept-Encoding, Authorization");
if(isset($_SERVER['REQUEST_METHOD'])) {
    if(strtoupper($_SERVER['REQUEST_METHOD'])== 'OPTIONS'){ // 遇到options直接返回
        exit;
    }
}

// $a = 5;
// print_r('%2d\n', $a--);die;

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]

// 定义应用目录
define('DOCUMENT_ROOT_PATH', __DIR__);

define('APP_PATH', DOCUMENT_ROOT_PATH . '/../application/');
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
