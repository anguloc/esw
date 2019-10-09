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
 *
 * Class SubRedis
 * @package Esw\Process
 */
class TriggerRabbitMQ extends AbstractProcess
{

    public static $dead_exchanger_type = 'direct';
    public static $dead_exchanger_name = 'dead_exchanger';

    public static $exchanger_type = 'direct';
    public static $exchanger_passive = false;
    public static $exchanger_durable = true;
    public static $exchanger_auto_delete = false;

    public static $queue_passive = false;
    public static $queue_durable = true;
    public static $queue_auto_delete = false;
    public static $queue_exclusive = false;

    public static $message_mandatory = true;

    /**
     * 启动定时器进行循环扫描
     */
    public function run($arg)
    {
//        go(function() {
//            Timer::tick(1000, function () {
//                Logger::getInstance()->log('test process ' . \EasySwoole\Utility\Random::character());
//            });
//        });
//        return '';
        go(function(){
            $redis = RedisPool::defer(REDIS_POOL);
            $key = 1;

            $redis->set('exist', 1);
            $b = $redis->subscribe(['test']);
            Logger::getInstance()->log(json_encode(['sub' => $b,'key' => $key]));
            if($b){
                while(true){
                    $msg = $redis->recv();
                    $msg = is_array($msg) ? json_encode($msg) : $msg;
                    Logger::getInstance()->log($msg . '--key:'.$key);
                }
            }
        });
        go(function(){
            $redis = RedisPool::defer(REDIS_POOL);
            $key = 2;

            $redis->set('exist', 1);
            $b = $redis->subscribe(['test']);
            Logger::getInstance()->log(json_encode(['sub' => $b,'key' => $key]));
            if($b){
                while(true){
                    $msg = $redis->recv();
                    $msg = is_array($msg) ? json_encode($msg) : $msg;
                    Logger::getInstance()->log($msg . '--key:'.$key);
                }
            }
        });
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