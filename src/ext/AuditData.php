<?php

namespace Cute\ext;

use Cute\exceptions\FieldsValidateException;

trait AuditData
{
    /**
     * 数据字段，由子类定义
     * 每个字段必须定义，如果不接受用户输入，则不定义转换与效验规则
     * 转换规则必须定义
     * @var array
     */
    /*
    protected $fields = [
    //'fieldname' => ['value type or convert function', ['check function name, otherparam1, otherparam2', ['check functon name', 'functon params']]
    ]; */

    /**
     * 转换后的数据
     * @var array
     */
    protected $data = [];

    /**
     * 是否合法
     * @var bool
     */
    protected $validity = null;

    /**
     * 取得字段定义
     * @param string|array $field
     * @return array
     */
    public function fields($field = null)
    {
        if (is_null($field)) {
            return $this->fields;
        }
        return array_filter_key($this->fields, $field);
    }

    /**
     * 调用效验或转换函数
     * @param string || array $fun
     * @param mixed $value
     * @return mixed
     */
    protected function callAudit($fun, $value)
    {
        if (is_string($fun)) {
            $fun = explode(',', strtr($fun, [' ' => '']));
        }

        $args = $fun;
        $fun = $fun[0];
        $args[0] = $value;
        if (is_string($fun) && is_callable([$this, $fun])) {
            $fun = [$this, $fun];
        }

        return call_user_func_array($fun, $args);
    }

    /**
     * 设置与取得数据
     * @param array $data
     * @return $this
     */
    public function data($data = null)
    {
        if (is_null($data)) {
            return $this->data;
        }

        if (is_string($data)) {
            return array_value($this->data, $data);
        }

        $this->data = $data;
        return $this;
    }

    /**
     * 取得数据
     * @param string $key
     * @return mixed
     */
    public function getData($key = null)
    {
        if (is_null($key)) {
            return $this->data;
        }

        return array_value($this->data, $key);
    }

    /**
     * 设置字段数据
     * 该方法会过滤掉未定义效验的数据
     * @param array $data
     */
    public function setData($data)
    {
        if (!array_is_column($data)) {
            $this->data = $this->castData($data);
        } else {
            // 多行数据转换
            $this->data = [];
            foreach ($data as $row) {
                $this->data[] = $this->castData($row);
            }
        }
        $this->validity = null;
        return $this;
    }

    /**
     * 减去数据项
     * 键值数组减去同key同值得键，普通数组减去数组中的键
     * @param array $data
     */
    public function subData($data)
    {
        // 不带key减去数据中的某些键
        if (isset($data[0])) {
            $data = array_flip($data);
            if (!array_is_column($this->data)) {
                $this->data = array_diff_key($this->data, $data);
            } else {
                foreach ($this->data as $key => &$item) {
                    $item = array_diff_key($item, $data);
                }
            }
        } else {
            // 带key减去数据中相同的值
            if (!array_is_column($this->data)) {
                array_unset_same($this->data, $data);
            } else {
                foreach ($this->data as $key => &$item) {
                    $item = array_unset_same($item, $data);
                }
            }
        }
        return $this;
    }

    /**
     * 添加数据项,不转换,不检查字段
     * 支持key,value
     * @param $data
     */
    public function addData($data)
    {
        if (empty($this->data)) {
            $this->data = $data;
        } elseif (array_is_column($this->data) === array_is_column($data)) {
            $this->data = array_merge($this->data, $data);
        } else {
            throw new Exception('Dimension of the array not same');
        }
        return $this;
    }

    /**
     * 是否有操作数据
     * @return bool
     */
    public function hasData()
    {
        return !empty($this->data);
    }

    /**
     * 转换一行数据
     * 未定义转换的数据会被舍去
     * @param array $data 要转换的数据，一维数组
     * @param array $fields 字段规则定义
     * @return array
     */
    public function castData($data, $fields = null)
    {
        $item = [];
        if (is_null($fields)) {
            $fields = $this->fields;
        }

        foreach ($data as $field => $value) {
            if (empty($fields[$field])) {
                continue;
            }

            $options = $fields[$field];
            if (!empty($options[0])) { // 只接收定义了转换的数据
                $type = $options[0];
                $item[$field] = $this->castValue($value, $type);
            }
        }
        return $item;
    }

    /**
     * 转换值类型
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    protected function castValue($value, $type)
    {
        switch ($type) {
            case 'float': // 转换成浮点数
                $value = floatval($value);
                break;
            case 'decimal': // 转换浮点数两位小数，货币用
                $value = round($value, 2);
                break;
            case 'string': // 转换成字符串，会过滤掉html字符，防xss攻击处理
                $value = str_striptrim($value);
                break;
            case 'strhtml': // html格式字符，不会做转换放xss攻击
                $value = strval($value);
                break;
            case 'strpure': // 不包含标点的纯净的字符
                $value = str_stripmark($value);
                break;
            case 'integer': // 转换成整形
                $value = intval($value);
                break;
            case 'array': // 转换成数组，按逗号与分号
                $value = arrayval($value);
                break;
            case 'jsondoc':
                $value = is_array($value) ? $value : json_decode($value, true);
                break;
            case 'boolean': // 转换成布尔
                $value = boolval($value);
                break;
            case 'timestamp': //  转换成unix时间戳
                $value = is_string($value) ? strtotime($value) : $value;
                break;
            case 'file': // 文件地址类型
                $value = str_striptrim($value);
                break;
            case 'files': // 多个文件地址类型，数组
                $value = arrayval($value);
                break;
            default: // 默认，为函数处理
                $value = $this->callAudit($type, $value);
        }
        return $value;
    }

    /**
     * 效验sets的数据
     * @param boolean $required 是否必填
     * @return boolean
     */
    public function isValid($required = false)
    {
        if (is_null($this->validity)) {
            $this->validity = true;
            if (!array_is_column($this->data)) {
                $message = $this->validData($this->data, $required);
                if (!empty($message)) {
                    $this->validity = false;
                    throw new FieldsValidateException(json_encode($message));
                }
            } else {
                // 多行数据效验，忽略效验错误的数据
                foreach ($this->data as $k => $data) {
                    $message = $this->validData($data, $required);
                    if (!empty($message)) {
                        $this->validity = false;
                        throw new FieldsValidateException(json_encode($message));
                    }
                }
            }
        }
        return $this->validity;
    }

    /**
     * 必填校验
     * @return bool
     */
    public function isMust()
    {
        return $this->isValid(true);
    }

    /**
     * 效验一行数据
     * 会舍去未定义效验的字段
     * @param array $data
     * @param boolean|array $required 是否必填 | 效验规则
     * @return array
     */
    public function validData(&$data, $required = true)
    {
        $message = [];
        if (is_array($required)) {
            // 自定义效验
            $rules = $required;
            $required = true;
        } else {
            // fields定义的效验
            $rules = $this->fields;
        }
        // 删除未定义效验的值
        foreach ($data as $key => $val) {
            if (!isset($rules[$key]) || empty($rules[$key][1])) {
                unset($data[$key]);
            }

        }
        // 效验数据
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : null;
            if ((!empty($value) || $required) && !empty($rule[1])) {
                if ($rs = $this->validValue($value, $rule[1])) {
                    if (is_string($rs)) {
                        $message[$field] = $rs;
                    }
                }

            }
        }
        return $message;
    }

    /**
     * 校验值
     * @param mix $value
     * @param array|string $rules 如果有返回值效验失败
     * @return string
     */
    public function validValue($value, $rules)
    {
        // 空值校验
        $required = array_search('*', $rules);
        $message = $this->validByRule($value, '*');
        if ($required !== false) {
            // 必填项效验失败不在效验
            if (is_string($message)) {
                return $message;
            }

            unset($rules[$required]);
        } elseif ($message !== true) {
            // 非必填项值为空返回通过效验
            return true;
        }
        // 规范校验
        foreach ($rules as $rule) {
            $message = $this->validByRule($value, $rule);
            if (is_string($message)) {
                return $message;
            }

        }
        return true;
    }

    /**
     * 检验一个规则
     * 效验失败返回要有消息，效验成功返回true
     * @param mixed $value
     * @param mixed $rule
     * @return mixed
     * @throws exceptions\FieldsValidateException
     */
    protected function validByRule($value, $rule)
    {
        if (is_string($rule)) {
            $rule = explode(',', strtr($rule, [' ' => '']));
        }

        $rs = true;
        switch ($rule[0]) {
            case 'pass': // 通过校验
                break;
            case '*':
            case 'required': //  必填
                if (is_null($value) || empty($value) && $value != '0') {
                    $rs = 'Value cannot be empty';
                }

                break;

            case 'regex': // 正则表达式
                if (empty($rule[1])) {
                    throw new \Cute\exceptions\FieldsValidateException('Arguments has error in regex valid');
                }

                $regex = $rule[1];
                if (!preg_match($regex, $value)) {
                    $rs = 'Error in field';
                }

                break;

            case 'digit': // 数字
                if (!ctype_digit($value)) {
                    $rs = 'Input is not a digit';
                }

                break;

            case 'alnum': // 数字与字母
                if (!ctype_alnum($value)) {
                    $rs = 'Input can only consist of letters or digits';
                }

                break;

            case 'alpha': // 字母
                if (!ctype_alpha($value)) {
                    $rs = 'Input can only consist of letters';
                }

                break;

            case 'length': // 字符长度
                $min = array_value($rule, 1, 0);
                $max = array_value($rule, 2, 0);
                $length = mb_strlen($value, 'utf-8');
                if (($length < $min) || (($max > 0) && ($length > $max))) {
                    $rs = "Input must be [$min]-[$max] characters";
                }

                break;

            case 'between': //  数值范围
                $min = array_value($rule, 1, 0);
                $max = array_value($rule, 2, 0);
                if ((($min != null) && ($value < $min)) || (($max != null) && ($value > $max))) {
                    $rs = "Value must be between [$min]-[$max]";
                }

                break;

            case 'equal': // 相等
                if (empty($rule[1])) {
                    throw new \Cute\exceptions\FieldsValidateException('Arguments has error in equal valid');
                }

                $equal = $rule[1];
                if (!($value == $equal && strlen($value) == strlen($equal))) {
                    $rs = 'Both values must be the same';
                }

                break;

            case 'email': // 邮箱
                if (!preg_match('/^\w[-.\w]*@([-a-z0-9]+\.)+[a-z]{2,4}$/i', $value)) {
                    $rs = 'Invalid email address';
                }

                break;

            case 'phone': // 手机
                if (!preg_match('/^1[34578]\d{9}$/', $value)) {
                    $rs = 'Invalid phone number';
                }

                break;

            case 'exist': // 只在数组内
                if (empty($rule[1])) {
                    throw new \Cute\exceptions\FieldsValidateException('Arguments has error in inArray valid');
                }

                $array = $rule[1];
                if (!in_array($value, $array)) {
                    $rs = 'Invalid scope';
                }

                break;

            default: // 默认，为函数处理
                $rs = $this->callAudit($rule, $value);
        }
        return $rs;
    }

}
