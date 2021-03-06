<?php
/**
 * Created by PhpStorm.
 * User: kejing.song
 * Email: 597481334@qq.com
 * Date: 2015/7/3
 * Time: 11:40
 */

namespace ClassLibrary;

use think\Exception;

/**
 * Class ClHttp 类库Http
 * @package Common\Common
 */
class ClHttp {

    /**
     * http结果格式
     */
    const V_HTTP_RESULT_JSON = 'json';

    /**
     * http debug
     */
    const V_HTTP_IS_DEBUG_YES = true;

    /**
     * http debug
     */
    const V_HTTP_IS_DEBUG_NO = false;

    /**
     * 获取服务器地址
     * @param bool $with_protocol
     * @return string
     */
    public static function getServerDomain($with_protocol = true) {
        $protocol = '';
        if ($with_protocol) {
            if (!isset($_SERVER['SERVER_PORT'])) {
                $_SERVER['SERVER_PORT'] = 80;
            }
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        }
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = '127.0.0.1';
        }
        return $protocol . $_SERVER['HTTP_HOST'];
    }

    /**
     * 获取当前请求路径
     * @return string
     */
    public static function getCurrentRequest() {
        return self::getServerDomain() . $_SERVER['REQUEST_URI'];
    }

    /**
     * 非阻塞请求
     * Nginx应做如下配置修改：
     * fastcgi_send_timeout 30000; #nginx进程向fastcgi进程发送request的整个过程的超时时间
     * fastcgi_read_timeout 30000; #fastcgi进程向nginx进程发送response的整个过程的超时时间
     * proxy_ignore_client_abort on; #不允许代理端主动关闭连接
     * @param string $ip 服务器ip地址 192.168.1.168
     * @param string $url 请求url http://www.sda1.test/Index/delay
     * @param array $params 参数数组 array('name' => '张三')
     * @param int $timeout 超时时间
     * @return bool
     */
    public static function noBlockingRequest($ip, $url, $params = array(), $timeout = 15) {
        if (config('app_debug')) {
            log_info($ip, $url, $params);
        }
        $matches = parse_url($url);
        !isset($matches['host']) && $matches['host'] = '';
        !isset($matches['path']) && $matches['path'] = '';
        !isset($matches['query']) && $matches['query'] = '';
        !isset($matches['port']) && $matches['port'] = '';
        $host   = $matches['host'];
        $path   = $matches['path'] ? $matches['path'] . ($matches['query'] ? '?' . $matches['query'] : '') : '/';
        $port   = !empty($matches['port']) ? $matches['port'] : 80;
        $cookie = '';
        $post   = '';
        if (is_array($params) && count($params) > 0) {
            foreach ($params as $name => $value) {
                if (empty($post)) {
                    $post .= $name . '=' . $value;
                } else {
                    $post .= '&' . $name . '=' . $value;
                }
            }
        }
        if ($post) {
            if (is_array($post)) {
                $post = http_build_query($post);
            }
            $out = "POST $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
            $out .= "Accept-Language: zh-cn\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $out .= "Host: $host\r\n";
            $out .= 'Content-Length: ' . strlen($post) . "\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cache-Control: no-cache\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
            $out .= $post;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
            $out .= "Accept-Language: zh-cn\r\n";
            $out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
        }
        $fp = @fsockopen(($ip ? $ip : $host), $port, $err_no, $err_str, $timeout);
        //错误
        if (empty($fp)) {
            log_info('error:', array(
                'ip:'      => $ip,
                'params:'  => $params,
                'timeout:' => $timeout,
                'err_no:'  => $err_no,
                'err_str:' => $err_str
            ));
            return false;
        }
        @stream_set_blocking($fp, 0);
        fwrite($fp, $out);
        fclose($fp);
        unset($fp);
        return true;
    }

    /**
     * http请求
     * @param string $url 请求地址
     * @param array $params 请求参数数组
     * @param int $duration 缓存时间
     * @param bool $is_debug 是否是debug模式
     * @param string $result_type 结果格式
     * @return mixed
     */
    public static function http($url, $params = [], $duration = null, $is_debug = false, $result_type = 'json') {
        $config = ['post' => $params];
        if ($result_type == 'json') {
            $config['content_type'] = 'application/json';
        }
        if ($duration !== null) {
            $key    = ClCache::getKey($url, $params);
            $result = cache($key);
            if ($result === false) {
                $result = self::fsockopenDownload($url, $config);
                if (strtolower($result_type) == 'json') {
                    $result = json_decode($result, true);
                }
                //写入缓存
                cache($key, $result, $duration);
            }
        } else {
            $result = self::fsockopenDownload($url, $config);
            if (strtolower($result_type) == 'json') {
                $result = json_decode($result, true);
            }
        }
        if ($is_debug) {
            log_info('HTTP:', $url, $params, $result);
        }
        return $result;
    }

    /**
     * 删除缓存
     * @param $url
     * @param $params
     */
    public static function httpRc($url, $params) {
        ClCache::remove($url, $params);
    }

    /**
     * https请求
     * @param string $url 请求地址
     * @param array $params 上传文件采用 @文件绝对地址 方式
     * @param bool $debug 是否调试
     * @param int $duration 缓存时间
     * @param string $result_type json/xml
     * @param array $header
     * @param int $timeout 超时时间
     * @return mixed
     */
    public static function request($url, $params = [], $debug = false, $duration = 0, $result_type = 'json', $header = [], $timeout = 30) {
        if ($duration > 0) {
            $key    = ClCache::getKey($url, $params);
            $result = cache($key);
            if ($result !== false) {
                if (strtolower($result_type) == 'json') {
                    $result = json_decode($result, true);
                } else if (strtolower($result_type) == 'xml') {
                    $result = ClXml::toArray($result);
                }
                return $result;
            }
        }
        $ch = curl_init();
        //处理包含文件的参数
        $is_post_file = false;
        if (is_array($params)) {
            foreach ($params as $param_index => $param_value) {
                if (!is_string($param_value)) {
                    continue;
                }
                if (strpos($param_value, '@') === 0) {
                    $is_post_file = true;
                    $param_value  = substr($param_value, 1);
                    //如果存在文件
                    if (is_file($param_value)) {
                        //兼容5.0-5.6版本的curl
                        if (class_exists('\CURLFile')) {
                            $params[$param_index] = new \CURLFile(realpath($param_value), mime_content_type($param_value), ClFile::getName($param_value, true));
                        } else {
                            if (defined('CURLOPT_SAFE_UPLOAD')) {
                                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, FALSE);
                            }
                        }
                    }
                }
            }
        }
        if (strpos($url, 'https') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        //文件上传不可build
        curl_setopt($ch, CURLOPT_POSTFIELDS, $is_post_file ? $params : (is_array($params) ? http_build_query($params) : $params));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $response = curl_exec($ch);
        if ($error = curl_error($ch)) {
            die($error);
        }
        // 打印错误信息
        if ($debug) {
            log_info('info', curl_getinfo($ch));
            log_info('error', curl_error($ch));
            log_info('response', $response);
        }
        curl_close($ch);
        if ($duration > 0) {
            $key = ClCache::getKey($url, $params);
            //缓存
            cache($key, $response, $duration);
        }
        if (strtolower($result_type) == 'json') {
            $response = json_decode($response, true);
        } else if (strtolower($result_type) == 'xml') {
            $response = ClXml::toArray($response);
        }
        return $response;
    }

    /**
     * 获取服务器根目录
     * @return mixed
     */
    public static function getServerDocumentRoot() {
        return $_SERVER['DOCUMENT_ROOT'];
    }

    /**
     * 模拟登录
     * @param $url
     * @param string $cookie_create_key 生成唯一cookie文件的key值
     * @param $params
     * @param bool $is_post 是否是post请求
     * @return mixed
     */
    public static function simulateLogin($url, $cookie_create_key, $params, $is_post = true) {
        $curl = curl_init();//初始化curl模块
        curl_setopt($curl, CURLOPT_URL, $url);//登录提交的地址
        curl_setopt($curl, CURLOPT_HEADER, 0);//是否显示头信息
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//是否自动显示返回的信息
        curl_setopt($curl, CURLOPT_COOKIEJAR, self::simulateGetCookieFile($cookie_create_key)); //设置Cookie信息保存在指定的文件中
        curl_setopt($curl, CURLOPT_POST, ($is_post) ? 1 : 0);//post方式提交
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));//要提交的信息
        //浏览器
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.80 Safari/537.36 Core/1.47.640.400 QQBrowser/9.4.8309.400');
        $rs = curl_exec($curl);//执行cURL
        curl_close($curl);//关闭cURL资源，并且释放系统资源
        return $rs;
    }

    /**
     * 模拟http请求
     * @param $url
     * @param string $cookie 生成唯一cookie文件的key值或者是coockie内容
     * @param $params
     * @param bool $is_post
     * @param string $referer
     * @param bool $is_debug
     * @return mixed
     */
    public static function simulateRequest($url, $cookie, $params, $is_post = true, $referer = '', $is_debug = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (is_file($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, self::simulateGetCookieFile($cookie)); //读取cookie
        } else {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if (!empty($referer)) {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        curl_setopt($ch, CURLOPT_POST, ($is_post) ? 1 : 0);//post方式提交
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));//要提交的信息
        //浏览器
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.80 Safari/537.36 Core/1.47.640.400 QQBrowser/9.4.8309.400');
        $rs = curl_exec($ch); //执行cURL抓取页面内容
        curl_close($ch);
        if ($is_debug) {
            log_info([
                'url'     => $url,
                'cookie'  => $cookie,
                'params'  => $params,
                'is_post' => $is_post,
                'result'  => $rs
            ]);
        }
        return $rs;
    }

    /**
     * 获取cookie文件
     * @param $cookie_create_key
     * @return string
     */
    private static function simulateGetCookieFile($cookie_create_key) {
        $cookie_file = DOCUMENT_ROOT_PATH . '/Cookie/' . $cookie_create_key . '.cookie';
        if (!is_file($cookie_file)) {
            //创建文件夹
            ClFile::dirCreate($cookie_file);
            //创建文件
            $f = fopen($cookie_file, 'w+');
            fclose($f);
        }
        return $cookie_file;
    }

    /**
     * 获取http所有的状态码
     * @return array
     */
    public static function getHttpStatusAllCodes() {
        return array(
            #-------------------------------------
            # Define 100 series http codes (informational)
            #-------------------------------------
            100 => '100 Continue',
            101 => '101 Switching Protocols',

            #-------------------------------------
            # Define 200 series http codes (successful)
            #-------------------------------------
            200 => '200 OK',
            201 => '201 Created',
            202 => '202 Accepted',
            203 => '203 Non-Authoritative Information',
            204 => '204 No Content',
            205 => '205 Reset Content',
            206 => '206 Partial Content',

            #-------------------------------------
            # Define 300 series http codes (redirection)
            #-------------------------------------
            300 => '300 Multiple Choices',
            301 => '301 Moved Permanently',
            302 => '302 Found',
            303 => '303 See Other',
            304 => '304 Not Modified',
            305 => '305 Use Proxy',
            306 => '306 (Unused)',
            307 => '307 Temporary Redirect',

            #-------------------------------------
            # Define 400 series http codes (client error)
            #-------------------------------------
            400 => '400 Bad Request',
            401 => '401 Unauthorized',
            402 => '402 Payment Required',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            406 => '406 Not Acceptable',
            407 => '407 Proxy Authentication Required',
            408 => '408 Request Timeout',
            409 => '409 Conflict',
            410 => '410 Gone',
            411 => '411 Length Required',
            412 => '412 Precondition Failed',
            413 => '413 Request Entity Too Large',
            414 => '414 Request-URI Too Long',
            415 => '415 Unsupported Media Type',
            416 => '416 Requested Range Not Satisfiable',
            417 => '417 Expectation Failed',

            #-------------------------------------
            # Define 500 series http codes (server error)
            #-------------------------------------
            500 => '500 Internal Server Error',
            501 => '501 Not Implemented',
            502 => '502 Bad Gateway',
            503 => '503 Service Unavailable',
            504 => '504 Gateway Timeout',
            505 => '505 HTTP Version Not Supported'
        );
    }

    /**
     * 按code获取内容
     * @param $code
     * @return string
     */
    public static function getHttpStatusByCode($code) {
        $code = intval($code);
        if (empty($code)) {
            return '';
        }
        $codes = self::getHttpStatusAllCodes();
        return isset($codes[$code]) ? $codes[$code] : '';
    }

    /**
     * 伪造ip请求
     * @param $ip
     * @param $port
     * @param $url
     * @return mixed
     */
    public static function httpFake($ip, $port, $url) {
        $ch      = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); //代理认证模式
        curl_setopt($ch, CURLOPT_PROXY, $ip); //代理服务器地址
        curl_setopt($ch, CURLOPT_PROXYPORT, $port); //代理服务器端口
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, ":"); //http代理认证帐号，username:password的格式
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); //使用http代理模式
        $file_contents = curl_exec($ch);
        curl_close($ch);
        return $file_contents;
    }

    /**
     * 使用 fsockopen 通过 HTTP 协议直接访问(采集)远程文件
     * 如果主机或服务器没有开启 CURL 扩展可考虑使用
     * fsockopen 比 CURL 稍慢,但性能稳定
     * @static
     * @access public
     * @param string $url 远程URL
     * @param array $conf 其他配置信息
     *        int   limit 分段读取字符个数
     *        string post  post的内容,字符串或数组,key=value&形式
     *        string cookie 携带cookie访问,该参数是cookie内容
     *        string ip    如果该参数传入,$url将不被使用,ip访问优先
     *        int    timeout 采集超时时间
     *        bool   block 是否阻塞访问,默认为true
     * @return mixed
     */
    public static function fsockopenDownload($url, $conf = []) {
        $return = '';
        if (!is_array($conf)) {
            return $return;
        }
        $matches = parse_url($url);
        !isset($matches['host']) && $matches['host'] = '';
        !isset($matches['path']) && $matches['path'] = '';
        !isset($matches['query']) && $matches['query'] = '';
        !isset($matches['port']) && $matches['port'] = '';
        $host = $matches['host'];
        $path = $matches['path'] ? $matches['path'] . ($matches['query'] ? '?' . $matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;

        $conf_arr   = [
            'limit'        => 0,
            'post'         => '',
            'cookie'       => '',
            'ip'           => '',
            'timeout'      => 15,
            'block'        => TRUE,
            'content_type' => 'application/x-www-form-urlencoded'
        ];
        $conf_arr   = array_merge($conf_arr, $conf);
        $user_agent = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.80 Safari/537.36 Core/1.47.640.400 QQBrowser/9.4.8309.400';
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }
        $limit        = $conf_arr['limit'];
        $post         = $conf_arr['post'];
        $cookie       = $conf_arr['cookie'];
        $ip           = $conf_arr['ip'];
        $timeout      = $conf_arr['timeout'];
        $block        = $conf_arr['block'];
        $content_type = $conf_arr['content_type'];
        if ($post) {
            if (is_array($post)) {
                $post = http_build_query($post);
            }
            $out = "POST $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
            //$out .= "Referer: $boardurl\r\n";
            $out .= "Accept-Language: zh-cn\r\n";
            $out .= "Content-Type: $content_type\r\n";
            $out .= "User-Agent: $user_agent\r\n";
            $out .= "Host: $host\r\n";
            $out .= 'Content-Length: ' . strlen($post) . "\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cache-Control: no-cache\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
            $out .= $post;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
            //$out .= "Referer: $boardurl\r\n";
            $out .= "Accept-Language: zh-cn\r\n";
            $out .= "User-Agent: $user_agent\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
        }
        $fp = @fsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return '';
        } else {
            stream_set_blocking($fp, $block);
            stream_set_timeout($fp, $timeout);
            @fwrite($fp, $out);
            $status = stream_get_meta_data($fp);
            if (!$status['timed_out']) {
                while (!feof($fp)) {
                    if (($header = @fgets($fp)) && ($header == "\r\n" || $header == "\n")) {
                        break;
                    }
                }
                $stop = false;
                while (!feof($fp) && !$stop) {
                    $data   = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
                    $return .= $data;
                    if ($limit) {
                        $limit -= strlen($data);
                        $stop  = $limit <= 0;
                    }
                }
            }
            @fclose($fp);
            return $return;
        }
    }

    /**
     * 分析返回用户网页浏览器名称
     * @param string $user_agent
     * @return array 返回的数组第一个为浏览器名称，第二个是版本号。
     */
    public static function getBrowser($user_agent = '') {
        $sys = empty($user_agent) ? $_SERVER['HTTP_USER_AGENT'] : $user_agent;
        $exp = array();
        if (stripos($sys, 'MQQBrowser')) {
            $exp[0] = "MQQBrowser";
            $exp[1] = "";
        } elseif (stripos($sys, 'QQBrowser')) {
            $exp[0] = "QQBrowser";
            $exp[1] = "";
        } elseif (stripos($sys, "Opera") > 0 || stripos($sys, "OPR/") > 0) {
            $exp[0] = "Opera";
            $exp[1] = "";
        } elseif (stripos($sys, 'Edge')) {
            $exp[0] = "Edge";
            $exp[1] = '';
        } else if (stripos($sys, "Safari/") > 0) {
            $exp[0] = "Safari";
            $exp[1] = "";
        } else if (stripos($sys, "NetCaptor") > 0) {
            $exp[0] = "NetCaptor";
            $exp[1] = "";
        } elseif (stripos($sys, "Firefox/") > 0) {
            preg_match('/Firefox\/([^;)]+)+/i', $sys, $b);
            $exp[0] = 'Mozilla Firefox';
            $exp[1] = $b[1];
        } elseif (stripos($sys, "MAXTHON") > 0) {
            preg_match('/MAXTHON\s+([^;)]+)+/i', $sys, $b);
            preg_match('/MSIE\s+([^;)]+)+/i', $sys, $ie);
            // $exp = $b[0]." (IE".$ie[1].")";
            if (empty($b) || empty($ie)) {
                $exp[0] = "未知浏览器";
                $exp[1] = "";
            } else {
                $exp[0] = $b[0] . " (IE" . $ie[1] . ")";
                $exp[1] = $ie[1];
            }
        } elseif (stripos($sys, "MSIE") > 0) {
            preg_match('/MSIE([^;)]+)+/i', $sys, $ie);
            //$exp = "Internet Explorer ".$ie[1];
            //dump($sys);
            if (empty($ie)) {
                var_dump($user_agent, $ie);
            }
            $exp[0] = "Internet Explorer";
            $exp[1] = $ie[1];
        } elseif (stripos($sys, "Netscape") > 0) {
            $exp[0] = "Netscape";
            $exp[1] = "";
        } elseif (stripos($sys, "Chrome") > 0) {
            $exp[0] = "Chrome";
            $exp[1] = "";
        } elseif (stripos($sys, "rv:11.0") > 0) {
            //ie 11识别
            $exp[0] = "Internet Explorer";
            $exp[1] = 11;
        } else {
            $exp[0] = "未知浏览器";
            $exp[1] = "";
        }
        $exp[1] = trim($exp[1]);
        return $exp;
    }

}
