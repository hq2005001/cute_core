<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-7-19
 * Time: 下午8:21
 */

namespace Cute\interfaces;


interface Seedable
{
    /**
     * 用于seed文件生成数据库数据
     * @return mixed
     */
    public function run();
}