<?php
/**
 * Created by PhpStorm.
 * User: kejing.song
 * Email: 597481334@qq.com
 * Date: 2015/7/3
 * Time: 11:19
 */

namespace ClassLibrary;

/**
 * class library array
 * Class ClArray
 * @package Common\Common
 */
class ClArray {

    /**
     * 将boolean型的元素转换为int型，1 or 0
     * @param $a 待转换数组
     * @param $keys 需转换key值
     * @return mixed
     */
    public static function boolToInt($a, $keys) {
        foreach ($a as $k => $v) {
            if (in_array($k, $keys)) {
                if (is_bool($v)) {
                    $a[$k] = intval($v);
                } else if ($v === 'true') {
                    $a[$k] = 1;
                } else if ($v === 'false') {
                    $a[$k] = 0;
                }
            }
        }
        return $a;
    }

    /**
     * 将字段的0，null转换为false，1转换为true
     * @param $a
     * @param $keys
     * @return mixed
     */
    public static function intToBool($a, $keys) {
        foreach ($a as $k => $v) {
            if (in_array($k, $keys)) {
                if (empty($v)) {
                    $a[$k] = false;
                } else {
                    $a[$k] = true;
                }
            }
        }
        return $a;
    }

    /**
     * 排序，获取文件夹目录下的文件，是字符型的，排序有问题，故采用先采用长度，然后再采用排序方式合并排序
     * @param $files
     * @return array
     */
    public static function sortForDirFiles($files) {
        $a = array();
        foreach ($files as $k => $v) {
            if (isset($a[strlen($v)])) {
                $a[strlen($v)][] = $v;
            } else {
                $a[strlen($v)] = array($v);
            }
        }
        //置空
        $files = array();
        //排序
        foreach ($a as $k => $v) {
            sort($v);
            $files = array_merge($files, $v);
        }
        unset($a);
        return $files;
    }

    /**
     * 将数组合并为字符串，如果传入的是字符串，则返回字符串，不包含key值
     * @param array $a 待合并数组
     * @param string $split 合并分割符
     * @return string
     */
    public static function toString($a, $split = '; ') {
        if (!is_array($a)) {
            return $a;
        }
        $str = '';
        foreach ($a as $each) {
            if (is_array($each)) {
                if ($str == '') {
                    $str .= self::toString($each);
                } else {
                    $str .= $split . self::toString($each);
                }
            } else {
                if ($str == '') {
                    $str = $each;
                } else {
                    $str .= $split . $each;
                }
            }
        }
        unset($a);
        unset($split);
        return $str;
    }

    /**
     * 二维数组去重
     * @param $a
     * @return mixed
     */
    public static function arrayUniqueFor2($a) {
        //降维度
        foreach ($a as $k => $v) {
            $a[$k] = implode(',', $v);
        }
        //去重
        $a = array_unique($a);
        //还原数据
        foreach ($a as $k => $v) {
            $a[$k] = explode(',', $v);
        }
        return $a;
    }

    /**
     * JSON_UNESCAPED_UNICODE
     * @param $array
     * @return string
     */
    public static function jsonUnicode($array) {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON_PRETTY_PRINT
     * @param $array
     * @return string
     */
    public static function jsonPretty($array) {
        return json_encode($array, JSON_PRETTY_PRINT);
    }

    /**
     * JSON_UNESCAPED_SLASHES
     * @param $array
     * @return string
     */
    public static function jsonSlashes($array) {
        return json_encode($array, JSON_UNESCAPED_SLASHES);
    }

    /**
     * 按keys过滤数组
     * @param $array
     * @param $keys
     * @param array $filters 过滤器
     * @return array
     */
    public static function getByKeys($array, $keys, $filters = ['trim']) {
        $return = [];
        foreach ($array as $k => $v) {
            if (in_array($k, $keys)) {
                $return[$k] = $v;
            }
        }
        if (!empty($filters)) {
            $return = self::itemFilters($return, $filters);
        }
        return $return;
    }

    /**
     * 对内容进行过滤处理
     * @param $array
     * @param array $filters
     * @return array
     */
    public static function itemFilters($array, $filters = ['trim']) {
        if (!is_array($array)) {
            return $array;
        }
        foreach ($filters as $filter) {
            array_walk_recursive($array, function (&$each) use ($filter) {
                $each = call_user_func($filter, $each);
            });
        }
        return $array;
    }

    /**
     * 判断数组是否是一维数组
     * @param array $arr
     * @param int $is_rule 是否是规则数组，规则数组为类似数据库存储结构
     * @return bool
     */
    public static function isLinearArray($arr, $is_rule = true) {
        if (count($arr) === count($arr, 1)) {
            //数组内，单个属性不存在数组情况，属于简单数组
            return true;
        } else if ($is_rule) {
            //如果是规则数组，则进一步判断每一个值是否是数组，如果有一个值不为数组，则是一维数组
            foreach ($arr as $k => $v) {
                if (!is_array($v)) {
                    return true;
                }
            }
            return false;
        } else {
            //非规则数组
            return false;
        }
    }

    /**
     * 判断是否在数组内，不区分大小写
     * @param $search
     * @param $array
     * @return bool
     */
    public static function inArrayIgnoreCase($search, $array) {
        return in_array(strtolower($search), array_map('strtolower', $array));
    }

}
