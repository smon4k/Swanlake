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


 /**
  * POST GET请求数据
 * @return [type] [CurlRequest]
 * @param String $url     请求的地址
 * @param Array  $header  自定义的header数据 $header = array('x:y','language:zh','region:GZ');
 * @param Array  $content POST的数据 $content = array('name' => 'wumian');
 * @param Array  $backHeader 返回数据是否返回header 0不反回 1返回
 * @param Array  $cookie 携带的cookie
 * @author [qinlh] [WeChat QinLinHui0706]
 */
function CurlRequest($url, $header, $content=array(), $backHeader=0, $cookie='')
{
    $ch = curl_init();
    if (substr($url, 0, 5)=='https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在
    }
    if (!isset($header[0])) {//将索引数组转为键值数组
        foreach ($header as $hk=>$hv) {
            unset($header[$hk]);
            $header[]=$hk.':'.$hv;
        }
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POST, true);
    if (count((array)$content)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    }
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($ch, CURLOPT_HEADER, $backHeader); // 显示返回的Header区域内容
    $response = curl_exec($ch);
    if ($error=curl_error($ch)) {
        die($error);
    }
    curl_close($ch);
    return $response;
}

    /**
    * 函数的含义说明：CURL发送get请求    获取数据
    * @access public
    * @param str $url
    * @return  返回json数据
     */
    function CurlGetRequest($url, $header=[], $cookie='')
    {
        $curl = curl_init(); // 启动一个CURL会话
        if (!isset($header[0])) {//将索引数组转为键值数组
            foreach ($header as $hk=>$hv) {
                unset($header[$hk]);
                $header[]=$hk.':'.$hv;
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        if (!empty($cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在
        $output = curl_exec($curl);     //返回api的json对象
        //关闭URL请求
        curl_close($curl);
        return $output;    //返回json对象
    }

    /**
    * 模拟post请求,既可以是http也可以是https
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    function postCurl($url, $data, $https = true)
    {
        $curl = curl_init(); //启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        if ($https) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检测
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); //从证书中检查SSL加密算法是否存在
        }
        if(getIsCli()) {
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'); //模拟用户使用的浏览器
        } else {
            curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); //模拟用户使用的浏览器
        }
        curl_setopt($curl, CURLOPT_POST, 1); //发送一个常规的post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); //post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); //设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); //显示返回的header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); //执行操作
        curl_close($curl); //关闭curl会话
        if ($tmpInfo) {//返回数据
            return json_decode($tmpInfo, true);
        }
        return false;
    }

    /**
    * 日志记录
    * @return [type] [description]
    * @param  [post] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    function logger($log_content)
    {
        if (isset($_SERVER['HTTP_APPNAME'])) {   //SAE
            sae_set_display_errors(false);
            sae_debug($log_content);
            sae_set_display_errors(true);
        } else { //LOCAL
            $max_size = 1000000;
            $log_filename = "log.xml";
            if (file_exists($log_filename) and (abs(filesize($log_filename)) > $max_size)) {
                unlink($log_filename);
            }
            file_put_contents($log_filename, date('Y-m-d H:i:s')." ".$log_content."\r\n", FILE_APPEND);
        }
    }

    /**
    * 获取内存占用大小
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    function convert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    /**
     * 循环删除目录和文件
     * @param string $dir_name 目录名
     * @return bool
     */
    function delete_dir_file($dir_name)
    {
        $result = false;
        if (is_dir($dir_name)) { //检查指定的文件是否是一个目录
            if ($handle = opendir($dir_name)) { //打开目录读取内容
                while (false !== ($item = readdir($handle))) { //读取内容
                    if ($item != '.' && $item != '..') {
                        if (is_dir($dir_name . DS . $item)) {
                            delete_dir_file($dir_name . DS . $item);
                        } else {
                            unlink($dir_name . DS . $item); //删除文件
                        }
                    }
                }
                closedir($handle); //打开一个目录，读取它的内容，然后关闭
                if (rmdir($dir_name)) { //删除空白目录
                    $result = true;
                }
            }
        }
        return $result;
    }

    /**
     * 获取客户端真实IP
     * @param null
     * @return string
     */
    function getRealIp()
    {
        $ip=false;
        //客户端IP 或 NONE
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        }
        //多重代理服务器下的客户端真实IP地址（可能伪造）,如果没有使用代理，此字段为空
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
            if ($ip) {
                array_unshift($ips, $ip);
                $ip = false;
            }
            for ($i = 0; $i < count((array)$ips); $i++) {
                if (!preg_match("/(10│172.16│192.168)./", $ips[$i])) {
                    $ip = $ips[$i];
                    break;
                }
            }
        }
        //客户端IP 或 (最后一个)代理服务器 IP
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }

    /**
     * 获取当前网址协议
     * @param null
     * @return string
     */
    function getHttpType()
    {
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        return $http_type;
    }

    /**
     * 判断是是否命令行模式
     * @author qinlh
     * @since 2021-12-08
     */
    function getIsCli()
    {
        return preg_match("/cli/i", php_sapi_name()) ? true : false;
    }

    /**
    * 字符串首尾留N位,中间替换成*号
    * @param string $string 需要替换的字符串
    * @param string $start 开始的保留几位
    * @param string $end 最后保留几位
    * @author qinlh
    * @since 2021-12-08
    */
    function strReplace($string, $start, $end)
    {	
        $strlen = mb_strlen($string, 'UTF-8');//获取字符串长度
        $firstStr = mb_substr($string, 0, $start,'UTF-8');//获取第一位
        $lastStr = mb_substr($string, -1, $end, 'UTF-8');//获取最后一位
        return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($string, 'utf-8') -1) : $firstStr . str_repeat("*", $strlen - 2) . $lastStr;
            
    }


    /**
     * 密码加密方法
     * @param string $pw       要加密的原始密码
     * @param string $authCode 加密字符串
     * @return string
     */
    function encryptionPassword($pw, $authCode = '')
    {
        if (empty($authCode)) {
            $authCode = config('auth_code');
        }
        $result = md5(md5($authCode . $pw));
        return $result;
    }

    /**
     * 输出信息
     */
    function getEnvs() {
        if(getIsCli()) {
            return 'prod';
        }
        if ($_SERVER['HTTP_HOST'] === 'www.swanlake.club') { //正式环境
            return 'prod';
        } else {
            return 'dev';
        }
    }

    /**
     * 获取地址是否管理员地址
     * @author qinlh
     * @since 2022-07-13
     */
    function getAdminAddress($address='') {
        $arr = config('admin_address_arr');
        $isAdmin = false;
        if($arr && count((array)$arr) > 0) {
            foreach ($arr as $key => $val) {
                if(strtolower($val) === strtolower($address)) {
                    $isAdmin = true;
                }
            }
        }
        return $isAdmin;
    }

    function valueInterceptStr($value=0, $num=4) {
        $pat = "/(\d+\.\d{".$num."})\d*/"; 
        $str = preg_replace($pat,"\${1}",$value);
        return $str;
    }

    function sprintfNum($num, $number=1) {
        $numberA = $number + 1;
        return substr(sprintf("%.".$numberA."f", $num),0,-1);
    }

    /**
     * 将科学计数法的数字转换为正常的数字
     * 为了将数字处理完美一些，使用部分正则是可以接受的
     * @author loveyu
     * @param string $number
     * @return string
     */
    function convert_scientific_number_to_normal($number)
    {
        if(stripos($number, 'e') === false) {
            //判断是否为科学计数法
            return $number;
        }
        if(!preg_match("/^([\\d.]+)[eE]([\\d\\-\\+]+)$/",str_replace(array(" ", ","), "", trim($number)), $matches)) { //提取科学计数法中有效的数据，无法处理则直接返回
            return $number;
        }

        //对数字前后的0和点进行处理，防止数据干扰，实际上正确的科学计数法没有这个问题
        $data = preg_replace(array("/^[0]+/"), "", rtrim($matches[1], "0."));
        $length = (int)$matches[2];
        if($data[0] == ".") {
            //由于最前面的0可能被替换掉了，这里是小数要将0补齐
            $data = "0{$data}";
        }
        //这里有一种特殊可能，无需处理
        if($length == 0) {
            return $data;
        }

        //记住当前小数点的位置，用于判断左右移动
        $dot_position = strpos($data, ".");
        if($dot_position === false) {
            $dot_position = strlen($data);
        }
        //正式数据处理中，是不需要点号的，最后输出时会添加上去
        $data = str_replace(".", "", $data);
        if($length > 0) {
            //如果科学计数长度大于0
            //获取要添加0的个数，并在数据后面补充
            $repeat_length = $length - (strlen($data) - $dot_position);
            if($repeat_length > 0) {
                $data .= str_repeat('0', $repeat_length);
            }
            //小数点向后移n位
            $dot_position += $length;
            $data = ltrim(substr($data, 0, $dot_position), "0").".".substr($data, $dot_position);
        } elseif($length < 0) {
            //当前是一个负数
            //获取要重复的0的个数
            $repeat_length = abs($length) - $dot_position;
            if($repeat_length > 0) {
                //这里的值可能是小于0的数，由于小数点过长
                $data = str_repeat('0', $repeat_length).$data;
            }
            $dot_position += $length;//此处length为负数，直接操作
            if($dot_position < 1) {
                //补充数据处理，如果当前位置小于0则表示无需处理，直接补小数点即可
                $data = ".{$data}";
            } else {
                $data = substr($data, 0, $dot_position).".".substr($data, $dot_position);
            }
        }
        if($data[0] == ".") {
            //数据补0
            $data = "0{$data}";
        }
        return trim($data, ".");
    }

    // 时间日期转时间戳格式，精确到毫秒，
    function get_data_format($time) {
        list($usec, $sec) = explode(".", $time);
        $date = strtotime($usec);
        $return_data = str_pad($date.$sec,13,"0",STR_PAD_RIGHT); //不足13位。右边补0
        return $return_data;
    }
