<?php
/**
 * redis实例
 * @author hq <305706352@qq.com>
 */
namespace Cute;

class Redis extends Service
{
    
    protected $configs = [
        'host' => '127.0.0.1', 
        'port' => 6379,
        'db' => 0,
        'prefix' => '',
    ];
    
    /**
     * redis实例
     * @var object 
     */
    protected $redis;
    
    /**
     * 键前缀
     * @var string 
     */
    protected $prefix = '';
    
    /**
     * redis 连接实例
     * @var array
     */
    protected static $dbs = [];
    
    /**
     * 当前数据库
     * @var integer 
     */
    protected $curdb=null;
    
    /**
     * 静态选择的数据库
     * @var type 
     */
    protected static $db = null;
    
    public function __construct()
    {
        $conf = $this->config();
        $this->prefix = $conf['prefix'];
        $key = crc32("{$conf['host']}{$conf['port']}{$conf['db']}");
        if(empty(self::$dbs[$key])) {
            $redis = new \Redis();
            $redis->pconnect($conf['host'], $conf['port']);
            self::$dbs[$key] = $redis;
        }
        $this->redis = self::$dbs[$key];
        $this->select($conf['db']);
    }
    
    /**
     * 魔术方法调用redis的方法
     * @param type $name
     * @param string $args
     * @return type
     * @throws exceptions\MethodException
     */
    public function __call($name, $args)
    {
        $this->select($this->curdb);
        if(is_callable([$this->redis, $name])) {
            if(!empty($args[0])) {
                $args[0] = $this->prefix . $args[0];
            }
            return call_user_func_array([$this->redis, $name], $args);
        } else {
            throw new exceptions\MethodException("方法{$name}不存在");
        }
    }
    
    /**
     * 选择数据库
     * @param integer $db
     */
    public function select($db) 
    {
        if(self::$db !== $db) {
            $this->redis->select($db);
            $this->curdb = $db;
            self::$db = $db;
        }
    }
    
    /**
     * 进程排它锁
     * @param string $key 
     * @param integer $expire 过期时间毫秒
     * @return boolean
     */
    public function lock($key, $expire=1)
    {
        $key = "lock_{$key}";
        if($this->setnx($key, 1)) {
            $this->expire($key, $expire);
            return true;
        }
        return false;
    }
    
    /**
     * 释放锁
     * @param type $key
     */
    public function unlock($key)
    {
        $key = "lock_{$key}";
        $this->del($key);
    }
}
