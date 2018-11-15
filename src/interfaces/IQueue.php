<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-7-19
 * Time: 下午8:21
 */

namespace Cute\interfaces;


interface IQueue
{
    /**
     * 设置
     * @param $key
     * @param $data
     * @return mixed
     */
    public function set($key, $data);

    /**
     * 获取
     * @param $key
     * @return mixed
     */
    public function get($key);

    /**
     * 删除
     * @param $key
     * @return mixed
     */
    public function delete($key);

}