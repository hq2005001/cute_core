<?php

/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-11-15
 * Time: 上午11:54
 */

namespace Cute\queue;



use Cute\interfaces\IQueue;
use Cute\Service;

class RedisQueue extends Service implements IQueue
{
    protected $configs = [
        'host' => '127.0.0.1',
        'port' => '6379',
        'db' => '15',
        'password' => '',
        'prefix' => 'task_queue',
        'timeout' => 10,
    ];

    /**
     * redis 连接实例
     * @var array
     */
    protected static $dbs = [];

    private $prefix = '';

    private $redis = null;

    /**
     * 当前数据库
     * @var integer
     */
    protected $curdb = null;

    /**
     * 静态选择的数据库
     * @var type
     */
    protected static $db = null;

    public function __construct($conf = [])
    {

        if (empty($conf)) {
            $conf = $this->config();
        }
        $this->configs = $conf;
        $this->prefix = $conf['prefix'];
        $key = crc32("{$conf['host']}{$conf['port']}{$conf['db']}");
        if (empty(self::$dbs[$key])) {
            $redis = new \Redis();
            $redis->pconnect($conf['host'], $conf['port']);
            self::$dbs[$key] = $redis;
        }
        $this->redis = self::$dbs[$key];
        $this->select($conf['db']);

        // $redis = new \Redis();
        // $redis->pconnect($conf['host'], $conf['port']);
        // $this->redis = $redis;
        // $this->select($conf['db']);
    }

    /**
     * 选择数据库
     * @param integer $db
     */
    public function select($db)
    {
        if (self::$db !== $db) {
            $this->redis->select($db);
            $this->curdb = $db;
            self::$db = $db;
        }
    }


    protected function genKey($key)
    {
        return $this->prefix . '_' . $key;
    }

    public function get($key)
    {
//        $data = $this->redis->brpop($this->genKey($key), 5);
//        if($data) {
//            $data = $data[1];
//            return json_decode($data, true);
//        }
        $data = $this->redis->rpop($this->genKey($key));
        if (!empty($data)) {
            return json_encode($data, true);
        }
        return false;
    }

    public function set($key, $data)
    {
        $data = json_encode($data);
        $this->redis->lpush($this->genKey($key), $data);
    }

    public function delete($key)
    {
        $this->redis->delete($this->genKey($key));
    }
}