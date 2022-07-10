<?php
namespace lib;
class ClSms
{
    /**
     * 下面是慕华尚测发送SMS的相关账号信息
     * @var string
     */
    public static $user = "";
    public static $password = "";
    public static $baseurl = "http://www.mb2e.com/Sendutf8.php";

    /**
     * @param $phone
     * @param $content
     * @return bool false:有错误发生 true：消息成功发送
     */
    public static function sendSms($phone, $content)
    {
        $content = '短信验证码是：'.iconv('gbk', 'utf-8', $content)."【慕华尚测】";
        $param = "Mobile=$phone&Content=$content";
        $now = date('YmdHis') . rand(0, 9);
        $url = self::$baseurl . '?CorpID=' . self::$user . '&Pwd=' . self::$password . '&' . $param . '&Cell=&SendTime=&t=' . $now;
        $get_rtn = ClHttp::forCurl($url);
        //返回值头部有隐藏字符  65279
        $get_rtn = trim($get_rtn);
        $if_success = (strlen($get_rtn) > 4) ? 1 : 0;
        return $if_success;
    }
}