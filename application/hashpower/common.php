<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author:
// +----------------------------------------------------------------------

// 应用公共文件

/**
* 打印函数 打印关于变量的易于理解的信息。
* @param [type] $var [description]
* @return [type] [description]
*/
if (! function_exists('p')) {
    function p($var)
    {
        echo "<pre>";
        print_r($var);
        exit;
    }
}
