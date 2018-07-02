<?php

namespace Cute;

class App extends Singleton
{

    /**
     * 全局对象树
     *
     * @var array
     */
    protected static $objects = [];

    /**
     * 应用是否已经启动
     *
     * @var boolean
     */
    protected static $started = false;

    protected function __construct()
    {
        defined('CUTE_ROOT') || define('CUTE_ROOT', dirname(__FILE__));
        //引入函数库
        require_once CUTE_ROOT . '/Func.php';
    }

    /**
     * 魔术方法得到对象
     *
     * @param string $name 类别名
     * @return object
     */
    public function __get($name)
    {
        return self::get($name);
    }

    /**
     * 魔术方法设置对象
     *
     * @param string $name 类别名
     * @param object $value 类实例
     */
    public function __set($name, $value)
    {
        self::set($name, $value);
    }

    /**
     * 魔术方法检查类是否存在
     *
     * @param string $name 类别名
     * @return boolean
     */
    public function __isset($name)
    {
        return isset(self::$objects[$name]);
    }

    /**
     * 魔术方法删除类
     *
     * @param string $name 类别名
     */
    public function __unset($name)
    {
        if (isset(self::$objects[$name])) {
            unset(self::$objects[$name]);
        }
    }

    /**
     * 全局应用启动方法
     *
     * @return void
     */
    public function run()
    {
        if (self::$started) {
            return;
        }
        self::$started = true;
        //设置异常捕获
        set_exception_handler([app('exception'), 'handle']);
        $isCrontab = !empty($_SERVER['argv']);
        //判断是否是脚本任务
        if ($isCrontab && !empty($_SERVER['argv'][1])) {
            // crontab的参数
            $_SERVER['REQUEST_URI'] = trim($_SERVER['argv'][1], '?& ');
        }
        define('IS_CRONTAB', $isCrontab);

        //开始处理请求
        app('req')->start();
    }

    /**
     * 得到对象
     *
     * @param string $name 对象别名
     * @return object
     */
    public static function get($name)
    {
        $value = array_value(self::$objects, $name);
        if (empty($value)) {
            //载入应用配置文件
            $apps = require CONFIG_ROOT . '/app.php';
            //得到系统配置类map
            $sysApps = require CUTE_ROOT . '/config/app.php';
            $apps = array_merge($sysApps, $apps);
            if (array_key_exists($name, $apps)) {
                $className = $apps[$name];
                $value = new $className();
                self::set($name, $value);
            } else {
                throw new exceptions\ClassNotFoundException('Class Not Found');
            }
        }
        return $value;
    }

    /**
     * 设置对象到全局对象树
     *
     * @param string $name 对象别名
     * @param object $object 对象实例
     * @return void
     */
    public static function set($name, $object)
    {
        array_setval(self::$objects, $name, $object);
    }

    /**
     * 得到控制器对象
     *
     * @param string $className 类名
     * @return object
     */
    public function controller($className)
    {
        $className = str_replace('\\\\', '\\', '\\app\\controller\\' . $className . 'Controller');
        if (!isset(self::$objects['controllers']) || !array_key_exists($className, self::$objects['controllers'])) {
            $obj = new $className();
            self::set('controllers.' . $className, $obj);
        }
        return self::$objects['controllers'][$className];
    }

    public function crontab($className)
    {
        //把路径中的反斜线转成正斜线
        $className = str_replace('/', '\\', $className);
        $className = str_replace('\\\\', '\\', '\\app\\crontab\\' . $className);
        if (!isset(self::$objects['crontabs']) || !array_key_exists($className, self::$objects['crontabs'])) {
            $obj = new $className();
            self::set('crontabs.' . $className, $obj);
        }
        return self::$objects['crontabs'][$className];
    }

    /**
     *  得到模型对象
     * @param type $className
     * @return type
     */
    public function model($className)
    {
        $className = str_replace('\\\\', '\\', '\\app\\model\\' . $className . 'Model');
        if (!isset(self::$objects['models']) || !array_key_exists($className, self::$objects['models'])) {
            $obj = new $className();
            self::set('models.' . $className, $obj);
        }
        return self::$objects['models'][$className];
    }

    /**
     * 得到中间件
     * @return type
     */
    public function middleware($className)
    {
        $className = str_replace('\\\\', '\\', '\\app\\middleware\\' . $className . 'Middleware');
        if (!isset(self::$objects['middleware']) || !array_key_exists($className, self::$objects['middleware'])) {
            $obj = new $className();
            self::set('middleware.' . $className, $obj);
        }
        return self::$objects['middleware'][$className];
    }

    /**
     * 保存日志
     * @param string $data
     * @param type $file
     */
    public function log($data, $file = 'default.log')
    {
        $data = date('Y-m-d H:i:s') . "\t" . (is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data) . PHP_EOL;
        @file_put_contents(log_path($file), $data, FILE_APPEND);
    }

    /**
     * db数据库连接实例
     *
     * @param string $driver 驱动名
     * @param array $confs 连接配置
     * @return object
     */
    public function db($driver = null, $confs = [])
    {
        if (empty($confs)) {
            if (is_null($driver)) {
                $driver = app('config')->get('db_driver', 'mongodb');
            }
            //得到指定驱动下的配置文件
            $confs = app('config')->get('db.' . $driver);
            if (is_null($confs)) {
                throw new exceptions\ConfigNotFoundException('配置文件不存在');
            }
        }
        $dbMd5Str = md5(json_encode($confs));
        //通过confs实例化db对象
        if (!isset(self::$objects['connections'][$dbMd5Str])) {
            self::$objects['connections'][$dbMd5Str] = new $confs['class_name']($confs['options']);
        }
        return self::$objects['connections'][$dbMd5Str];
    }

    /**
     * 索引引擎实例
     * 
     * @param string $driver 驱动
     * @param array $confs 配置
     * @return object
     */
    public function search($driver = 'elasticsearch', $confs=[])
    {
        if (empty($confs)) {
            //得到指定驱动下的配置文件
            $confs = app('config')->get('search.' . $driver);
            if (is_null($confs)) {
                throw new exceptions\ConfigNotFoundException('配置文件不存在');
            }
        }
        if(!isset(self::$objects['searchs'][$driver])) {
            self::$objects['searchs'][$driver] = new $confs['class_name']($confs);
        }
        return self::$objects['searchs'][$driver];
    }

}
