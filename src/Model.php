<?php

namespace Cute;

use Cute\exceptions\DBException;

abstract class Model extends Service {

   use ext\AuditData;
//
   use ext\QueryData;


    /**
     * 集合名词，必须由子类定义
     * @var string
     */
    protected $table = null;

    /**
     * 主键名
     * @var type 
     */
    protected $pk = '_id';
    
    /**
     * 是否生成ID
     * @var type 
     */
    protected $genId = true;

    /**
     * 数据库驱动实例
     * @var v\Dbase
     */
    protected $db = null;

    /**
     * 最后一条查询的数据
     * @var array
     */
    protected $lastGet = null;

    /**
     * 最后一条添加的数据
     * @var array
     */
    protected $lastAdd = null;

    /**
     * 最后一条更新的数据
     * @var array
     */
    protected $lastUp = null;

    /**
     * 取得数据驱动实例
     * @param array | array $conf 配置
     * @return Dbase
     */
    public function db($conf = []) {
        if (is_null($this->db)) {
            if (empty($this->table)) {
                throw new exceptions\PropertyException('Model'. get_called_class(). ' property table undefined');
            }
            if(!empty($conf)) {
                $db = app()->db(null, $conf);
            } else {
                $db = app()->db();
            }
            $this->db = $db->table($this->table);
        }
        return $this->db;
    }

    /**
     * 建立索引
     * @return $this
     */
    public function indexes() {
        if (!empty($this->indexes)) {
            $this->db()->indexes($this->indexes);
        }
        return $this;
    }

    /**
     * 选择数据库
     * @param string | array $conf 配置KEY
     * @return self
     */
    public function copy($conf = []) {
        $model = clone $this;
        $model->db($conf);
        return $model;
    }

    /**
     * 给数据生成唯一ID
     * @param array $data ;
     */
    protected function guuid(&$data) {
        if (isset($data[0])) {
            if (!isset($data[0][$this->pk])) {  // 多条数据_id格式需要一致，这里以第一条数据为准
                foreach ($data as $k => &$item) {
                    $item[$this->pk] = (string) new \MongoDB\BSON\ObjectId();
                }
            }
        } elseif (!isset($data[$this->pk])) {
            $data[$this->pk] = (string) new \MongoDB\BSON\ObjectId();
        }
        return $this;
    }

    /**
     * 初始化模型查询与插入更新数据
     */
    public function reset() {
        $this->where([]);
        $this->setData([]);
        return $this;
    }

    /**
     * find取得的字段
     * @param array|string $fields
     *      string format +field1+field2 or -field1-field2
     */
    public function field($fields) {
        if (!empty($fields)) {
            if (is_array($fields) && isset($fields[0])) {
                $this->field = $fields;
            } else {
                if (!is_string($fields)) {
                    $state = reset($fields);
                } else {
                    $state = substr($fields, 0, 1) == '-' ? 0 : 1;
                    $fields = explode(',', trim(strtr($fields, [', ' => ',', '-' => ',', '+' => ',', ' ' => ',']), ','));
//                    $fields = array_fill_keys($fields, 1);
                }

                if ($state == 0) {
                    // 排除某字段， 在现有字段上排除
                    if (empty($this->field)) {
                        $this->field = array_keys($this->fields);
//                        $this->field = array_fill_keys($this->field, 1);
                    }
                    foreach ($fields as $field => $value) {
                        unset($this->field[$field]);
                    }
                } else {
                    $this->field = $fields;
                }
            }
        }
        return $this;
    }

    /**
     * 排序
     * @param array|string $sorts ，如果为字符串需要检测是否允许排序
     *      string like -filed1+field2
     */
    public function sort($sorts) {
        if (!empty($sorts)) {
            if (is_string($sorts)) {
                $fields = explode(',', trim(strtr($sorts, ['-' => ',-', '+' => ',+', ' ' => ',+']), ','));
                $sorts = [];
                foreach ($fields as $field) {
                    $state = substr($field, 0, 1) == '-' ? -1 : 1;
                    $field = trim($field, '+ -');
                    $sorts[$field] = $state;
                }
            }
            $this->sort = $sorts;
        }
        return $this;
    }

    /**
     * 计算数量
     * @param int|null $limit
     * @return int
     */
    public function count($limit = null) {
        return $this->db()->where($this->query)->count($limit);
    }

    /**
     * 取得数据
     * @param array $options 选项设置
     * @return array
     */
    public function getAll($options = []) {
        if (!empty($options))
            $this->options($options);
        $rs = $this->db()->where($this->query)->field($this->field)
                        ->limit($this->limit)->skip($this->skip)
                        ->sort($this->sort)->hint($this->hint)->find();
        $this->lastGet = reset($rs);
        return $rs;
    }

    /**
     * 取得一条数据
     * @param array $options 选项设置
     * @return array
     */
    public function getOne($options = []) {
        if (!empty($options))
            $this->options($options);
        $rs = $this->db()->where($this->query)->field($this->field)->sort($this->sort)->hint($this->hint)->findOne();
        $this->lastGet = $rs;
        return $rs;
    }

    /**
     * 取得分页数据
     * @param array $options 选项，包含 limit skip field sort row page all
     *         row 每页条数, page 页码,  all 最大统计条数，模糊查询的时候使用
     * @param bool $fuzzyCount 是否模糊统计数据
     * @return array
     */
    public function getPaging($options = [], $fuzzyCount = false) {
        // 分页
        $row = min([array_value($options, 'row', 12), $this->config('pagingPerMaxRow', 100)]);  // 每页数据最大条数，默认100
        $page = array_value($options, 'page', 1);
        $start = $row * ($page - 1);
        $items = $this->skip($start)->limit($row)->getAll($options);
        // 如果参数里有总行数，则使用模糊行数,默认模糊一页数据
        $count = 0;
        if (!empty($items)) {
            if (!$fuzzyCount) {
                $count = $this->limit(0)->count();
            } else {
                $all = array_value($options, 'all', $this->config('pagingCountMaxRow', 101));  // 最大统计数据条数，默认101条
                $count = count($items);
                $count = $start + $count + ($count == $row ? 1 : 0);  // 如果数据等于页数则向后一页
                // 如果模糊页数小于预估页数，从新算总页数
                if ($count > 0 && $count < $all) {
                    $count = $this->count($all);
                }
            }
        }

        return ['count' => $count, // 总条数
            'page' => ceil($count / $row), // 总页数
            'data' => $items];
    }

    /**
     * 按ID取得数据
     * @param $id
     * @param array $options
     * @return array
     */
    public function getByID($id, $options = []) {
        return $this->where([$this->pk => $id])
                        ->getOne($options);
    }

    /**
     * 按多个ID取得二维数据
     * @param array $ids
     * @param array $options
     * @return array
     */
    public function getByIDs($ids, $options = []) {
        $ids = arrayval($ids);
        return $this->where(count($ids) === 1 ? [$this->pk => reset($ids)] : [$this->pk => ['in', $ids]])
                        ->getAll($options);
    }

    /**
     * 设置添加数据，由子类继承改变 addOne与addAll的数据
     * @return $this;
     */
    protected function setAdd($isMulti) {
        return $this;
    }

    /**
     * 设置更新数据，由子类继承改变 upOne与upAll的数据
     * @return $this;
     */
    protected function setUp($isMulti) {
        return $this;
    }

    /**
     * 添加多条数据
     * @return int
     */
    public function addAll() {
        $rs = 0;
        if (empty($this->data)) {
            throw new DBException('Data cannot be empty');
        } else if (!array_is_column($this->data)) {
            throw new DBException('Data cannot be one');
        } else {
            if($this->genId){
                $this->guuid($this->data);
            }
            $this->setAdd(true);
            $rs = $this->db()->data($this->data)->insert();
            $this->lastAdd = null;
        }
        return $rs;
    }

    /**
     * 添加一条数据
     * 会走多条执行方式，子类修改数据，修改多条的数据即可
     * @return int
     */
    public function addOne() {
        $rs = 0;
        if (empty($this->data)) {
            throw new DBException('Data cannot be empty');
        } else if (isset($this->data[0])) {
            throw new DBException('Data cannot be multi');
        } else {
            if($this->genId) {
                $this->guuid($this->data);
            }
            $this->setAdd(false);
            $rs = $this->db()->data($this->data)->insert();
            $this->lastAdd = null;
        }
        return $rs;
    }

    /**
     * 更新多条数据
     * @return int
     */
    public function upAll() {
        $rs = 0;
        if (!empty($this->data)) {
            $this->setUp(true);
            $rs = $this->db()->where($this->query)->data($this->data)->update();
            $this->lastUp = null;
        }
        return $rs;
    }

    /**
     * 更新一条数据
     * @return int
     */
    public function upOne() {
        $rs = 0;
        if (!empty($this->data)) {
            $this->setUp(false);
            $rs = $this->db()->where($this->query)->data($this->data)->updateOne();
            $this->lastUp = null;
        }
        return $rs;
    }

    /**
     * 按ID更新数据
     * @param string $id
     * @return int
     */
    public function upByID($id) {
        return $this->where([$this->pk => $id])
                        ->upOne();
    }

    /**
     * 保存数据，只支持单条数据
     * 有该数据时候update，没有时插入数据
     * 该数据会走upAll与addAll，资料对数据的处理不用单独进行
     * @return int
     */
    public function save() {
        $rs = 0;
        if (empty($this->data)) {
            throw new DBException('Data cannot be empty');
        } else if (array_is_column($this->data)) {
            throw new DBException('Data cannot be multi');
        } else {
            if (empty($this->data[$this->pk])) {
                // 无ID添加
                $this->setAdd(false);
                $rs = $this->addOne();
            } else {
                // 有ID更新
                $this->where([$this->pk => $this->data[$this->pk]])
                        ->setUp(false);
                unset($this->data[$this->pk]);
                $rs = $this->upOne();
            }
        }
        return $rs;
    }

    /**
     * 删除数据
     * @return int
     */
    public function delAll() {
        $rs = 0;
        if (!empty($this->query)) {
            $rs = $this->db()->where($this->query)->remove();
        }
        return $rs;
    }

    /**
     * 删除单条数据
     * @return int
     */
    public function delOne() {
        $rs = 0;
        if (!empty($this->query)) {
            $rs = $this->db()->where($this->query)->removeOne();
        }
        return $rs;
    }

    /**
     * 按ID删除数据
     * @param string $id
     * @return int
     */
    public function delByID($id) {
        return $this->where([$this->pk => $id])
                        ->delOne();
    }

    /**
     * 按ID删除数据
     * 支持多个和单个ID，字符串逗号隔开
     * @param array | string $ids
     * @return int
     */
    public function delByIDs($ids) {
        $ids = arrayval($ids);
        return count($ids) === 1 ? $this->where([$this->pk => reset($ids)])->delOne() :
                $this->where([$this->pk => ['in', $ids]])->delAll();
    }

    /**
     * 取得最后插入的ID
     * @return string
     */
    public function lastID() {
        return $this->db()->lastID();
    }

    /**
     * 取得最后添加的一条数据
     * 注意不会改变lastGet的数据
     * @param array $data
     * @return $this
     */
    public function lastAdd($data = null) {
        if (is_null($data)) {
            if (is_null($this->lastAdd)) {
                $this->lastAdd = [];
                if ($id = $this->db()->lastID()) {
                    $this->lastAdd = $this->db()->where([$this->pk => $id])->findOne();
                }
            }
            return $this->lastAdd;
        }
        $this->lastAdd = $data;
        return $this;
    }

    /**
     * 取得最后更新后的一条数据
     * 注意不会改变lastGet的数据
     * @param array $data
     * @return $this
     */
    public function lastUp($data = null) {
        if (is_null($data)) {
            if (is_null($this->lastUp)) {
                $this->lastUp = $this->db()->where($this->query)->findOne();
                if (is_null($this->lastUp))
                    $this->lastUp = [];
            }
            return $this->lastUp;
        }
        $this->lastUp = $data;
        return $this;
    }

    /**
     * 取得最后查询的一条数据
     * 注意如果没有做过查询操作会使用最后更新的条件进行查询
     * @param array $data
     * @return $this
     */
    public function lastGet($data = null) {
        if (is_null($data)) {
            if (is_null($this->lastGet)) {
                $this->lastGet = $this->db()->where($this->query)->findOne();
            }
            return $this->lastGet;
        }
        $this->lastGet = $data;
        return $this;
    }

    /**
     * 数据Join
     * 通过该模型ID，连接该模型的数据
     * @param array $data 要关联的数据
     * @param string $foreignKey 关联数据的外键
     * @param array|string $field 要关联的字段
     * @return array
     */
    public function joinTo(&$data, $foreignKey, $field = null, $prefix = null) {
//        $prefix = is_null($prefix) ? strrchr(get_class($this), '\\') : $prefix;
        $prefix = is_null($prefix) ? '': $prefix;
        if (!empty($prefix)) {
            $prefix = $prefix . '_';
        }
        if (array_is_column($data)) {
            // 多条
            $ids = array_column($data, $foreignKey);
            if (!empty($ids)) {
                $ids = array_values(array_unique($ids));
                if(!empty($field)) {
                    $field = array_merge($field, [$this->pk]);
                }
                $items = $this->getByIDs($ids, ['field' => $field]);
                $items = array_column_askey($items, $this->pk);
                // 信息合并 modelname_fieldname
                foreach ($data as &$item) {
                    if (!empty($item[$foreignKey]) && !empty($items[$item[$foreignKey]])) {
                        foreach ($items[$item[$foreignKey]] as $f => $v) {
                            if ($f != $this->pk)
                                $item["{$prefix}{$f}"] = $v;
                        }
                    }
                }
            }
        } elseif (!empty($data[$foreignKey])) {
            // 单条
            $item = $this->getByID($data[$foreignKey], ['field' => $field]);
            if (!empty($item)) {
                foreach ($item as $f => $v) {
                    if ($f != $this->pk)
                        $data["{$prefix}{$f}"] = $v;
                }
            }
        }
        return $data;
    }

    public function query($sql, $params=[])
    {
        return $this->db()->queryRaw($sql, $params);
    }

}

