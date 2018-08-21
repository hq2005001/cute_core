<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-7-19
 * Time: 下午7:55
 */

namespace Cute\ext;


use Cute\Crontab;

class Seeder extends Crontab
{

    protected $seeders = [];

    protected function start()
    {
        parent::start();
        foreach($this->seeders as $seed) {
            (new $seed())->run();
        }
    }

}