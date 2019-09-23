<?php
/**
 * Created by PhpStorm.
 * User: gk
 * Date: 2019/6/27
 * Time: 23:25
 */

namespace Esw\Command;

use common\exceptions\Rabbitmq\RabbitmqRequeueException;
use EasySwoole\EasySwoole\Command\CommandInterface;
use EasySwoole\MysqliPool\Mysql as MysqlPool;
use EasySwoole\RedisPool\Redis as RedisPool;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Swlib\Http\Exception\ClientException;
use Swlib\Http\Exception\ConnectException;
use Swlib\Http\Exception\HttpExceptionMask;
use Swlib\Saber\Response;
use Swoole\Coroutine\Channel;
use Swlib\SaberGM;
use Swlib\Http\Exception\RequestException;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use Swoole\Timer;

/**
 * 进程控制
 *
 * Class TestCommand
 * @package Esw\Command
 */
class ProcessCommand implements CommandInterface
{

    public static $dead_exchanger_type = 'direct';
    public static $dead_exchanger_name = 'dead_exchanger';

    public static $exchanger_type        = 'direct';
    public static $exchanger_passive     = false;
    public static $exchanger_durable     = true;
    public static $exchanger_auto_delete = false;

    public static $queue_passive     = false;
    public static $queue_durable     = true;
    public static $queue_auto_delete = false;
    public static $queue_exclusive   = false;

    public static $message_mandatory = true;

    const BASE_URL = 'http://www.httpbin.org';
    /** @var Channel */
    private $dataChan; // 入库数据
    /** @var Channel */
    private $rawChan; // 原始数据 需要解析
    /** @var Channel */
    private $chan; // 测试用
    /** @var Channel */
    private $Xchan; // 测试用
    /** @var Channel */
    private $Ychan; // 测试用
    /** @var Channel */
    private $Mchan; // 测试用
    /** @var Channel */
    private $Nchan; // 测试用

    private $start_time;
    private $end_time;
    private $end = false;

    private $chanLogTick;

    const TABLE = 'spiders_copy2';

    const GET = '/get';
    const IMG_JPEG = '/image/jpeg';
    const IMG_PNG = '/image/png';
    const IMG_SVG = '/image/svg';
    const IMG_WEBP = '/image/webp';

    private $foo;
    private $bar;
    private $x;
    private $y;
    private $m;
    private $n;


    public function commandName(): string
    {
        return 'process';
    }

    public function exec(array $args): ?string
    {
//        swoole_set_process_name('eeee');

//        $process = new \Swoole\Process(function(){

            $this->main();

            die();


//        },null,0,true);



        return $process->start();

        return 'test';
    }

    public function help(array $args): ?string
    {
        return '测试一些玩法';
    }


    public function main()
    {


        $normal_queue_name = "php:normal_queue_name";

        // check queue config
//        list($servers, $queue_name, $timeout_queue_name, $exchange_name, $callback, $consumers, $trace, $queue_timeout, $memory_limit) = static::getQueueConfig($queue_index);

        $queue_name = 'test_queue_name';
        $exchange_name = 'test_exchange_name';
        $timeout_queue_name = '';
        $queue_timeout = '';

        $callback = function(){
            Logger::getInstance()->log('rabbitmq-qqqq'. json_encode(func_get_args()));
        };

        if (!empty($timeout_queue_name) && $queue_timeout < 0) {
            echo "error QueueConfig, queue_name={$queue_name}" . PHP_EOL;
            exit;
        }


        $rabbitmq_config = Config::getInstance()->getConf('rabbitmq');
//        print_r($rabbitmq_config);
//        die;
        $host = $rabbitmq_config['host'];
        $port = $rabbitmq_config['port'];
        $user = $rabbitmq_config['user'];
        $pass = $rabbitmq_config['password'];

        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
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
                // 设置上下文 LogId
                $contextLogId = json_decode($msg->body, true)['context_log_id'];
                if ($contextLogId) {
                    Logger::getInstance()->log($contextLogId);
                }
                $res = call_user_func($callback, $msg->body);
                if ((is_bool($res) && $res == false) || (is_array($res) && $res['status'] != 0)) {
                    Logger::getInstance()->log("rabbitmq task run error: {$msg->body}, res: " . json_encode($res));
                }
            } catch (RabbitmqRequeueException $queue_exception) {  // 重新入队（该消息的 handler 会重新运行）
                Logger::getInstance()->log("rabbitmq task requeue: [queue_name {$queue_name}] {$msg->body}");
                $channel->basic_nack($msg->delivery_info['delivery_tag'], false, true); // 发送信号提醒mq该消息不能被删除，且重新入队列
                return;
            } catch (\Exception $e) {
                $exceptionInfoStr = "{$e->getFile()} {$e->getLine()} {$e->getMessage()}";
                Logger::getInstance()->log("rabbitmq task catch error: [queue_name {$queue_name}] {$msg->body}, exception: {$exceptionInfoStr}");
            }
            $channel->basic_ack($msg->delivery_info['delivery_tag']);   // 发送信号提醒mq可删除该信息
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

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

    }

}