<?php

namespace Cute\ext;

trait QueryData {

    /**
     * where查询条件
     * @var array
     */
    protected $query = null;

    /**
     * 排序
     * @var array
     */
    protected $sort = null;

    /**
     * 查询字段
     * @var array
     */
    protected $field = null;

    /**
     * 取数限制条数
     * @var init
     */
    protected $limit = null;

    /**
     * 跳过多少条
     * @var init
     */
    protected $skip = null;

    /**
     * 索引提示
     * @var string
     */
    protected $hint = null;

    /**
     * 查询条件
     * @param array $query
     */
    public function where($query = null) {
        $this->query = $query;
        // 所有查询条件被重置
        $this->sort = null;
        $this->field = null;
        $this->limit = null;
        $this->skip = null;
        $this->hint = null;
        return $this;
    }

    /**
     * find取得的字段
     * @param array $fields
     * @return $this
     */
    public function field($fields) {
        if (is_null($fields))
            return $this;
        if (is_string($fields))
            $fields = arrayval($fields);
        if (isset($fields[0])) {
            $fields = array_fill_keys($fields, 1);
        }
        $this->field = $fields;
        return $this;
    }

    /**
     * 排序
     * @param array $sorts
     * @return $this
     */
    public function sort($sorts) {
        $this->sort = $sorts;
        return $this;
    }

    /**
     * 数据限制条数
     * @param int $num
     * @return $this
     */
    public function limit($num) {
        $this->limit = $num;
        return $this;
    }

    /**
     * 跳过数据条数
     * @param int $num
     * @return $this
     */
    public function skip($num) {
        $this->skip = $num;
        return $this;
    }

    /**
     * 
     * @param string $indexName 索引名称
     * @return $this
     */
    public function hint($indexName) {
        $this->hint = $indexName;
        return $this;
    }

    /**
     * options参数设置，配置limit field sort skip hint等
     * @param array $options
     * @return $this
     */
    public function options($options) {
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'field':
                    $this->field($value);
                    break;
                case 'sort':
                    $this->sort($value);
                    break;
                case 'limit':
                    $this->limit($value);
                    break;
                case 'skip':
                    $this->skip($value);
                    break;
                case 'hint':
                    $this->hint($value);
                    break;
            }
        }
        return $this;
    }

}
