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
 * redis订阅
 *
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