<?php
    /**
     * Created by PhpStorm.
     * User: fanpengjie
     * Date: 18-1-18
     * Time: 下午3:22
     */

    namespace RequestService;

    use cache\Rediscache;
    use think\Cache;

    class RequestService
    {
        /**
         * @param string $url
         * @param array $params
         * @param int $timeout
         * @return bool|mixed|string
         */
        public static function doCurlPostRequest(string $url, array $params, int $timeout = 5)
        {
            if ($url == '' || empty($params) || $timeout <= 0) {
                return false;
            }
            try {
                $con = curl_init((string)$url);
                curl_setopt($con, CURLOPT_HEADER, false);
                curl_setopt($con, CURLOPT_POSTFIELDS, $params);
                curl_setopt($con, CURLOPT_POST, true);
                curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($con, CURLOPT_TIMEOUT, $timeout);
                $res = curl_exec($con);
                return json_decode($res, true);
            } catch (\Exception $exception) {
                return $exception->getMessage();
            }
        }

        /**
         * @param string $url
         * @param array $params
         * @param int $timeout
         * @return bool|mixed|string
         */
        public static function doJsonCurlPost(string $url, string $params, int $timeout = 5)
        {
            if ($url == '' || empty($params) || $timeout <= 0) {
                return false;
            }
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt(
                    $ch,
                    CURLOPT_HTTPHEADER,
                    array(
                                   'Content-Type: application/json')
                );
                return curl_exec($ch);
            } catch (\Exception $exception) {
                return $exception->getMessage();
            }
        }

        /**
         * @param string $url
         * @param array $params
         * @param int $timeout
         * @return bool|mixed|string
         */
        public static function doCurlGetRequest(string $url, array $params, int $timeout = 5)
        {
            if ($url == "" || $timeout <= 0) {
                return false;
            }
            try {
                $url = $url . '?' . http_build_query($params);
                $key = 'service:'.base64_encode(md5($url));
                // $res = self::getCache($key);
                $res = 0;
                if (false == $res || count(json_decode($res, true)['data']) <= 0) {
                    // p($url);
                    $header = array(
                        'Accept: application/json',
                     );
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    // 设置请求头
                    //设置获取的信息以文件流的形式返回，而不是直接输出。
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $res = curl_exec($ch);
                    curl_close($ch);
                    self::setCache($key, $res);
                }
                $data = json_decode($res, true);
                // p($data);
                // return isset($data['data']) ? $data['data'] : $data;
                return $data;
            } catch (\Exception $exception) {
                return $exception->getMessage();
            }
        }


        //缓存相关
        private static function setCache($cache_key, $cache_content, $cache_time = 3600 * 8)
        {
            if (\Config('switch_cache') == 'redis') {
                Rediscache::getInstance()->set($cache_key, $cache_content);
            } else {
                Cache::set($cache_key, $cache_content, $cache_time);
            }
        }

        private static function getCache($cache_key)
        {
            $res = [];
            if (\Config('switch_cache') == 'redis') {
                $res =  Rediscache::getInstance()->get($cache_key);
            } else {
                $res = Cache::get($cache_key);
            }
            return $res;
        }
    }
