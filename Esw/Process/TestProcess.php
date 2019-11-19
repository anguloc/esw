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
use EasySwoole\MysqliPool\Mysql as MysqlPool;
use EasySwoole\Component\Process\Config as ProcessConfig;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use DHelper\RabbitMQ\RabbitMQRequeueException;
use DHelper\RabbitMQ\RabbitMQTask;
use Swoole\Coroutine\Channel;

/**
 * 用于自定义command开进程
 *
 * Class TriggerRabbitMQ
 * @package Esw\Process
 */
class TestProcess extends BaseProcess
{

    public function runTask($msg)
    {
        if (!is_array($msg)) {
            $msg = json_decode($msg, true);
        }
        if (empty($msg)) {
            return $msg;
        }



    }

    /**
     * 启动定时器进行循环扫描
     *
     * @param $arg
     * @return string|void
     */
    public function run($arg)
    {




        return '';

        go(function () {
            $redis = RedisPool::defer(REDIS_POOL);
            $key = 1;

            $redis->set('exist', 1);
            $b = $redis->subscribe(['test']);
            Logger::getInstance()->log(json_encode(['sub' => $b, 'key' => $key]));
            if ($b) {
                while (true) {
                    $msg = $redis->recv();
                    $msg = is_array($msg) ? json_encode($msg) : $msg;
                    Logger::getInstance()->log($msg . '--key:' . $key);
                }
            }
        });
        go(function () {
            $redis = RedisPool::defer(REDIS_POOL);
            $key = 2;

            $redis->set('exist', 1);
            $b = $redis->subscribe(['test']);
            Logger::getInstance()->log(json_encode(['sub' => $b, 'key' => $key]));
            if ($b) {
                while (true) {
                    $msg = $redis->recv();
                    $msg = is_array($msg) ? json_encode($msg) : $msg;
                    Logger::getInstance()->log($msg . '--key:' . $key);
                }
            }
        });
    }

    public static function getUserConfig()
    {
        return [new ProcessConfig([
            'processName' => "Esw-Process-Worker-Test",
            'arg' => [],

        ])];
    }


    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }

    private static function log($msg)
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg);
        }
        Logger::getInstance()->log($msg);
    }
}