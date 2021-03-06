<?php

namespace lib;

/**
 * Class ClString 字符串类库
 * @package Common\ClassLibrary
 */
class ClString
{

    /**
     * 计算中文字符串长度，支持中文
     * @param $string
     * @return int
     */
    public static function getLength($string)
    {
        if (is_string($string)) {
            // 将字符串分解为单元
            preg_match_all('/./us', $string, $match);
            // 返回单元个数
            return count($match[0]);
        } else {
            return 0;
        }
    }

    const V_ENCODE_GB2312 = 'GB2312';
    const V_ENCODE_UTF8 = 'UTF-8';
    const V_ENCODE_ASCII = 'ASCII';
    const V_ENCODE_GBK = 'GBK';
    const V_ENCODE_BIG5 = 'BIG5';
    const V_ENCODE_JIS = 'JIS';
    const V_ENCODE_eucjp_win = 'eucjp-win';
    const V_ENCODE_sjis_win = 'sjis-win';
    const V_ENCODE_EUC_JP = 'EUC-JP';

    /**
     * 自动检测内容是编码进行转换
     * @param $string
     * @param $to
     * @return string
     */
    public static function encoding($string, $to)
    {
        $encode_arr = array('GB2312', 'UTF-8', 'ASCII', 'GBK', 'BIG5', 'JIS', 'eucjp-win', 'sjis-win', 'EUC-JP');
        $encoded = mb_detect_encoding($string, $encode_arr);
        $string = mb_convert_encoding($string, $to, $encoded);
        return $string;
    }

    /**
     * 判断字符串是否包含中文
     * @param $str
     * @return bool
     */
    public static function hasChinese($str)
    {
        return preg_match('/[\x7f-\xff]/', $str) === 1;
    }

    /**
     * 获取crc32字符串结果的正整数
     * @param $str
     * @return string 正整数
     */
    public static function toCrc32($str)
    {
        return self::toFloat(sprintf('%u', crc32($str)));
    }

    /**
     * 转换为浮点型
     * @param $str
     * @return float
     */
    public static function toFloat($str)
    {
        return floatval($str);
    }

    const V_AUTH_CODE_TYPE_DECODE = 'DECODE';

    const V_AUTH_CODE_TYPE_ENCODE = 'ENCODE';

    /**
     * 加密解密函数
     *
     * @param string $string 加密字符串
     * @param string $operation DECODE/解密，ENCODE/加密
     * @param string $key 加密密钥
     * @param int $expiry 加密失效时间
     *
     * @return 成功返回string，失败返回''
     */
    public static function authCode($string, $operation = 'DECODE', $key = 'WHO_AM_I', $expiry = 0)
    {
        $ckey_length = 4;
        $key = md5($key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) :
            substr(md5(microtime()), -$ckey_length)) : '';
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) :
            sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 511);
        $rndkey = array();
        for ($i = 0; $i <= 511; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        for ($j = $i = 0; $i < 512; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 512;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 512;
            $j = ($j + $box[$a]) % 512;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 512]));
        }
        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) &&
                substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)
            ) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }

    /**
     * 格式化金钱为万分单位分割
     * @param $money
     * @return string
     */
    public static function moneyFormat($money)
    {
        $money = strval($money);
        $money = strrev($money);
        $return_str = '';
        //是否存在小数
        if (strpos($money, '.')) {
            $money_array = explode('.', $money);
            for ($i = 0; $i < strlen($money_array[1]); $i++) {
                $return_str .= $money_array[1]{$i};
                if (($i + 1) % 4 == 0 && ($i + 1) < strlen($money_array[1])) {
                    $return_str .= ',';
                }
            }
            $return_str = $money_array[0] . '.' . $return_str;
        } else {
            for ($i = 0; $i < strlen($money); $i++) {
                $return_str .= $money{$i};
                if (($i + 1) % 4 == 0 && ($i + 1) < strlen($money)) {
                    $return_str .= ',';
                }
            }
        }
        return strrev($return_str);
    }

    /**
     * 将字符串打散为数组
     * @param $str
     * @return mixed
     */
    public static function toArray($str)
    {
        preg_match_all('/./u', $str, $arr);
        unset($str);
        return $arr[0];
    }

    /**
     * 去除空格，包括中英文
     * @param $string
     * @return string
     */
    public static function spaceTrim($string)
    {
        return trim(preg_replace('/[\s]+/', '', $string));
    }

    /**
     * 多个空格转换为一个空格
     * @param $string
     * @param string $separator
     * @return string
     */
    public static function spaceManyToOne($string, $separator = ' ')
    {
        return trim(preg_replace('/[\s]+/', $separator, $string));
    }

    /**
     * 获取数字字符串
     * @param $s
     * @return string
     */
    public static function getInt($s)
    {
        preg_match_all('/\d+/', $s, $arr);
        $s = '';
        if (!empty($arr[0])) {
            $s = implode('', $arr[0]);
        }
        return $s;
    }

    /**
     * 把格式化的字符串写入变量中，支持数组参数
     * @param $str
     * @param array $value_array
     * @return mixed
     */
    public static function sprintf($str, array $value_array)
    {
        //合并参数
        $value_array = array_merge(array($str), $value_array);
        //执行
        return eval('return sprintf(' . implode("','", $value_array) . ');');
    }

    /**
     * 仅替换一次
     * @param $search
     * @param $replace
     * @param $string
     * @return mixed
     */
    public static function replaceOnce($search, $replace, $string)
    {
        $pos = strpos($string, $search);
        if ($pos === false) {
            return $string;
        }
        return substr_replace($string, $replace, $pos, strlen($search));
    }

    /**
     * 分割字符串，会默认转为小写
     * @param $string :待分割的字符
     * @param $separator_tag :分割标签
     * @param bool $get_before :分割标签之前
     * @param bool $is_include_tag :是否包含分割标签
     * @return string 返回分割后的结果
     */
    public static function split($string, $separator_tag, $get_before = true, $is_include_tag = true)
    {
        if(empty($separator_tag)){
            return $string;
        }
        $lc_str = strtolower($string);
        $marker = strtolower($separator_tag);
        $split_here = strpos($lc_str, $marker);
        if($split_here === false){
            return $string;
        }
        $parsed_string = '';
        if ($get_before) {
            if ($is_include_tag != false) {
                $split_here = strpos($lc_str, $marker) + strlen($marker);
            }
            $parsed_string = substr($string, 0, $split_here);
        } else {
            if ($is_include_tag == false) {
                $split_here = $split_here + strlen($marker);
            }
            $parsed_string = substr($string, $split_here, strlen($string));
        }
        return $parsed_string;
    }

    /**
     * 获取字符串标签中间的值
     * @param $string
     * @param $begin_tag :开始标签
     * @param string $end_tag :结束标签，如果为空，则直接获取到最后
     * @param bool|true $is_include_tag :是否包含标签
     * @return string 结果
     */
    public static function getBetween($string, $begin_tag, $end_tag = '', $is_include_tag = true)
    {
        $temp = self::split($string, $begin_tag, false, $is_include_tag);
        return empty($end_tag) ? $temp : self::split($temp, $end_tag, true, $is_include_tag);
    }

    /**
     * 解析为数组
     * @param $string
     * @param $begin_tag
     * @param $end_tag
     * @param bool|true $is_include_tag 是否包含标签
     * @return mixed
     */
    public static function parseToArray($string, $begin_tag, $end_tag, $is_include_tag = true)
    {
        preg_match_all("($begin_tag(.*)$end_tag)siU", $string, $matching_data);
        if ($is_include_tag == false) {
            foreach ($matching_data[0] as $k => $v) {
                $v = str_replace($begin_tag, '', $v);
                $v = str_replace($end_tag, '', $v);
                $matching_data[0][$k] = $v;
            }
        }
        return $matching_data[0];
    }

    /**
     * 获取Xml结构类型的标签属性
     * @param $string
     * @param $attribute :属性
     * @return string
     */
    public static function getAttribute($string, $attribute)
    {
        # Use Tidy library to 'clean' input
        $cleaned_html = self::tidyHtml($string);
        # Remove all line feeds from the string
        $cleaned_html = str_replace("\r", '', $cleaned_html);
        $cleaned_html = str_replace("\n", '', $cleaned_html);
        # Use return_between() to find the properly quoted value for the attribute
        return self::getBetween($cleaned_html, strtolower($attribute) . '="', '"', false);
    }

    /**
     * 删除标签之间的数据
     * @param $string
     * @param $begin_tag :开始标签
     * @param $end_tag :结束标签
     * @param bool|false $is_remove_tag :是否删除标签
     * @return mixed
     */
    public static function remove($string, $begin_tag, $end_tag, $is_remove_tag = false)
    {
        $remove_array = self::parseToArray($string, $begin_tag, $end_tag, $is_remove_tag);
        //循环替换
        for ($xx = 0; $xx < count($remove_array); $xx++) {
            $string = str_replace($remove_array, '', $string);
        }
        return $string;
    }

    /**
     * 格式化html，注意开启php的tidy扩展
     * @param $input_string
     * @return string
     */
    public static function tidyHtml($input_string)
    {
        if (function_exists('tidy_get_release')) {
            $config = array(
                'uppercase-attributes' => true,
                'wrap' => 800
            );
            $tidy = new \tidy();
            $tidy->parseString($input_string, $config, 'utf8');
            $tidy->cleanRepair();
            $input_string = tidy_get_output($tidy);
        }
        return $input_string;
    }

    /**
     * 随机生成字符串
     * @param int $length
     * @param string $str_pol
     * @return string 生成的字符串
     */
    public static function getRandomStr($length = 16, $str_pol = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz')
    {
        $str = '';
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     * 去除html标签
     * @param $str
     * @return mixed
     */
    public static function stripTags($str)
    {
        //去除html字符
        $str = strip_tags(self::htmlFormat($str));
        //js中html字符无法转换
        $str = str_replace(
            ['&#160;', '&#60;', '&#62;', '&#38;', '&#34;', '&#39;', '&#162;', '&#163;', '&#165;', '&#8364;', '&#167;', '&#169;', '&#174;', '&#8482;', '&#215;', '&#247;'],
            [' ', '<', '>', '&', '"', "'", '￠', '£', '¥', '€', '§', '©', '®', '™', '×', '÷'],
            $str
        );
        //去除 &nbsp;
        return str_replace('&nbsp;', ' ', $str);
    }

    /**
     * 字符串截取，支持中文和其他编码
     * @param $str 待截取的字符串
     * @param int $start 开始位置
     * @param int $length 截取长度
     * @param bool $suffix 截断显示字符
     * @param string $charset 编码格式
     * @return string
     */
    public static function subStringForView($str, $start = 0, $length = 10, $suffix = false, $charset = "utf-8")
    {
        $str = self::stripTags($str);
        //获取字符串长度
        $str_len = self::getLength($str);
        if ($str_len <= $length) {
            return $str;
        }
        if (function_exists("mb_substr")) {
            $slice = mb_substr($str, $start, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $charset);
            if (false === $slice) {
                $slice = '';
            }
        } else {
            $re['utf-8'] = '/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/';
            $re['gb2312'] = '/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/';
            $re['gbk'] = '/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/';
            $re['big5'] = '/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/';
            preg_match_all($re[$charset], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
        }
        return $suffix ? $slice . '…' : $slice;
    }

    /**
     * 获取唯一id
     * @return string
     */
    public static function getUniqueId()
    {
        $uuid = array(
            'time_low' => 0,
            'time_mid' => 0,
            'time_hi' => 0,
            'clock_seq_hi' => 0,
            'clock_seq_low' => 0,
            'node' => array()
        );
        $uuid['time_low'] = mt_rand(0, 0xffff) + (mt_rand(0, 0xffff) << 16);
        $uuid['time_mid'] = mt_rand(0, 0xffff);
        $uuid['time_hi'] = (4 << 12) | (mt_rand(0, 0x1000));
        $uuid['clock_seq_hi'] = (1 << 7) | (mt_rand(0, 128));
        $uuid['clock_seq_low'] = mt_rand(0, 255);
        for ($i = 0; $i < 6; $i++) {
            $uuid['node'][$i] = mt_rand(0, 255);
        }
        $uuid = sprintf('%08x-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x',
            $uuid['time_low'],
            $uuid['time_mid'],
            $uuid['time_hi'],
            $uuid['clock_seq_hi'],
            $uuid['clock_seq_low'],
            $uuid['node'][0],
            $uuid['node'][1],
            $uuid['node'][2],
            $uuid['node'][3],
            $uuid['node'][4],
            $uuid['node'][5]
        );
        return md5($uuid);
    }

    /**
     * 格式化url字符串
     * @param $str
     * @return mixed|string
     */
    public static function urlEncode($str)
    {
        if ($str === true) {
            $str = 'true';
        } else if ($str === false) {
            $str = 'false';
        }
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }

    /**
     * 去除html多次转码问题
     * @param $html
     * @return mixed
     */
    public static function stripAmp($html){
        //无限循环去除&amp;多次格式化问题
        while (strpos($html, '&amp;') !== false && strpos($html, '&lt;') === false){
            $html = str_replace('&amp;', '&', $html);
        }
        //特殊字符处理
        $html = str_replace(
            ['&#160;', '&#60;', '&#62;', '&#38;', '&#34;', '&#39;', '&#162;', '&#163;', '&#165;', '&#8364;', '&#167;', '&#169;', '&#174;', '&#8482;', '&#215;', '&#247;'],
            [' ', '<', '>', '&', '"', "'", '￠', '£', '¥', '€', '§', '©', '®', '™', '×', '÷'],
            $html
        );
        return $html;
    }

    /**
     * html格式化
     * @param $html
     * @return mixed
     */
    public static function htmlFormat($html)
    {
        $html = self::stripAmp($html);
        $html = htmlspecialchars_decode($html);
        //去掉回车和换行
        return str_replace(array("\r", "\n"), '<br/>', $html);
    }

}
