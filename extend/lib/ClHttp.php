<?php
namespace lib;
/**
 * Class ClHttp 类库Http
 */
class ClHttp
{
    /**
     * 获取服务器地址
     * @return string
     */
    public static function getServerDomain()
    {
        return (is_ssl() ? 'https://' : 'http://')  . $_SERVER['HTTP_HOST'];
    }

    /**
     * 获取当前请求路径
     * @return string
     */
    public static function getCurrentRequest()
    {
        return self::getServerDomain() . $_SERVER['REQUEST_URI'];
    }

    /**
     * for exec curl function
     * 远程获取页面返回内容
     */
    public static function forCurl($url = '')
    {
        $output = array();
        if ($url) {
            $ch = curl_init();
            //设置选项，包括URL
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            //执行并获取HTML文档内容
            $output = curl_exec($ch);
            //释放curl句柄
            curl_close($ch);
        }
        return $output;
    }

    /**
     * 分析返回用户网页浏览器名称
     * @return string 返回的数组第一个为浏览器名称，第二个是版本号。
     * @author gzzyb
     */
    public static function getBrowser()
    {
        $sys = $_SERVER['HTTP_USER_AGENT'];
        $exp = array();

        if (stripos($sys, "NetCaptor") > 0) {
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

            $exp[0] = $b[0] . " (IE" . $ie[1] . ")";
            $exp[1] = $ie[1];
        } elseif (stripos($sys, "MSIE") > 0) {
            preg_match('/MSIE\s+([^;)]+)+/i', $sys, $ie);
            //$exp = "Internet Explorer ".$ie[1];
            //dump($sys);
            $exp[0] = "Internet Explorer";
            $exp[1] = $ie[1];
        } elseif (stripos($sys, "Netscape") > 0) {
            $exp[0] = "Netscape";
            $exp[1] = "";
        } elseif (stripos($sys, "Opera") > 0) {
            $exp[0] = "Opera";
            $exp[1] = "";
        } elseif (stripos($sys, 'Edge')) {
            $exp[0] = "Edge";
            $exp[1] = '';
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
        return $exp;
    }
}
