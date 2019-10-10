<?php
/**
 * Created by PhpStorm.
 * User: gk
 * Date: 2019/6/27
 * Time: 23:25
 */

namespace Esw\Command;

use DHelper\RabbitMQ\RabbitMQRequeueException;
use EasySwoole\EasySwoole\Command\CommandInterface;
use EasySwoole\MysqliPool\Mysql as MysqlPool;
use EasySwoole\RedisPool\Redis as RedisPool;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use PhpOffice\PhpWord\Style\Tab;
use Swlib\Http\Exception\ClientException;
use Swlib\Http\Exception\ConnectException;
use Swlib\Http\Exception\HttpExceptionMask;
use Swlib\Saber\Response;
use Swoole\Coroutine\Channel;
use Swlib\SaberGM;
use Swlib\Http\Exception\RequestException;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use Swoole\Table;
use Swoole\Timer;
use DHelper\RabbitMQ\RabbitMQTask;
use Swoole\Process;

/**
 * 进程控制
 *
 * Class TestCommand
 * @package Esw\Command
 */
class ProcessCommand implements CommandInterface
{
    private $masterPid;

    private static $pool = [];

    private static $processConfig = [];
    private static $queueMsgKey = "8755";


    public function commandName(): string
    {
        return 'process';
    }

    public function help(array $args): ?string
    {
        return '玩玩进程以及队列等';
    }

    public function exec(array $args): ?string
    {
        /** init */
        $this->init($args);

        /** cmd */
        $cmd = array_shift($args);
        if ($cmd) {


            $sock = stream_socket_client("unix:///tmp/php.sock");
            $data = json_encode(['code' => 1, 'msg' => 'Hello World!',]);
//            fwrite($sock, $data);
            fwrite($sock, pack('N', strlen($data)) . $data);
            fclose($sock);

            return '';
            $class = self::$processConfig['process'][$cmd] ?? null;
            if (!$class || !class_exists($class)) {
                return '参数错误或者配置错误';
            }
            try {
                $queue = msg_get_queue(self::$queueMsgKey);
                if (!$queue) {
                    return '获取队列错误';
                }
                $data = json_encode([
                    'key' => $cmd,
                    'class' => $class,
                    'args' => $args,
                ], JSON_UNESCAPED_UNICODE);
                if (!msg_send($queue, 1, $data, false, false, $error_code)) {
                    return "send fail error_code is {$error_code}";
                }
            } catch (\Throwable $e) {
                return '发送消息失败';
            }
            return 'success';
        }

        /** Master */
        $this->startMaster($args);

        return '';
    }

    private function init(array $args)
    {
        self::initProcessConfig();


    }

    private function startMaster(array $args)
    {


        return '';
        Process::daemon();
        self::setProcessName('Esw-Process-Pool');

        $pool = new Process\Pool(1,SWOOLE_IPC_SOCKET,0,false);

        $pool->on('message', function(Process\Pool $pool, $data){
            self::log('message-'.$data);

            $pool->shutdown();

//            $a = new Process(function(Process $process){
//                self::log('zxbhfasd');
//                $process->name('Esw-Process-Worker');
//            });
//            $a::signal(SIGUSR1, function(){
//                self::log('xzhs');
//            });
//            $a->start();
        });
        $pool->listen('unix:/tmp/php.sock');

        $pool->on('workerStart',function(Process\Pool $pool, $worker_id){
            /** @var Process $process */
            $process = $pool->getProcess($worker_id);
            $process->name('Esw-Process-Master');
            $process::signal(SIGTERM, function($sig)use($process){
                $process->exit(0);
            });
            self::log(json_encode($pool->workers));
        });

        $pool->start();

        return '';


        /** @see __start */
        $process = new Process([$this, '__start'], false, 0, true);
        $process->useQueue(self::$queueMsgKey);
//        $process->useQueue(self::$queueMsgKey, 2 | Process::IPC_NOWAIT);


        $process->start();
    }

    public function __start(Process $process)
    {
        self::setProcessName('Esw-Process-Master');

        Process::signal(SIGCHLD, function($sig) {
            while($ret =  Process::wait(false)) {

            }
        });

        Process::signal(SIGUSR1, function(){
            self::log('sigusr1-'. json_encode(self::$pool, JSON_UNESCAPED_UNICODE));
        });

        Process::signal(SIGTERM, function () use ($process) {
            go(function () use ($process) {
//                swoole_event_del($process->pipe);
//                $channel = new Channel(8);
//                go(function () use ($channel) {
//                    try {
//                        $channel->push($this->onShutDown());
//                    } catch (\Throwable $throwable) {
//                        $this->onException($throwable);
//                    }
//                });
//                $channel->pop(3);
//                swoole_event_exit();
                Process::signal(SIGTERM, null);
                $process->exit(0);
            });
        });
//        swoole_event_add($process->pipe, function () use ($process) {
//            try {
//                $this->onPipeReadable($process);
//            } catch (\Throwable $throwable) {
//                $this->onException($throwable);
//            }
//        });

        Timer::tick(1000,function(){
            self::log('1');
        });




        // 队列
//        go(function()use($process){
//            Timer::tick(3000, function () use ($process) {
//                if ($msg = $process->pop()) {
//                    self::log('msg-'.$msg);
//                    $msg = json_decode($msg, true);
//                    $key = $msg['key'] ?? null;
//                    $class = $msg['class'] ?? null;
//                    $args = $msg['args'] ?? [];
//                    if (!$key || !$class || !class_exists($class)) {
//                        self::log('process-queue-msg-class-error' . json_encode($msg, JSON_UNESCAPED_UNICODE));
//                        return false;
//                    }
//                    try {
//                        $process = new Process(function (Process $process) use ($key, $class, $args) {
//                            $this->startChildProcess($process, $key, $class, $args);
//                        }, null, 0, true);
//                        $pid = $process->start();
//                        if (!$pid) {
//                            self::log('process-queue-msg-start-error' . json_encode($msg, JSON_UNESCAPED_UNICODE));
//                            return false;
//                        }
//                        self::$pool[$key] = [
//                            'class' => $class,
//                            'process' => $process,
//                            'pid' => $pid,
//                        ];
//                        unset($process);
//                    }catch(\Throwable $e){
//                        self::log(catch_exception($e));
//                    }
//                }
//            });
//        });


    }

    private function startChildProcess(Process $process, $key, $class, $args)
    {
        $process->name("Esw-Process-{$key}");
    }


    public function main($num = 1)
    {

        $normal_queue_name = "php:normal_queue_name";

        // check queue config
//        list($servers, $queue_name, $timeout_queue_name, $exchange_name, $callback, $consumers, $trace, $queue_timeout, $memory_limit) = static::getQueueConfig($queue_index);

        $queue_name = 'test_queue_name';
        $exchange_name = 'test_exchange_name';
        $timeout_queue_name = '';
        $queue_timeout = '';

        $callback = function () use ($num) {
            Logger::getInstance()->log('rabbitmq-qqqq-' . $num . '-' . json_encode(func_get_args()));
            return ['status' => 1, 'data' => 'zxhdfgsf'];
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
                self::log('wait');
                // 设置上下文 LogId
//                $contextLogId = json_decode($msg->body, true)['context_log_id'];
//                if ($contextLogId) {
//                    Logger::getInstance()->log($contextLogId);
//                }
                $res = call_user_func($callback, $msg->body);
                if ((is_bool($res) && $res == false) || (is_array($res) && $res['status'] != 0)) {
//                    Logger::getInstance()->log("rabbitmq task run error: {$msg->body}, res: " . json_encode($res));
                }
                $channel->basic_nack($msg->delivery_info['delivery_tag'], false, true);
                return ['status' => 1, 'data' => 'zxjhsetert',];
            } catch (RabbitMQRequeueException $queue_exception) {  // 重新入队（该消息的 handler 会重新运行）
                Logger::getInstance()->log("rabbitmq task requeue: [queue_name {$queue_name}] {$msg->body}");
                $channel->basic_nack($msg->delivery_info['delivery_tag'], false, true); // 发送信号提醒mq该消息不能被删除，且重新入队列
                return;
            } catch (\Exception $e) {
                $exceptionInfoStr = "{$e->getFile()} {$e->getLine()} {$e->getMessage()}";
                Logger::getInstance()->log("rabbitmq task catch error: [queue_name {$queue_name}] {$msg->body}, exception: {$exceptionInfoStr}");
            }
            return ['status' => 1, 'data' => 'zxjhsetert1111',];
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

    protected function onException(\Throwable $throwable, ...$args)
    {
        Logger::getInstance()->log('exceotion' . catch_exception($throwable));
    }

    protected function onShutDown()
    {
        Logger::getInstance()->log('shotdown');
    }

    protected function onPipeReadable(Process $process)
    {
        /*
         * 由于Swoole底层使用了epoll的LT模式，因此swoole_event_add添加的事件监听，
         * 在事件发生后回调函数中必须调用read方法读取socket中的数据，否则底层会持续触发事件回调。
         */
        Logger::getInstance()->log('asdasdfasd');
        $process->read();
    }

    private static function setProcessName($name)
    {
        if (PHP_OS != 'Darwin' && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        }
    }

    private static function log($msg)
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg);
        }
        Logger::getInstance()->log($msg);
    }

    private static function initProcessConfig()
    {
        $json_file = EASYSWOOLE_ROOT . '/Esw/Command/config/process.json';
        $json = file_get_contents($json_file);
        $config = json_decode($json, true);

        if (empty($config) || !is_array($config)) {
            return false;
        }
        return self::$processConfig = $config;
    }

}