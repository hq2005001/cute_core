<?php

namespace Cute;
use Elasticsearch\ClientBuilder;

abstract class Search
{

    use ext\QueryData;

    /**
     * 索引
     */
    protected $index=null;

    /**
     * 类型
     */
    protected $type=null;

    protected $searchConf = [];

    protected $data = null;

    protected $conn = null;

    protected static $conns = [];

    public function __construct($confs)
    {
        $this->searchConf = $confs['options'];
        $this->index = $confs['index'];
        $this->type = $confs['type'];
    }

    public function conn()
    {
        if(empty($this->conn)) {
            $key = md5(json_encode(array_merge($this->searchConf, ['index' => $this->index, 'type' => $this->type])));
            if(empty(self::$conns[$key])) {
                self::$conns[$key] = $this->connect();
            }
            $this->conn = self::$conns[$key];
        }
        return $this->conn;
    }

    public function setConf($confs) 
    {
        $this->searchConf = $confs;
        return $this;
    }

    public function index($index=null)
    {
        if(is_null($index)) {
            return $this->index;
        }
        $this->index = $index;
        return $this;
    }

    public function type($type=null)
    {
        if(is_null($type)) {
            return $this->type;
        }   
        $this->type = $type;
        return $this;
    }


    abstract protected function connect();

    abstract public function insert($data);

    abstract public function query();

    abstract public function delete($id);

    abstract protected function execute($type, $data);
}