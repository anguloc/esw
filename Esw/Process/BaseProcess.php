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
use EasySwoole\RedisPool\Redis as RedisPool;

/**
 * 用于自定义command开进程
 * 不随
 *
 * Class BaseProcess
 * @package Esw\Process
 */
class BaseProcess extends AbstractProcess
{

    private static $isSingleProcess = true; // 是否为单独进程 不跟随es启动

    public function __start(Process $process)
    {
        parent::__start($process);
    }

    public static function getUserConfig()
    {
        return [
            "Esw-Process-Worker",
        ];
    }

    public function run($args)
    {
        
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