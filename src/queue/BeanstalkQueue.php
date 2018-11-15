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
use Pheanstalk\Pheanstalk;

class BeanstalkQueue extends Service implements IQueue
{
    protected $configs = [
        'host' => '127.0.0.1',
        'port' => '11300',
        'db' => '',
        'password' => '',
        'prefix' => 'task_queue',
        'timeout' => 10,
    ];

    protected static $dbs = [];

    private $prefix = '';

    private $obj = null;

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
        $key = crc32("{$conf['host']}{$conf['port']}");
        if (empty(self::$dbs[$key])) {
            $pheanstalk = new Pheanstalk($conf['host'], $conf['port']);
            self::$dbs[$key] = $pheanstalk;
        }
        $this->obj = self::$dbs[$key];
    }

    protected function genKey($key)
    {
        return $this->prefix . '_' . $key;
    }

    public function get($key)
    {
        try{
            $job = $this->obj->watch($this->genKey($key))->reserve($this->config('timeout', 10));
            if($job) {
                $data = $job->getData();
                $data = json_decode($data, true);
                $this->delete($job);
            } else {
                echo 'timeout';
                $data = false;
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
            $data = false;
        }
        return $data;
    }

    public function set($key, $data)
    {
        $data = json_encode($data);
        $this->obj->useTube($this->genKey($key))->put($data);
    }

    public function delete($job)
    {
        $this->obj->delete($job);
    }
}