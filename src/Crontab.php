<?php

namespace Cute;


class Crontab
{

    protected $interval = 6000;

     /**
     * 按时间间隔执行任务
     */
    public function run() {
        $starttime = microtime(true);
        $endtime = $starttime + 600;
        while (true) {
            $starttime += $this->interval;
            $this->start();
            if ($starttime >= $endtime)
                break;
            // sleep时间 = 下次执行时间 - 当前时间，程序执行时间不做计算，比如间隔10秒钟执行，程序执行8秒钟，则sleep 2 秒钟
            $microtime = microtime(true);
            if ($starttime > $microtime) {
                $sleeptime = $starttime - microtime(true);
                usleep($sleeptime * 1000000);
            } else {
                $starttime = $microtime;
            }
        }
    }

    protected function start()
    {

    }
}