<?php
namespace cache;

use think\Exception;
use think\log;

class Rediscache
{
    private static $_instance;
    private $_cache;

    private function __construct()
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty(getenv('ENVIRONMENT')) && getenv('ENVIRONMENT') == 'production') {
            $config = \config('redis')['redisprod'];
        } else {
            $config = \config('redis')['redisdev'];
        }
        $this->_cache = new \Redis;
        $this->_cache->connect($config['host'], $config['port'], $config['timeout']);
        $this->_cache->select(1);

        if ('' != $config['password']) {
            $this->_cache->auth($config['password']);
        }
    }

    public static function getInstance()
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    public function __call($name, $arguments)
    {
        if (!method_exists(self::getInstance(), $name)) {
            try {
                $this->_cache->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
                return call_user_func_array([$this->_cache, $name], $arguments);
            } catch (Exception $exception) {
                Log::save();
                echo json_encode($exception->getMessage());
            };
        }
    }
}