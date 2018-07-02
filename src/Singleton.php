<?php

namespace Cute;

class Singleton
{
    protected static $obj = null;

    protected function __construct()
    {

    }

    protected function __clone()
    {

    }

    public static function getInstance()
    {
        if(is_null(static::$obj)) {
            static::$obj = new static();
        }
        return static::$obj;
    }
}