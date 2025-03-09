<?php
namespace lib;

class ClSms
{
    private $info = [
        'name'        => 'MobileCodeDemo',
        'title'       => '手机验证码演示插件',
        'description' => '手机验证码演示插件',
        'status'      => 1,
        'author'      => '',
        'version'     => '1.0'
    ];

    private $statusStr = array(
        "0" => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词",
        "51" => "手机号码不正确"
    );
    
    public static function sendSms($mobile, $code)
    {
        if(empty($mobile) || empty($code)) {
            return [
                'code'     => -1,
                'message' => '参数不全'
            ];
        }
        $smsapi = "http://www.smsbao.com/"; //短信网关
        $user = "bluestake"; //短信平台帐号
        $pass = md5("Encore04"); //短信平台密码
        $content="【阿尔法团队】您的验证码是".$code.",在5分钟内有效";//要发送的短信内容
        // $mobile = urlencode("+".str_replace("-","",$mobile));
        $phone = str_replace("86-","",$mobile);
        $sendurl = $smsapi."sms?u=".$user."&p=".$pass."&m=".$phone."&c=".urlencode($content);
        $return = self::curl($sendurl);
        if($return == 0) {
            $result = [
                'code'     => $return,
                'message' => '发送成功'
            ];
        } else {
            $result = [
                'code'     => $return,
                'message' => $this->statusStr[$return]
            ];
        }
        return $result;
    }

    /**  
    * 函数的含义说明：CURL发送get请求    获取数据
    * @access public 
    * @param str $url
    * @return  返回json数据
     */ 
    public static function curl($url, $method='GET', $postdata=[]) {
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url);
		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
		}
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_HEADER,0);
		curl_setopt($ch, CURLOPT_TIMEOUT,60);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);  
		curl_setopt ($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
		]);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		return $output;
	}
}