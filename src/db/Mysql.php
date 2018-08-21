<?php

namespace Cute\db;

use Cute\DB;

class Mysql extends DB
{

    protected $db = null;
    protected $cursor = null;
    protected $iterator = null;
    protected $bulker = null;
    protected $isBulk = false;
    protected $params = [];

    protected $sql = '';


    protected function dsn()
    {
        $conf = $this->dbConf;
        return 'mysql:host=' . $conf['host'] . ';dbname=' . $conf['dbname'] . ';port=' . (empty($conf['port']) ? '3306' : "{$conf['port']}");
    }

    protected function connect($dsn)
    {
        $options = $this->dbConf;
        $conn = new \PDO($dsn, $options['username'], $options['password'], $options['options']);
        return $conn;
    }

    public function execCmd($sql)
    {
        $this->sql = $sql;
        return $this->conn()->exec($sql);
    }

    /**
     * 解析where条件
     * @param array $query
     * @return string
     */
    public function parseQuery($query)
    {
        $newQuery = [];
        foreach ($query as $field => $sub_condition) {
            if(is_numeric($field)) {
                $newQuery[] = $sub_condition;
            } else if (!is_array($sub_condition)) {
                $newQuery[] = "`{$field}`" . '=:' . $field;
                $this->params[':' . $field] = $sub_condition;
            } elseif (count($sub_condition) == 2) {
                switch (strtolower($sub_condition[0])) {
                    case 'in':
                        $newQuery[] = implode(' ', [
                            "`{$field}`",
                            $sub_condition[0],
                            '("' . implode('","', $sub_condition[1]) . '")'
                        ]);
                        break;
                    case 'between':
                        $newQuery[] = implode(' ', [
                            "`{$field}`",
                            $sub_condition[0],
                            implode(' and ', sort($sub_condition[1]))
                        ]);
                        break;
                    default:
                        $newQuery[] = implode(' ', [
                            "`{$field}`",
                            $sub_condition[0],
                            ':' . $field
                        ]);
                        $this->params[':' . $field] = $sub_condition[1];
                }
            } else {
                echo '参数错误';
                exit;
            }
        }
        $newQuery = implode(' and ', $newQuery);
        return $newQuery;
    }

    /**
     * 解析排序条件
     * @param array $sort 排序条件
     * @return string
     */
    public function parseSort($sort)
    {
        $sortArr = [];
        foreach ($sort as $field => $direction) {
            $sortArr[] = implode(' ', [
                $field,
                $direction
            ]);
        }
        $sort = implode(' , ', $sortArr);
        return $sort;
    }

    /**
     * 按条件查询
     */
    public function query()
    {
        $this->params = [];
        $sql = ['select'];
        if (empty($this->field)) {
            $this->field = '*';
        }
        $sql[] = $this->field;
        $sql[] = 'from';
        $sql[] = '`' . $this->table . '`';
        if (!empty($this->query)) {
            $sql[] = "where {$this->parseQuery($this->query)}";
        }
        if (!empty($this->sort)) {
            $sql[] = "order by {$this->parseSort($this->sort)}";
        }
        if (!empty($this->limit)) {
            $this->skip = empty($this->skip) ? 0: $this->skip;
            $sql[] = "limit {$this->skip},{$this->limit}";
        }
        $sql = implode(' ', $sql);
        $this->sql = $sql;
        $this->execute();
        return $this;
    }

    /**
     * 执行原生sql查询
     */
    public function queryRaw($sql, $params=[])
    {
        $this->sql = $sql;
        $this->params = $params;
        $this->execute();
        return $this->cursor->fetchAll();
    }

    public function execute($sql='') 
    {
        if(empty($sql)) {
            $sql = $this->sql;
        }
        $this->cursor = $this->conn()->prepare($sql);
        $rs =  $this->cursor->execute($this->params);
        if(empty($rs)) {
            $msg = implode(' ', $this->conn()->errorInfo());
            dd($msg);
            throw new \Cute\exceptions\DBException(implode(' ', $this->conn()->errorInfo()));
        }
        return $rs;
    }
    
    /**
     * 逐条取得数据
     * @return array
     */
    public function next()
    {
        $item = $this->cursor->fetch();
        yield $item;
    }

    /**
     * 取得查询的数据
     * 如果数据量过大，如超过100条，请使用next
     * @return array
     */
    public function find()
    {
        $this->query();
        return $this->cursor->fetchAll();
    }

    /**
     * 取得一条数据
     * @return array
     */
    public function findOne($field = '', $default = null)
    {
//        $this->limit = 1;
        $this->query();
        $result = $this->cursor->fetch();
        if (!empty($field)) {
            $result = array_value($result, $field, $default);
        }
        return $result;
    }

    /**
     * 取得查询条件的数量
     * @param int $limit
     * @return int
     */
    public function count($limit = null)
    {
        $this->field(['count(1) as count']);
        return $this->limit($limit)->findOne('count', 0);
    }

    /**
     * 开始多条数据操作
     * 开始该操作，insert与update不会立即写入到数据库中，持续到commitBulk完成操作
     */
    public function beginBulk()
    {
        return $this;
    }

    /**
     * 提交结束多条数据操作
     * @return MongoDB\WriteResult
     */
    public function commitBulk()
    {
        $rs = 0;
        return $rs;
    }

    /**
     * 插入数据
     * @return int
     */
    public function insert()
    {
        $this->params = [];
        $data = $this->data;
        if(empty($data)) {
            throw new \Cute\exceptions\DBException('插入数据不能为空');
        }
        $sql = ['insert into'];
        $sql[] = $this->table;
        $multi = isset($data[0]) && is_array($data[0]);
        if ($multi) {
            $fields = array_keys($data[0]);
            $sql[] = '(`' . implode('`,`', $fields) . '`)';
            $insertStr = [];
            foreach ($data as $key =>  $item) {
                $keyData = [];
                foreach($item as $field => $v) {
                    $newKey = ':'.$field.$key;
                    $this->params[$newKey] = $v;
                    $keyData[] = $newKey;
                }
                $insertStr[] = "(" . implode(",", array_values($keyData)) . ")";
            }
        } else {
            $fields = array_keys($data);
            $sql[] = '(`' . implode('`,`', $fields) . '`)';
            $insertStr = [];
            $keyData = [];
            foreach($data as $field => $v) {
                $newKey = ':'.$field;
                    $this->params[$newKey] = $v;
                    $keyData[] = $newKey;
            }
            $insertStr[] = "(" . implode(",", array_values($keyData)) . ")";
        }
        $sql[] = 'values';
        $sql[] = implode(',', $insertStr);
        $this->sql = implode(' ', $sql);
        $rs = $this->execute();
        $this->lastID = $this->conn()->lastInsertId();
        return $rs;
    }

    /**
     * 更新数据
     * @param array $options  设置 multi | upsert
     * @return int  更新的数量
     */
    public function update($options = [])
    {
        $this->options($options);
        $this->params = [];
        $sql = ['update'];
        $sql[] = "`{$this->table}`";
        if(empty($this->data)) {
            throw new \Cute\exceptions\DBException('更新数据不能为空');
        }
        foreach ($this->data as $key => $value) {
            $data[] = "`{$key}`=:{$key}";
            $this->params[":{$key}"] = $value;
        }
        $sql[] = 'set ' . implode(' , ', $data);
        $sql[] = 'where ' . $this->parseQuery($this->query);
        if (!empty($this->limit)) {
            $sql[] = 'limit ' . empty($this->sort) ? '': $this->sort . ',' . $this->limit;
        }
        $sql = implode(' ', $sql);
        $rs = $this->execute($sql);
        return $rs;
    }

    /**
     * 更新一条数据
     * @return int 更新的数量
     */
    public function updateOne()
    {
        return $this->update(['limit' => 1]);
    }

    /**
     * 插入更新，更新数据是如果没有该数据则组合条件与数据插入
     * @return int
     */
    public function upsert()
    {
        // $this->query = ['_id' => $this->data['_id']];
        // unset($this->data['_id']);
        // return $this->update(['multi' => false, 'upsert' => true]);
    }

    /**
     * 删除数据
     * @param array
     * @return int
     */
    public function remove($options = [])
    {
        $this->options($options);
        $sql = ['delete from'];
        $sql[] = $this->table;
        $sql[] = 'where ' . $this->parseQuery($this->query);
        if (!empty($this->limit)) {
            $sql[] = 'limit ' . empty($this->skip) ? '': $this->skip . ',' . $this->limit;
        }
        $sql = implode(' ', $sql);
        $rs = $this->execute($sql);
        return $rs;
    }

    /**
     * 删除一条数据
     * @return int
     */
    public function removeOne()
    {
        return $this->remove(['limit' => true]);
    }

    /**
     * 删除表
     */
    public function drop()
    {
        $sql = 'drop table ' . $this->table;
        $this->execCmd($sql);
        return $this;
    }

    /**
     * 创建索引
     * @param array $indexes
     */
    public function indexes($indexes)
    {
        //@todo 
        return $this;
    }
    
    public function lastSql()
    {
        return $this->sql;
    }

}
