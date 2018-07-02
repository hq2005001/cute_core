<?php

namespace Cute;

class Request
{

    /**
     * 是否已经启动
     *
     * @var boolean
     */
    protected static $started = false;

    protected $controller;
    protected $domains = [];

    /**
     * 请求文件路径
     *
     * @var string
     */
    protected $path = '';
    protected $method;
    protected $type = 'html';
    protected $isAjax = false;
    protected $isAsset = false;

    public function __construct()
    {
        // var_dump($_SERVER);exit;
        // $this->method = strtoupper(empty($_REQUEST['_method']) ? array_value($_SERVER, 'REQUEST_METHOD', 'GET') : $_REQUEST['_method']);
        //  $qstr = empty($_GET['q']) ? '/' : strtr($_GET['q'], ['$' => '']);
        // $qstr = preg_replace(['/\.\.+/', '/\/\/+/'], ['.', '/'], $qstr);
        // $qstr = ltrim(strtr($qstr, ['.php' => '', './' => '']), '/.');  // 不允许访问php文件
        // // 解析格式
        // if ($pos = strrpos($qstr, '.')) {
        //     $this->type = substr($qstr, $pos + 1);
        //     $qstr = substr($qstr, 0, $pos);
        // }
        // var_dump($this->type);
        // var_dump($qstr);
        // var_dump($_REQUEST);
        // var_dump($_SERVER);//exit();
    }

    /**
     * 初始化参数
     */
    protected function init()
    {
        $this->path = '';
        $this->type = '';
        $this->isAjax = false;
        $this->isAsset = false;
    }


    public function setObj($request)
    {
        $this->request = $request;
        return $this;
    }

    public function getObj()
    {
        return $this->request;
    }

    /**
     * 启动应用
     *
     * @return void
     */
    public function start()
    {
        $this->init();
        //处理链接
        $this->parseUri();
        //
        if(IS_CRONTAB) {
            $this->parseCrontab();
        } else if ($this->isAsset) {
            $this->parseStatic();
        } else {
            //处理参数
            // $this->parseParams();
            //处理路由
            $result = app('route')->dispatch($this->path);
            app('res')->dispatch($result);
            // $this->parseController();
        }

//        
//        //解析请求文件类型
//        if (IS_COMMAND) {
//            $this->parseCommand();
//        } elseif (strpos($this->controller, 'static/') !== false) {
//            $this->parseStatic();
//        } else {
//            $this->parseController();
//        }
    }

    /**
     * 处理请求
     * @throws exceptions\ClassNotFoundException
     */
    public function parseController()
    {
        //得到所有的路由映射
        $routeMaps = app('config')->get('router');
        //去掉路径的前后/
        if ($this->path != '/') {
            $this->path = strtolower(trim(strtr($this->path, ['//' => '/']), '/'));
        }
        if (!array_key_exists($this->path, $routeMaps)) {
            throw new exceptions\ClassNotFoundException('路由未找到');
        }
        //得到要调用的方法
        $controller = $routeMaps[$this->path];
        list($controller, $method) = explode('@', $controller);
        $controller = app()->controller(strtr($controller, ['Controller' => '']));
        $result = call_user_func_array([$controller, 'run'], [$method]);
        //判断当前类型
        app('res')->dispatch($result);
    }

    /**
     * 解析命令行任务
     */
    public function parseCrontab()
    {
        app()->crontab($this->path)->run();
    }
    
    /**
     * 解析URI
     *
     * @return void
     */
    public function parseUri()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        //去掉请求链接中的.php
        $requestUri = strtr($requestUri, ['.php' => '']);
        //去掉请求中的请求参数部分
        $requestUri = explode('?', $requestUri);
        $requestUri = $requestUri[0];
        if (strpos($requestUri, '.') !== false) {
            $requestUriArr = explode('.', $requestUri);
            $type = array_pop($requestUriArr);
            $path = implode('.', $requestUriArr);
            $this->type = $type;
            $this->path = $path;
            if (strpos($requestUri, 'assets/') !== false) {
                //把path里的assets干掉
                $this->path = strtr($path, ['assets/' => '']);
                $this->isAsset = true;
            }
        } else {
            $this->path = $requestUri;
            $this->type = '';
        }
    }

    public function type()
    {
        return $this->type;
    }

    // public function parseParams()
    // {
    //     $_GET = empty($this->request->get) ? $_GET : array_merge($_GET, $this->request->get);
    //     $_POST = empty($this->request->post) ? $_POST : array_merge($_POST, $this->request->post);
    //     $_REQUEST = array_merge($_GET, $_POST);
    // }

    public function get($key = '', $default = '')
    {
        $value = array_value($_GET, $key, $default);
        return striptrim($value);
    }

    public function post($key = '', $default = '')
    {
        $value = array_value($_POST, $key, $default);
        return striptrim($value);
    }

    public function input($key = '', $default = '')
    {
        $value = array_value($_REQUEST, $key, $default);
        return striptrim($value);
    }

    /**
     * 得到cookie
     *
     * @param string $name
     * @param string $default
     * @return mixed
     */
    public function cookie($name = '', $default = '')
    {
        $_COOKIE = $this->request->cookie();
        if (empty($name)) {
            return $_COOKIE;
        } elseif (array_key_exists($name, $_COOKIE)) {
            return $_COOKIE[$name];
        }
        return $default;
    }
    
    /**
     * 得到请求头
     * @param type $name
     * @param type $default
     * @return type
     */
    public function header($name='', $default=null)
    {
        return empty($this->request->header[$name]) ?  $default: $this->request->header[$name];
    }

    /**
     *  处理静态文件
     * @return type
     * @throws exceptions\FileNotFoundException
     */
    public function parseStatic()
    {
        $file = $this->path . '.' . $this->type;
        $path = strtr(ASSET_ROOT . '/' . $file, ['//' => '/']);
        if (file_exists($path)) {
            $content = file_get_contents($path);
            //直接把值返回给浏览器
            return app('res')->dispatch($content);
        }
        throw new exceptions\FileNotFoundException('['.$file.']文件未找到');
    }

    public function ip()
    {
        return isset($this->request->header['x-real-ip']) ? $this->request->header['x-real-ip'] : $this->request->server['remote_addr'];
    }

    /**
     * 获得域名定义
     * 默认当前请求域名
     * @param string $url 带域名url
     * @param int $key 0域名，1\2\3对应顶级、二级、三级域名字符
     * @return array|string
     */
    public function domain($key = 0, $url = null) {
        if (empty($url)) {
            if (!empty($this->domains)) {
                $ds = $this->domains;
                return $key === null ? $ds : (isset($ds[$key]) ? $ds[$key] : '');
            }
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = $url;
        }

        // 取出域名部分
        if ($pos = strpos($host, '://'))
            $host = substr($host, $pos + 3);
        $host = preg_replace('/[^\w\._-].*$/i', '', strtolower($host));

        $ds = explode('.', $host);
        $ct = count($ds);
        if ($ct > 1) {
            $slds = ['com' => 1, 'edu' => 1, 'gov' => 1, 'net' => 1, 'org' => 1, 'info' => 1];  // 排.com.cn类似形式域名
            if ($ct > 2 && isset($slds, $ds[$ct - 2]) && !isset($slds, $ds[$ct - 1])) {
                $ds[$ct - 2] .= ".{$ds[$ct - 1]}";
                unset($ds[--$ct]);
            }
            $ds[$ct - 2] .= ".{$ds[$ct - 1]}";
            $ds[$ct - 1] = $host;
            $ds = array_reverse($ds);
        }
        if (empty($url)) {
            $this->domains = $ds;
        }
        return $key === null ? $ds : (isset($ds[$key]) ? $ds[$key] : '');
    }

    /**
     * 得到协议
     *
     * @return void
     */
    public function protocol()
    {
        $prol = 'http://';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $prol = 'https://';
        } elseif (!empty($_SERVER['SERVER_PROTOCOL'])) {
            $prol = explode('/', $_SERVER['SERVER_PROTOCOL']);
            $prol = strtolower(reset($prol)) . '://';
        }
        return $prol;
    }
}
