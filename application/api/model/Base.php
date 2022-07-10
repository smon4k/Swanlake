<?php

namespace app\api\model;

use Think\Cache;
use think\Model;

class Base extends Model
{
    const CRYPT_KEY = 'H2OMedio';

    protected $resultSetType = 'collection';

    //缓存相关
    protected static function setCache($cache_key, $cache_content, $cache_time = 3600) {
        $cache_key = implode('.',$cache_key);
        // Cache::set($cache_key, $cache_content, $cache_time * 8);
        Cache::set($cache_key, $cache_content, $cache_time);
    }

    protected static function getCache($cache_key){
        $res = [];
        $cache_key = implode('.',$cache_key);
        $res = Cache::get($cache_key);
        return $res;
    }

    protected static function delCache($cache_key){
        $cache_key = implode('.',$cache_key);
        $res = Cache::rm($cache_key);
        return $res;
    }

    /**
     * 批量更新数据函数
     * @param $data array 待更新的数据，二维数组格式
     * @param array $params array 值相同的条件，键值对应的一维数组
     * @param string $field string 值不同的条件，默认为id
     * @return bool|string
     */
    protected static function batchUpdate($table, $data, $field, $params = [])
    {
        if (!is_array($data) || !$field || !is_array($params)) {
            return false;
        }

        $updates = self::parseUpdate($data, $field);
        $where = self::parseParams($params);

        // 获取所有键名为$field列的值，值两边加上单引号，保存在$fields数组中
        // array_column()函数需要PHP5.5.0+，如果小于这个版本，可以自己实现，
        // 参考地址：http://php.net/manual/zh/function.array-column.php#118831
        $fields = array_column($data, $field);
        $fields = implode(',', array_map(function($value) {
            return "'".$value."'";
        }, $fields));

        $sql = sprintf("UPDATE `%s` SET %s WHERE `%s` IN (%s) %s", $table, $updates, $field, $fields, $where);

        return $sql;
    }

    /**
     * 将二维数组转换成CASE WHEN THEN的批量更新条件
     * @param $data array 二维数组
     * @param $field string 列名
     * @return string sql语句
     */
    protected static function parseUpdate($data, $field)
    {
        $sql = '';
        $keys = array_keys(current($data));
        foreach ($keys as $column) {

            $sql .= sprintf("`%s` = CASE `%s` \n", $column, $field);
            foreach ($data as $line) {
                $sql .= sprintf("WHEN '%s' THEN '%s' \n", $line[$field], $line[$column]);
            }
            $sql .= "END,";
        }

        return rtrim($sql, ',');
    }

    /**
     * 解析where条件
     * @param $params
     * @return array|string
     */
    protected static function parseParams($params)
    {
        $where = [];
        foreach ($params as $key => $value) {
            $where[] = sprintf("`%s` = '%s'", $key, $value);
        }
        
        return $where ? ' AND ' . implode(' AND ', $where) : '';
    }

    /**
    * 递归实现无限极分类
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    protected static function getListTree($list, $pid=0){
        $tree = '';
        foreach($list as $k => $v){
            if($v['pid'] == $pid){
                $v['child'] = self::getListTree($list, $v['id']);
                $tree[] = $v;
            } 
        }
        return $tree;
     }

     /**
     * 获取指定行内容
     *
     * @param $file 文件路径
     * @param $line 行数
     * @param $length 指定行返回内容长度
     */
    protected static function get_file_line( $file_name, $line_star,  $line_end){
        $n = 0;
        $handle = fopen($file_name, "r");
        if ($handle) {
            while (!feof($handle)) {
                ++$n;
                $out = fgets($handle, 4096);
                if($line_star <= $n){
                    $ling[] = $out;
                }
                if ($line_end == $n) break;
            }
            fclose($handle);
        }
        if( $line_end==$n) return $ling;
        return false;
    }
}
