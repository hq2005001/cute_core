<?php

namespace Cute;

class Route
{

    /**
     * 控制器
     *
     * @var string
     */
    protected $controller = null;

    /**
     * 方法
     *
     * @var string
     */
    protected $action = null;


    protected function parseUrl($url)
    {
        $defaultController = app('config')->get('default_controller', 'index');
        $defaultMethod = app('config')->get('default_method', 'index');
        $this->controller = $defaultController;
        $this->action = $defaultMethod;
        $controllerArr = explode('/', $url);
        if (count($controllerArr) > 1) {
            $this->action = array_pop($controllerArr);
            $controller = ucfirst(array_pop($controllerArr));
            array_push($controllerArr, $controller);
            $this->controller = implode('\\', $controllerArr);
        } else if (count($controllerArr) == 1) {
            $this->controller = ucfirst($controllerArr[0]);
        }
    }

    /**
     * 分发路由
     *
     * @return void
     */
    public function dispatch($url)
    {
        // if($url !== '/') {
        //     $url = trim($url, '/');
        // }
        // 得到所有的预设路由
        $routeMaps = app('config')->get('router');
        $match = false;
        if (!empty($url)) {
            foreach ($routeMaps as $pattern => $action) {
                //如果完全相同就按指定的走
                // $pattern = trim($pattern, '/');
                if ($pattern == $url) {
                    $this->parseUrl($action);
                    $match = true;
                    break;
                } else {
                    //得到指定pattern中的参数
                    preg_match_all("#:([a-zA-Z0-9]+)#", $pattern, $keys);
                    if (!empty($keys[1])) {
                        $keys = $keys[1];
                        $pattern = preg_replace("#:([a-zA-Z0-9]+)#", "([a-zA-Z0-9_-]+)", $pattern);
                        preg_match("#{$pattern}#", $url, $values);
                        if (count($values) > 1) {
                            array_shift($values);
                            $params = array_combine($keys, $values);
                            $_GET = array_merge($_GET, $params);
                            $match = true;
                            $this->parseUrl($action);
                            break;
                        }
                    }
                }
            }
        }
        // 如果没有在指定的路由里匹配到，就按控制器/方法来取
        if (!$match) {
            if (!empty($url)) {
                $this->parseUrl($url);
            }
        }
        $controller = app()->controller(strtr($this->controller, ['Controller' => '']));
        $result = call_user_func_array([$controller, 'run'], [$this->action]);
        return $result;
    }

    /**
     * 生成链接
     *
     * @param string $url 链接
     * @param array $params 参数
     * @return string
     */
    public function build($url, $params=[])
    {
        $routeMaps = app('config')->get('router');
        $reverseRoutes = array_flip($routeMaps);
        if(!empty($reverseRoutes[$url])) {
            //解析参数
            $parseUrl = $reverseRoutes[$url];
            preg_match_all("#:([a-zA-Z0-9]+)#", $parseUrl, $keys);
            if (!empty($keys[1])) {
                $keys = $keys[1];
                //得到值
                $urlParams = array_intersect_key($params, array_flip($keys));
                if(count($urlParams) == count($keys)) {
                    $parseUrl = strtr($parseUrl, array_merge([':'=>''], $urlParams));
                    $leftParams = array_diff_key($params, $urlParams);
                    if(!empty($leftParams)) {
                        $parseUrl .= '?'.http_build_query($leftParams);
                    }
                    return $parseUrl;
                }
            } else {
                $url = $parseUrl;
            }
        }
        return implode('?',[$url,http_build_query($params)]);
    }
}
