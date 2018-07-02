<?php

namespace Cute;

class Response
{

    /**
     * 配置
     * @var array
     */
    protected $configs = [
        'charset' => 'utf-8',
        'chartypes' => [
            'html' => 1,
            'xml' => 1,
            'json' => 1,
            'css' => 1,
            'js' => 1,
            'csv' => 1,
            'txt' => 1,
            'md' => 1
        ],
    ];

    /**
     * http 头
     * @var array
     */
    protected $headers = [];

    /**
     * 响应正文
     * @var string
     */
    protected $body = '';

    /**
     * 最后响应状态
     * @var string
     */
    protected $status = 200;

    /**
     * 格式
     * @var string
     */
    protected $type = 'txt';
    
    protected $contentTypes = [
        'html' => 'text/html',
        'xml' => 'text/xml',
        'json' => 'application/json',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'swf' => 'application/x-shockwave-flash',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'otf' => 'application/x-font-otf',
        'eot' => 'application/octet-stream',
        'woff' => 'application/x-font-woff',
        'svg' => 'image/svg+xml',
        'ttf' => 'application/octet-stream',
        'csv' => 'text/csv',
        'file' => 'application/octet-stream',
        'md' => 'text/html',
        'map' => 'text/html',
    ];

    /**
     * 编码
     * @var string
     */
    protected $charset = 'utf-8';

    /**
     * 缓存时间
     * @var int
     */
    protected $cacheAge = 0;

    /**
     * 跨域jsonp函数
     * @var string
     */
    protected $jsonpCallback = null;

    /**
     * 是否文件流
     * @var boolean
     */
    protected $isStream = false;

    /**
     * 响应json转换参数
     * @var bool
     */
    protected $jsonOption = 0;

    /**
     * 初始化设置系统编码
     */
    public function __construct()
    {
        //网页编码
        $this->charset = app('config')->get('charset', 'utf-8');
    }

    /**
     * 设置http状态码
     * @param type $code
     * @return $this
     */
    public function status($code = null)
    {
        if(empty($code)) {
            return $this->status;
        }
        $this->status = intval($code);
        return $this;
    }

    /**
     * 返回响应
     * @param type $content
     * @param type $code
     */
    public function end($content = '', $code = null)
    {
        if (!is_null($code)) {
            $this->headers[$_SERVER['SERVER_PROTOCOL']] = $this->status;
        }
        //返回值前发送header头
        if (!empty($this->headers)) {
            foreach ($this->headers as $k => $v) {
                call_user_func_array('header', [implode(': ',[$k,$v])]);
            }
        }
        echo $content;
        exit;
    }

    /**
     * 
     * @param type $content
     */
    public function dispatch($content, $code = null)
    {
        $this->type = app('req')->type();
        switch ($this->type) {
            case 'json' :
                $content = json_encode($content);
                break;
            case 'html':
                break;
            case '':
                $this->type = 'html';
        }
        //根据不同类型设置不同的响应头
        $contentType = $this->contentTypes[$this->type] . '; charset=' . $this->charset;
        $this->header('Content-Type', $contentType);

        $this->end($content, $code);
    }

    public function header($key = '', $value = '')
    {
        if (is_array($key)) {
            $this->headers = array_merge($this->headers, $key);
        } elseif (!empty($value)) {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    public function cookie($key, $value, $expire = 0, $path = '/', $domain = '', $httponly = true)
    {
        $expire = empty($value) ? NOW_TIME - 60 : ($expire === 0 ? 0 : NOW_TIME + $expire);
        $base = app('req')->url('/');
        setcookie($name, $value, $expire, $base, $domain, false,$httponly);
        return $this;
    }

    /**
     * 重定向
     * @param type $url
     * @param type $status
     * @return string
     */
    public function redirect($url, $status = 302)
    {
        $this->status($status);
        $this->header("Location", $url);
        $this->end('');
    }

    /**
     * 得到/设置请求类型
     * @param type $type
     * @return $this
     */
    public function type($type = null)
    {
        if (is_null($type)) {
            return $this->type;
        }
        $this->type = $type;
        return $this;
    }

}
