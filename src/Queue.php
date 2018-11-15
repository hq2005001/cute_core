<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-11-15
 * Time: 上午11:55
 */

namespace Cute;

class Queue extends Service
{

    private $obj = null;

    protected $configs = [
        'driver' => \Cute\queue\RedisQueue::class,
        'config' => [
            'host' => '127.0.0.1',
            'port' => '6379',
            'db' => '15',
            'password' => '',
            'prefix' => 'task_queue',
        ]
    ];


    public function __construct($driver=null, $config=[])
    {
        if($driver) {
            $this->configs['driver'] = $driver;
        }
        if($config) {
            $this->configs['config'] = $config;
        }
        $this->obj = app()->createObj($this->config('driver'), [$this->config('config')]);
    }


    public function __call($name, $args)
    {
        if(is_callable([$this->obj, $name])) {
            return call_user_func_array([$this->obj, $name], $args);
        } else {
            throw new exceptions\MethodException("方法{$name}不存在");
        }
    }
}