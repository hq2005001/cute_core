<?php

return [
    'req' => Cute\Request::class, //请求类
    'res' => Cute\Response::class, //响应类
    'test' => Cute\Test::class,
    'config' => Cute\Config::class, //配置类
    'view' => Cute\View::class, //视图类
    
    'server' => Cute\Server::class, //服务器类
    
    'jwt' => Cute\ext\Jwt::class, //jwt验证类
    
    'route' => Cute\Route::class, //路由类
    'session' => Cute\Session::class, //session类
];