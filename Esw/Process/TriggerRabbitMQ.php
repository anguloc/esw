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
class TriggerRabbitMQ extends BaseProcess
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
     *
     * @param $arg
     * @return string|void
     */
    public function run($arg)
    {

        $normal_queue_name = "php:normal_queue_name";

        // check queue config
//        list($servers, $queue_name, $timeout_queue_name, $exchange_name, $callback, $consumers, $trace, $queue_timeout, $memory_limit) = static::getQueueConfig($queue_index);

        $queue_name = 'test_queue_name';
        $exchange_name = 'test_exchange_name';
        $timeout_queue_name = '';
        $queue_timeout = '';
        $num = 1;

        $callback = function () use ($num) {
            Logger::getInstance()->log('rabbitmq-qqqq-' . $num . '-' . json_encode(func_get_args()));
            return false;
            return ['status' => 1, 'data' => 'zxhdfgsf'];
        };

        if (!empty($timeout_queue_name) && $queue_timeout < 0) {
            echo "error QueueConfig, queue_name={$queue_name}" . PHP_EOL;
            exit;
        }

        $connection = new AMQPStreamConnection(HOST_1, PORT_3, USER_1, PWD_1);

        $channel = $connection->channel();

        $channel->exchange_declare("dead_exchanger", self::$dead_exchanger_type, self::$exchanger_passive, self::$exchanger_durable, self::$exchanger_auto_delete);

        $channel->exchange_declare($exchange_name, self::$exchanger_type, self::$exchanger_passive, self::$exchanger_durable, self::$exchanger_auto_delete);
        if (empty($timeout_queue_name)) {
            $channel->queue_declare($queue_name, self::$queue_passive, self::$queue_durable, false, self::$queue_auto_delete);
            $channel->queue_bind($queue_name, $exchange_name);
        } else if ($queue_timeout != -1) {  // 如果有 dead queue，且 timeout 的定义不为空
            // 定义超时删除并自动进入死信(超时)队列的消息属性
            $route_key = $queue_name;
            $queue_args = new AMQPTable([
                'x-message-ttl' => $queue_timeout,
                'x-dead-letter-exchange' => self::$dead_exchanger_name,
                'x-dead-letter-routing-key' => $route_key
            ]);

            $channel->queue_declare($queue_name, self::$queue_passive, self::$queue_durable, false, self::$queue_auto_delete, false, $queue_args);
            $channel->queue_declare($timeout_queue_name, self::$queue_passive, self::$queue_durable, false, self::$queue_auto_delete);  // 声明一个延时队列

            $channel->queue_bind($queue_name, $exchange_name);
            $channel->queue_bind($timeout_queue_name, self::$dead_exchanger_name, $route_key);  // 绑定死信（超时）队列的路径
        }

        $consume = function ($msg) use ($callback, $channel, $queue_name) {
            try {
                self::log('aa');
                $res = call_user_func($callback, $msg->body);
                self::log('bb');
                if ((is_bool($res) && $res == false) || (is_array($res) && $res['code'] != 0)) {
                    $channel->basic_nack($msg->delivery_info['delivery_tag'], false, true);
                    return false;
                }
                self::log('cc');
                $channel->basic_ack($msg->delivery_info['delivery_tag']);   // 发送信号提醒mq可删除该信息
                return true;
            } catch (RabbitMQRequeueException $queue_exception) {  // 重新入队（该消息的 handler 会重新运行）
                self::log("rabbitmq task requeue: [queue_name {$queue_name}] {$msg->body}");
                $channel->basic_nack($msg->delivery_info['delivery_tag'], false, true); // 发送信号提醒mq该消息不能被删除，且重新入队列
                return false;
            } catch (\Exception $e) {
                $exceptionInfoStr = "{$e->getFile()} {$e->getLine()} {$e->getMessage()}";
                self::log("rabbitmq task catch error: [queue_name {$queue_name}] {$msg->body}, exception: {$exceptionInfoStr}");
                return false;
            }
        };

        $channel->basic_qos(null, 1, null); // 设置一次只从queue取一条信息，在该信息处理完（消费者没有发送ack给mq），queue将不会推送信息给该消费者

        // no_ack:false 表示该队列的信息必须接收到消费者信号才能被删除
        // 消费者从queue拿到信息之后，该信息不会从内存中删除，需要消费者处理完之后发送信号通知mq去删除消息（如果没此通知，queue会不断积累旧的信息不会删除）
        // 超时队列：推送message到消息队列，但不主动去该队列获取message,等到ttl超时，自动进入绑定的死信队列，在死信队列处理业务
        if (empty($timeout_queue_name)) {
            $channel->basic_consume($queue_name, '', false, false, false, false, $consume);
        } else {
            $channel->basic_consume($timeout_queue_name, '', false, false, false, false, $consume);
        }
//        while (count($channel->callbacks)) {
//            $channel->wait();
//        }

        go(function()use($channel,$connection){
            $chan = new Channel(8);

//        go(function()use($channel){
            Timer::tick(1000, function()use($channel){
                if (count($channel->callbacks)) {
                    $channel->wait();
                }
            });
//        });


            $chan->pop();

            $channel->close();
            $connection->close();
        });



        return '';
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

    public static function getUserConfig()
    {
        return [new ProcessConfig([
            'processName' => "Esw-Process-Worker-RabbitMQ",
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