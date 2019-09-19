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
//            Logger::getInstance()->log(json_encode(['sub' => 'bcvxasdf']));
            $redis = RedisPool::defer(REDIS_POOL);


            $redis->set('exist', 1);

//            $a = $redis->keys('*');
//            var_dump($a);
//
//
//
//            Logger::getInstance()->log(json_encode(['sub' => 'bcvxasdf']));
            $b = $redis->subscribe(['test']);
            Logger::getInstance()->log(json_encode(['sub' => $b]));
            if($b){
//                $a = $redis->keys('*');
//                $aa = $redis->set('zxc',456);
//                $aaa = $redis->get('zxc');
//                var_dump($a);
//                var_dump($aa);
//                var_dump($aaa);
//                echo 123,PHP_EOL;
                while(true){
//                    $a = $redis->get('exist');
//                    Logger::getInstance()->log('asdasd:'.$a);

                    $msg = $redis->recv();
                    $msg = is_array($msg) ? json_encode($msg) : $msg;
                    Logger::getInstance()->log($msg);
                }
//                while($msg = $redis->recv()){
//                    print_r($msg);
//                }
            }
//            var_dump($b);
//            Logger::getInstance()->log($b);
//            while($msg = $redis->recv()){
//                echo 123123213;
//                Logger::getInstance()->log(json_encode($msg));
//            }
//            Logger::getInstance()->log('end');
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