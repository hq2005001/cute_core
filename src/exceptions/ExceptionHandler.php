<?php
/**
 * 异常处理类
 * @author hq <305706352@qq.com>
 */

namespace Cute\exceptions;

class ExceptionHandler 
{

    public function handle($e) 
    {
        $message = $e->getMessage();
        $result = [
            'status' => 0,
            'msg' => $message,
            'recordset' => new \StdClass()
        ];
        if(app('req')->type() != 'json') {
            $result = $message;
        }
        app()->log($message);
        if(IS_CRONTAB) {
            $this->handleCrontabException($e, $result);
        } else {
            $this->handleException($e, $result);
        }
    }
    
    protected function handleCrontabException($e, $result) {
        dd($result);
    }


    protected function handleException($e, $result)
    {
        if($e instanceof FileNotFoundException) {
            app('res')->dispatch($result, 404);
        } else if( $e instanceof ClassNotFoundException) {
            app('res')->dispatch($result, 404);
        } else if($e instanceof RouteNotFoundException) {
            app('res')->dispatch($result, 404);
        } else{
            app('res')->dispatch($result, 404);
        }
    }
}