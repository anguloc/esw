<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-26
 * Time: 23:18
 */

namespace Esw\Process;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Http\Utility;
use EasySwoole\Utility\File;
use Swoole\Process;
use Swoole\Table;
use Swoole\Timer;
use EasySwoole\EasySwoole\Logger;

/**
 * 暴力热重载
 * Class SubRedis
 * @package Esw\Process
 */
class SubRedis extends AbstractProcess
{
    /** @var \swoole_table $table */
    protected $table;
    protected $isReady = false;

    protected $monitorDir; // 需要监控的目录
    protected $monitorExt; // 需要监控的后缀

    /**
     * 启动定时器进行循环扫描
     */
    public function run($arg)
    {
        Timer::tick(1000, function(){
            Logger::getInstance()->log('test process ' . \EasySwoole\Utility\Random::character());
        });

//        while (1) {
//
//        }
    }


    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}