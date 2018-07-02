<?php

namespace Cute;

class Session extends Service
{

    protected $configs = [
        'expire' => 60, //过期时间
        'name' => null, //session cookie 名
        'domain' => null, //域名
        'path' => null, //保存路径
    ];

    public function __construct()
    {
        $conf = $this->config();
        if(!empty($conf['expire'])) {
            session_cache_expire($conf['expire']);
        }
        if(!empty($conf['name'])) {
            session_name($conf['name']);
        }
        if(!empty($conf['path'])) {
            session_save_path($conf['path']);
        }
        
        if(!isset($_SESSION)) {
            if(!empty($conf['domain'])) {
                $domain = $conf['domain'];
                if(is_int($domain)) {
                    $domain = app('req')->domain($domain);
                    if (!empty($domain)) {
                        $domain = ".{$domain}";
                    }
                }

                if(ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    session_set_cookie_params($params['lifetime'], $params['path'], $domain);
                }
            }
            session_start();
        }
    }

    /**
     * 得到session值
     *
     * @param string $key 要取的键
     * @param string $default 默认值
     * @return array|string
     */
    public function get($key=null, $default=null)
    {
        if(is_null($key)) {
            return $_SESSION;
        }
        return array_value($_SESSION, $key, $default);
    }

    /**
     * 设置session键值
     *
     * @param array|string $key 设置的键或者键值对
     * @param string $value 值
     * @return object
     */
    public function set($key, $value=null)
    {
        if(is_array($key)) {
            array_extend($_SESSION, $key);
        } else {
            array_setval($_SESSION, $key, $value);
        }
        return $this;
    }

    /**
     * 删除session键
     *
     * @param string $key 键名
     * @return object
     */
    public function delete($key)
    {
        array_setval($_SESSION, $key, null);
        unset($_SESSION[$key]);
        return $this;
    }

    public function has($key)
    {
        return !empty($_SESSION[$key]);
    }

    /**
     * 删除会话
     *
     * @return object
     */
    public function destroy()
    {
        $_SESSION = [];

        if(ini_set('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        return $this;
    }
}