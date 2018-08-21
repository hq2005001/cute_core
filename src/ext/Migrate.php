<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-7-19
 * Time: 下午7:55
 */

namespace Cute\ext;


use Cute\Crontab;

class Migrate extends Crontab
{

    protected $migrates = [];

    protected function start()
    {
        parent::start();
        foreach($this->migrates as $migrate) {
            (new $migrate())->create();
        }
    }

}