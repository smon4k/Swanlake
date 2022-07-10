<?php
/**
 * Created by PhpStorm.
 * User: sun
 * Date: 17-10-20
 * Time: 上午15:23
 * Desc: Aliyun OSS
 */

namespace lib;

use OSS\OssClient;
use OSS\Core\OssException;
use Sts\Request\V20150401 as Sts;
use DateTime;

class ClOSS
{
    const accessKeyId = "";
    const accessKeySecret = "";
    const endpoint = "";
    const bucket = "";
    const host = "";
    const cname = "";//oss域名

    public $client;

    public function __construct()
    {
        $this->client = new OssClient(self::accessKeyId, self::accessKeySecret, self::endpoint);
    }


    /**
     * OSS上传
     * @Author sunbobin
     *
     * @param $object
     * @param $fileContent
     * @param $uploadFile
     * @param $bucket
     * @return \Exception|OssException | string
     */
    public function upload($object, $fileContent = '', $uploadFile = '', $bucket = self::bucket)
    {
        $client = $this->client;
        if (empty($fileContent) && empty($uploadFile)) {
            return false;
        }
        try {
            $option = trim(date('Ymd') . '/' . $object);
            if ($fileContent) {
                $client->putObject($bucket, $option, $fileContent);
            } else {
                $client->uploadFile($bucket, $option, $uploadFile);
            }
            return "http://" . self::host . "/" . $option;
        } catch (OssException $e) {
            return $e;
        }
    }


    public function uploadPaper($object,$uploadFile = '', $bucket = self::bucket){
        $client = $this->client;
        if (empty($uploadFile)) {
            return false;
        }
        try {
            $option = 'paper/'.$object;

            $client->uploadFile($bucket, $option, $uploadFile);

            return true;
        } catch (OssException $e) {
            return $e;
        }
    }

    /**
     * @Author sunbobin
     *
     * @param $object
     * @return string
     */
    public function download($object)
    {
        try {
            $client = $this->client;
            $content = $client->getObject(self::bucket, $object);
            return $content;
        } catch (OssException $e) {
            return $e->getMessage();
        }
    }

    /**
     * 对oss链接进行签名
     * @Author sunbobin
     * @param $path
     * @return string
     */
    public static function takeSignUrl($path)
    {
        $url_parse = parse_url($path);
        if (isset($url_parse['host']) && in_array($url_parse['host'], [self::host, self::cname])) {
            $object = substr($url_parse['path'], 1);
            $client = new OssClient(self::accessKeyId, self::accessKeySecret, self::endpoint);
            $new = $client->signUrl(self::bucket, $object, 3600 * 3);
            $new = $path . (isset($url_parse['query']) ? "&" . parse_url($new)['query'] : "?" . parse_url($new)['query']);
        } else {
            $new = $path;
        }
        return $new;
    }


    /**
     * 设置文件下载名
     * @Author sunbobin
     * @param $path
     * @param $file_name
     */
    public static function changeMata($path, $file_name)
    {
        $url_parse = parse_url($path);
        if (isset($url_parse['host']) && in_array($url_parse['host'], [self::host, self::cname])) {
            $key = substr($url_parse['path'], 1);
            $client = new OssClient(self::accessKeyId, self::accessKeySecret, self::endpoint);
            $option = [$client::OSS_HEADERS => [
                "Content-Disposition" => "attachment;filename=$file_name",
                'Content-Encoding' => 'utf8', 'x-oss-metadata-directive' => 'REPLACE'
            ]];
            $client->copyObject(self::bucket, $key, self::bucket, $key, $option);
        }
    }

    /**
     * 获取临时token
     * @Author sunbobin
     * @return bool|string
     */
    public static function getToken()
    {
        $now = time();
        $expire = 60; //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问
        $end = $now + $expire;
        $expiration = self::gmt_iso8601($end);
//        $dir = date('Y') . '/';
        //最大文件大小.用户可以自己设置
        $condition = array(0 => 'content-length-range', 1 => 0, 2 => 1048576000);
        $conditions[] = $condition;
        //表示用户上传的数据,必须是以$dir开始, 不然上传会失败,这一步不是必须项,只是为了安全起见,防止用户通过policy上传到别人的目录
//        $start = array(0 => 'starts-with', 1 => '$key', 2 => $dir);
//        $conditions[] = $start;
        $arr = array('expiration' => $expiration, 'conditions' => $conditions);
        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, self::accessKeySecret, true));
        $response = array();
        $response['accessid'] = self::accessKeyId;
        $response['host'] = self::host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        return $response;
    }

    private static function gmt_iso8601($time)
    {
        $dtStr = date("c", $time);
        $mydatetime = new DateTime($dtStr);
        $expiration = $mydatetime->format(DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }

    /**
     * @Author sunbobin
     * @Version 1.8.0
     * Date 2016/12/28
     * @param $remote
     * @param $local
     */
    public static function syncFile($remote, $local)
    {
        $local = trim($local);
        if (is_file($local)) {
            return;
        }
        $remote = ClOSS::takeSignUrl($remote);
        $img_data = file_get_contents($remote);
        ClFile::dirCreate($local);
        file_put_contents($local, $img_data);
    }


}