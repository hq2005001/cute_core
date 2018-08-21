<?php

namespace Cute;


class View
{
    
    protected $obj = null;
    
    public function __construct()
    {
        $viewPath = app('config')->get('view.template_path');
        $loader = new \Twig_Loader_Filesystem($viewPath);
        $this->obj = new \Twig_Environment($loader, [
            'debug' => true,
            'cache' => app('config')->get('view.cache_path'),
        ]);
        $this->obj->registerUndefinedFunctionCallback(function ($name) {
            if (function_exists($name)) {
                return new \Twig_SimpleFunction($name, $name);
            }
            
            return false;
        });
    }
    
    public function getObj()
    {
        return $this->obj;
    }
    
    /**
     * 渲染视图
     * @param type $path
     * @param type $data
     * @return type
     */
    public function render($path, $data= [])
    {
        return $this->obj->render($path, $data);
    }

    /**
     * 渲染字段
     */
    public function assign($key=null, $value=null) {
        if(is_array($key)) {
            $this->obj->mergeGlobals($key);
        } else {
            $this->obj->addGlobal($key, $value);
        }
    }
}