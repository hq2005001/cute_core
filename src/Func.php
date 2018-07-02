<?php
// namespace Cute;
/**
 * 这里定义一些到处都能用到的公有函数
 */
if (!function_exists('app')) {

    function app($name = '')
    {
        if (!empty($name)) {
            $obj = Cute\App::get($name);
        } else {
            $obj = Cute\App::getInstance();
        }
        return $obj;
    }

}

/**
 * 根据字符键获取多维数组中的值
 * 如array_value(array, 's.2')
 *
 * @param array $array 数组
 * @param string $key 键
 * @param mixed $default 默认值
 * @return mixed
 */
if (!function_exists('array_value')) {

    function array_value($array, $key, $default = null)
    {
        if (strpos($key, '.')) {
            $keys = explode('.', $key);
            $data = $array;
            foreach ($keys as &$key) {
                if (isset($data[$key])) {
                    $data = $data[$key];
                } else {
                    return $default;
                }
            }
            return $data;
        }
        return isset($array[$key]) && $array[$key] !== '' ? $array[$key] : $default;
    }

}

/**
 * 字符串去html标签与首尾空格
 * 对用户的数据使用该函数，防止xss攻击
 *
 * @param string $string 字符串
 * @param string $allow 允许的标签
 * @return string
 */
if (!function_exists('str_striptrim')) {

    function str_striptrim($string, $allow = null)
    {
        $string = strval($string);
        $string = strip_tags($string, $allow);
        $string = htmlentities(trim($string));
        return $string;
    }

}

/**
 * 去除空格与标签
 * 只允许3层
 *
 * @param string|array $value 字符串或字符串组成的数组
 * @param string $allow 允许的标签
 * @return string
 */
if (!function_exists('striptrim')) {

    function striptrim($value, $allow = null)
    {
        if (!is_array($value)) {
            $value = str_striptrim($value, $allow);
        } else {
            foreach ($value as &$v) {
                if (is_array($v)) {
                    $v = str_striptrim($v, $allow);
                } else {
                    foreach ($v as &$v1) {
                        $v1 = str_striptrim($v1, $allow);
                    }
                }
            }
        }
        return $value;
    }

}

/**
 * 设置多维数组中的值
 * 如array_setval('s.2', 'good')
 *
 * @param array $array 数组
 * @param string $key 键
 * @param mixed $value 值
 * @return array
 */
if (!function_exists('array_setval')) {

    function array_setval(&$array, $key, $value)
    {
        if (!($pos = strpos($key, '.'))) {
            $array[$key] = $value;
        } else {
            $pkey = substr($key, 0, $pos);
            $key = substr($key, $pos + 1);
            if (!isset($array[$pkey])) {
                $array[$pkey] = [];
            }

            array_setval($array[$pkey], $key, $value);
        }
        return $array;
    }

}

/**
 * 得到资源的链接
 */
if (!function_exists('asset')) {

    function asset($path, $fullPath=false)
    {
        $path = '/assets/' . trim($path, '/');
        if($fullPath) {
            $path = app('req')->domain() . $path;
        }
        return $path;
    }

}

/**
 * 视图帮助函数
 */
if (!function_exists('view')) {

    function view($path, $data = [])
    {
        return app('view')->render($path, $data);
    }

}

/**
 * 得到存储数据
 */
if (!function_exists('storage_path')) {

    function storage_path($file)
    {
        return STORAGE_ROOT . '/' . $file;
    }

}

if (!function_exists('log_path')) {

    function log_path($file)
    {
        return STORAGE_ROOT . '/logs/' . $file;
    }

}

/**
 * 生成唯一的字符串
 */
if (!function_exists('uniqid12')) {

    function uniqid12($length = 12)
    {
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($length / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($length / 2));
        } else {
            throw new \Exception("no cryptographically secure random function available");
        }
        return substr(bin2hex($bytes), 0, $length);
    }

}

/**
 * 是否数组列表
 * 必须下标从0开始
 * @param array
 * @return bool
 */
if (!function_exists('array_is_column')) {

    function array_is_column($array)
    {
        return isset($array[0]) && is_array($array[0]);
    }
}

/**
 * 变量转换成数组
 * @param string|array $string
 * @param string $spliter 分割字符，逗号必定会被分割，默认分号也会被分割
 * @return array
 */
if (!function_exists('arrayval')) {

    function arrayval($var, $spliter = ';')
    {
        if (empty($var)) {
            return [];
        }

        switch (gettype($var)) {
            case 'string':
                $var = explode(',', strtr($var, [', ' => ',', "$spliter, " => ',', "$spliter" => ',']));
                break;
            case 'array':
                $var = array_values($var);
                break;
            default:
                $var = [$var];
        }
        return $var;
    }
}

/**
 * 按key取得数组中的数据
 * @param array $array
 * @param array | string $keys
 * @return array
 */
if (!function_exists('array_filter_key')) {

    function array_filter_key($array, $keys)
    {
        $keys = array_flip(arrayval($keys));
        return array_intersect_key($array, $keys);
    }
}

/**
 * 删除两个数组相同的值
 *
 * @param array $array 被过滤的数组
 * @param array $array1 须比较的值
 * @return array
 */
if (!function_exists('array_unset_same')) {

    function array_unset_same(&$array, $array1)
    {
        foreach ($array as $k => $v) {
            if (isset($array1[$k]) && $v === $array1[$k]) {
                unset($array[$k]);
            }
        }
        return $array;
    }
}

/**
 * 去除标点符号与标签空格
 *
 * @param string $string 字符串
 * @return string
 */
if (!function_exists('str_stripmark')) {

    function str_stripmark($string)
    {
        $string = trim(strval($string));
        if ($string !== '') {
            $string = preg_replace("/[[:punct:]\s]/", ' ', $string);
            $string = urlencode($string);
            $string = preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99|%EF%BD%9E|%EF%BC%8E|%EF%BC%88)+/", ' ', $string);
            $string = str_striptrim(urldecode($string));
        }
        return $string;
    }
}

/**
 * 调试打印
 */
if(!function_exists('dd')) {
    function dd($data) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        exit;
    }
}

/**
 * array merge extend
 * 深度扩展合并数组
 *
 * @param array $array 数组1
 * @param array $array1 数组2
 * @param boolean $override 如果为true，当对应数据为空时才覆盖
 * @return array
 */
if(!function_exists('array_extend')) {
    function array_extend(&$array, $array1, $override = true) {
        foreach ($array1 as $key => $value) {
            if (isset($array [$key]) && is_array($array [$key]) && is_array($value)) {
                array_extend($array[$key], $value);
            } elseif ($override || empty($array [$key])) {
                $array [$key] = $value;
            }
        }
        return $array;
    }    
}

/**
 * 得到url
 *
 * @param string $url
 * @param boolean $fullPath
 * @return string
 */
if(!function_exists('url_for')) {
    
    function url_for($url, $params=[], $fullPath=false) {
        $url = app('route')->build($url, $params);
        if($fullPath) {
            $domain = app('req')->protocol().app('req')->domain().'/';
        } else {
            $domain = '/';
        }
        $url = $domain.trim($url, ' /');
        return $url;
    }
}