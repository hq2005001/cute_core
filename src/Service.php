<?php

namespace Cute;


class Service
{

    /**
     * 类配置
     *
     * @var array
     */ 
    protected $configs = [];
    
    /**
     * 得到类配置文件
     *
     * @param string $key
     * @param string $default
     * @return array
     */
    public function config($key=null, $default=null)
    {
        if(!isset($this->conf)) {
            //从类属性中去取
            $className = get_called_class();
            $classRealName = strtolower(basename(strtr($className, '\\', '/')));
            //优先级当前类配置
            $this->conf = $this->configs;
            //得到配置文件中的配置
            $fileConfigs = app('config')->get($classRealName, []);
            $this->conf = array_extend($this->conf, $fileConfigs);
        }
        if(empty($key)) {
            return $this->conf;
        }
        return array_value($this->conf, $key, $default);
    }

}