<?php
namespace lib;
/**
 * Class ClHttp 类库Http
 */
class ClFile
{
    /**
     * 无限新建文件夹，支持linux和windows目录
     * @param $file_name string 待创建的文件，例如：C:\workspace\PhpStorm\CC\WebSite\Application\Runtime\Logs/Home//DDD/14_08_07.log
     * @param bool $is_file 传入的是否是文件，如果是文件则不进行文件、文件夹的自动判断
     * @return string
     */
    public static function dirCreate($file_name, $is_file = false)
    {
        $file_name = trim(str_replace('\\', '/', $file_name), '/');
        $dir_array = explode('/', $file_name);
        if (ClSystem::isWin()) {
            $dir_str = '';
        } else {
            $dir_str = '/';
        }
        if (empty($dir_array[0])) {
            //去除为空的数据
            array_shift($dir_array);
        }
        //第一个目录不判断
        $dir_str .= $dir_array[0];
        array_shift($dir_array);
        //判断最后一个是文件还是文件夹
        if ($is_file) {
            $min_limit = 1;
        } else {
            $min_limit = empty(self::getSuffix($file_name)) ? 0 : 1;
        }
        while (is_array($dir_array) && count($dir_array) > $min_limit) {
            $dir_str .= '/' . $dir_array[0];
            if (!is_dir($dir_str)) {
                mkdir($dir_str, 0777, true);
                chmod($dir_str, 0777);
            }
            array_shift($dir_array);
        }
        return $dir_str;
    }

    /**
     * 获取文件后缀名
     * @param $file
     * @return mixed
     */
    public static function getSuffix($file)
    {
        $suffix = $suffix_mid = isset(pathinfo($file)['extension']) ? strtolower(pathinfo($file)['extension']) : '';
        if (strpos($suffix_mid, '?')) {
            $suffix_ary = explode('?', $suffix_mid);
            $suffix = $suffix_ary[0];
        }
        return $suffix;
    }

    /**
     * @Author sunbobin
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

    /**
     * 下载文件
     * @param string $file 被下载文件的绝对路径
     * @param string $name 用户看到的文件名
     * @return void
     */
    public static function download($file, $name = '')
    {
        $fileName = $name ? $name : self::getName($file, true);
        $filePath = realpath($file);
        $fp = fopen($filePath, 'rb');
        if (!$filePath || !$fp) {
            header('HTTP/1.1 404 Not Found');
            echo "Error: 404 Not Found.(server file path error)<!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding --><!-- Padding -->";
            exit;
        }
        //ie 版本 gb2312转码
        $encoded_filename = ClString::encoding($fileName, ClString::V_ENCODE_GB2312);
        header('HTTP/1.1 200 OK');
        header("Pragma: public");
        header("Expires: 0");
        header("Content-type: application/octet-stream");
        header("Content-Length: " . filesize($filePath));
        header("Accept-Ranges: bytes");
        header("Accept-Length: " . filesize($filePath));
        $ua = ClHttp::getBrowser();
        if (in_array($ua[0], ['Internet Explorer', 'Edge'])) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if ($ua[0] == 'Mozilla Firefox') {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $fileName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
        }
        // ob_end_clean(); <--有些情况可能需要调用此函数
        // 输出文件内容
        fpassthru($fp);
        exit;
    }

    /**
     * 获取文件名
     * @param $file
     * @param bool|false $has_suffix 是否带有后缀
     * @return array|string
     */
    public static function getName($file, $has_suffix = false)
    {
        $file = trim($file);
        $file = basename($file);
        if ($has_suffix) {
            return $file;
        } else {
            if (strpos($file, '.') === false) {
                return $file;
            } else {
                return str_replace(self::getSuffix($file), '', $file);
            }
        }
    }
}