<?php

namespace Cute;

class Controller
{

    protected $middlewares = [
    ];

    public function run($method)
    {
        $request = app('req');
        $result = true;
        if (!empty($this->middlewares)) {
            foreach ($this->middlewares as $middleware) {
                app()->middleware($middleware)->handle($request, $result);
                if (!$result) {
                    return '';
                }
            }
        }
        return $this->$method();
    }

    /**
     * 添加中间件
     * @param type $name
     * @return $this
     */
    public function addMiddleware($name)
    {
        if (!in_array($this->middlewares, $name)) {
            $this->middlewares[] = $name;
        }
        return $this;
    }

    /**
     * 设置api返回数据结构
     *
     * @param mixed $data 返回的数据
     * @param string $msg 返回的消息
     * @param integer $statusCode http状态码
     * @return void
     */
    public function apiData($data, $msg = '', $statusCode = 200)
    {
        app('res')->status($statusCode);
        return [
            'status' => $statusCode,
            'msg' => 'ok',
            'recordset' => $data,
        ];
    }
}
