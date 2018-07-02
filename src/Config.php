<?php

namespace cute;

class Config
{

    protected static $configs = [];

    public function __construct()
    {
        self::$configs = require CONFIG_ROOT . '/main.php';
    }

    /**
     * 得到配置文件值
     *
     * @param string $name
     * @param mixed $default
     * @return void
     */
    public function get($name, $default = null)
    {
        return array_value(self::$configs, $name, $default);
    }

    /**
     * 设置配置文件值
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        array_setval(self::$configs, $key, $value);
    }

    /**
     *  得到所有配置信息
     * @return array
     */
    public function all()
    {
        return self::$configs;
    }
}
