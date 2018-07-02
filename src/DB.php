<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Cute;

/**
 * Description of DB
 *
 * @author anoah
 */
abstract class DB
{

    use ext\QueryData;
    
    protected static $tables = [];
    
    /**
     * 插入或者更新的数据
     * @var array
     */
    protected $data = null;

    /**
     * 数据表名
     * @var string
     */
    protected $table = null;

    /**
     * 数据库名
     * @var string
     */
    protected $dbname = null;

    /**
     * 最后插入的ID
     * @var string
     */
    protected $lastID = null;

    /**
     * 数据库连接
     * @var mixed
     */
    protected $conn = null;

    /**
     * 数据库连接
     *
     * @var array
     */
    protected static $conns = [];

    protected $dbConf = [];

    /**
     * 构造函数
     */
    public function __construct($configs)
    {
        $this->dbConf = $configs;
    }

    /**
     * 取得数据库连接
     * @return mixed
     */
    public function conn()
    {
        if (empty($this->conn)) {
            $dsn = $this->dsn();
            $key = md5($dsn);
            if (empty(self::$conns[$key])) {
                self::$conns[$key] = $this->connect($dsn);
            }
            $this->conn = self::$conns[$key];
            $this->dbname = $this->dbConf['dbname'];
        }
        return $this->conn;
    }

    /**
     * 选择数据表
     * @param string $table 表名
     * @return self
     */
    public function table($table = null)
    {
        if (is_null($table)){
            return $this->table;
        }

        $dbname = $this->dbConf['dbname'];
        $key = md5($this->dsn() . ";db=$dbname;table=$table");
        if (empty(self::$tables[$key])) {
            // 第一次的table作为默认，如果table不同则自动克隆一个新对象
            self::$tables[$key] = is_null($this->table) ? $this : clone $this;
            self::$tables[$key]->table = $table;
        }
        return self::$tables[$key];
    }

    /**
     * 插入或更新的数据
     * @param array $data
     * @return array
     */
    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 取得最后插入的ID
     * @return string
     */
    public function lastID()
    {
        return $this->lastID;
    }

    /**
     * 设置读写分离
     * 默认为读写分离模式
     * 希望写入后立即读取的情况，不要读写分离模式
     * @param boolean $value  为true读写分离
     */
    public function splitRW($value)
    {
        self::$isSplitRW = $value;
        return $this;
    }

    /**
     * 取得数据库连接data source name
     * 不同的驱动组成方式不一样
     * @return string
     */
    abstract protected function dsn();

    /**
     * 通过dsn链接到数据库
     * @param string $dsn
     * @return mixed 数据库连接实例
     */
    abstract protected function connect($dsn);

    /**
     * 查询数据，不返回查询结果，和next配合使用
     */
    abstract public function query();

    /**
     * 取得下一条数据
     * @return array
     */
    abstract public function next();

    /**
     * 取得数据
     * @return array
     */
    abstract public function find();

    /**
     * 取得数据，单行
     * @return array
     */
    abstract public function findOne();

    /**
     * 开始多条数据操作
     * 开始该操作，insert与update不会立即写入到数据库中，持续到commitBulk完成操作
     */
    abstract public function beginBulk();

    /**
     * 提交结束多条数据操作
     * @return int
     */
    abstract public function commitBulk();

    /**
     * 插入数据
     * @return boolean 是否成功
     */
    abstract public function insert();

    /**
     * 更新数据
     * @return boolean 是否成功
     */
    abstract public function update();

    /**
     * 更新一条数据
     * @return boolean 是否成功
     */
    abstract public function updateOne();

    /**
     * 插入更新，更新数据是如果没有该数据则组合条件与数据插入
     * @return int
     */
    abstract public function upsert();

    /**
     * 删除数据，多条
     * @return boolean 是否成功
     */
    abstract public function remove();

    /**
     * 删除数据，单条
     */
    abstract public function removeOne();

    /**
     * 删除数据表
     * @return boolean 是否成功
     */
    abstract public function drop();

    /**
     * 取得数据条数
     * @param int $limit 最多限制条数，null不限制
     * @return int
     */
    abstract public function count($limit = null);

    /**
     * 创建索引
     * @param array $indexes
     */
    abstract public function indexes($indexes);
}
