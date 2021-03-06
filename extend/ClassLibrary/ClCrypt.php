<?php
/**
 * Created by PhpStorm.
 * User: skj19
 * Date: 2016/11/21
 * Time: 10:26
 */

namespace ClassLibrary;

/**
 * 加解密函数
 * Class ClCrypt
 * @package ClassLibrary
 */
class ClCrypt {

    /**
     * 加密字符串
     * @param string $string 待加密的字符串
     * @param string $key 加密key
     * @param int $expire 有效期（秒）
     * @return mixed
     */
    public static function encrypt($string, $key, $expire = 0) {
        $expire = sprintf('%010d', $expire ? $expire + time() : 0);
        $key    = md5($key);
        $string = base64_encode($expire . $string);
        $x      = 0;
        $len    = strlen($string);
        $l      = strlen($key);
        $char   = $str = '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) $x = 0;
            $char .= substr($key, $x, 1);
            $x++;
        }
        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord(substr($string, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
        }
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
    }

    /**
     * 解密字符串
     * @param string $string 字符串
     * @param string $key 加密key
     * @param bool $get_left_time 获取剩余时间
     * @return bool|int|string
     */
    public static function decrypt($string, $key, $get_left_time = false) {
        $cache_key      = ClCache::getKey([$string, $key]);
        $decrypt_string = cache($cache_key);
        if ($decrypt_string === false) {
            $key    = md5($key);
            $string = str_replace(array('-', '_'), array('+', '/'), $string);
            $mod4   = strlen($string) % 4;
            if ($mod4) {
                $string .= substr('====', $mod4);
            }
            $string = base64_decode($string);
            $x      = 0;
            $len    = strlen($string);
            $l      = strlen($key);
            $char   = $str = '';
            for ($i = 0; $i < $len; $i++) {
                if ($x == $l) $x = 0;
                $char .= substr($key, $x, 1);
                $x++;
            }
            for ($i = 0; $i < $len; $i++) {
                if (ord(substr($string, $i, 1)) < ord(substr($char, $i, 1))) {
                    $str .= chr((ord(substr($string, $i, 1)) + 256) - ord(substr($char, $i, 1)));
                } else {
                    $str .= chr(ord(substr($string, $i, 1)) - ord(substr($char, $i, 1)));
                }
            }
            $decrypt_string = base64_decode($str);
            //缓存
            cache($cache_key, $decrypt_string, 3600);
        }
        if ($get_left_time) {
            $expire = substr($decrypt_string, 0, 10);
            if ($expire > 0 && $expire < time()) {
                return 0;
            } else {
                return $expire - time();
            }
        } else {
            $expire = substr($decrypt_string, 0, 10);
            if ($expire > 0 && $expire < time()) {
                return '';
            }
            return substr($decrypt_string, 10);
        }
    }

}