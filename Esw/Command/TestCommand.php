<?php
/**
 * Created by PhpStorm.
 * User: gk
 * Date: 2019/6/27
 * Time: 23:25
 */

namespace Esw\Command;

use EasySwoole\EasySwoole\Command\CommandInterface;
use EasySwoole\MysqliPool\Mysql as MysqlPool;
use EasySwoole\RedisPool\Redis as RedisPool;
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
 * 测试一些玩法
 * Class TestCommand
 * @package Esw\Command
 */
class TestCommand implements CommandInterface
{
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
        return 'test';
    }

    public function exec(array $args): ?string
    {

        $normal_queue_name = "php:normal_queue_name";

        // check env
        $pid_dir = ROOT_PATH . "/zhichi/protected/commands/bin/rabbitmq/pid/";
        $pid_file = $pid_dir . $normal_queue_name . ".pid";
        if (!file_exists($pid_dir)) {
            exec("mkdir -p {$pid_dir} && chmod 777 -R {$pid_dir}");
        }

        if ((is_writable($log_file) && is_writable($error_log_file) && is_writable($pid_dir) && is_writable($pid_file))) {
            echo "文件 {$log_file}, {$error_log_file}, {$pid_dir}, {$pid_file} 不可写" . PHP_EOL;
            exit;
        }

        // check queue config
        list($servers, $queue_name, $timeout_queue_name, $exchange_name, $callback, $consumers, $trace, $queue_timeout, $memory_limit) = static::getQueueConfig($queue_index);
        if (!empty($timeout_queue_name) && $queue_timeout < 0) {
            echo "error QueueConfig, queue_name={$queue_name}" . PHP_EOL;
            exit;
        }

        global $argc;
        global $argv;
        if ($argc == 1) {
            $argc += 1;
            $argv[] = "start";
        }

        Worker::$stdoutFile = $log_file;
        Worker::$logFile = $workerman_log_file;
        Worker::$pidFile = $pid_file;
        Worker::$daemonize = true;
        $worker = new Worker();
        $worker->count = $consumers;
        $worker->name = $normal_queue_name;
        $worker->onWorkerStart = function (Worker $worker) use ($queue_index, $parent_error_reporting, $memory_limit) {
            if ($memory_limit) {
                ini_set("memory_limit", $memory_limit);
            }
            error_reporting($parent_error_reporting);
            self::do_listen($queue_index);
        };
        Worker::runAll();


        $_SERVER['SERVER_ADDR'] = getHostByName(getHostName());
        list($servers, $queue_name, $timeout_queue_name, $exchange_name, $callback, $consumers, $trace, $queue_timeout) = static::getQueueConfig($queue_index);

        // 随机选一个 server
        $server = self::selectServer($servers);
        $host = RabbitmqConfig::$arrServers[$server]['host'];
        $port = RabbitmqConfig::$arrServers[$server]['port'];
        $user = RabbitmqConfig::$arrServers[$server]['user'];
        $pass = RabbitmqConfig::$arrServers[$server]['password'];

        // 直连本机
        //$host = '0.0.0.0';
        //$port = 5672;
        //$user = 'guest';
        //$pass = 'uB8!yDEmpeh9';

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

        $consume = function ($msg) use ($callback, $channel, $trace, $queue_name) {
            try {
                // 设置上下文 LogId
                $contextLogId = json_decode($msg->body, true)['context_log_id'];
                if ($contextLogId) {
                    \Loggers::getInstance("rabbitmq_task")->setLogId($contextLogId);
                }
                if ($trace) {
                    \Loggers::getInstance("rabbitmq_task")->notice("run rabbitmq handler: [queue_name {$queue_name}] param=" . $msg->body);
                }
                $res = call_user_func($callback, $msg->body);
                if ((is_bool($res) && $res == false) || (is_array($res) && $res['status'] != 0)) {
                    \Loggers::getInstance('rabbitmq_task')->warning("rabbitmq task run error: {$msg->body}, res: " . json_encode($res));
                }
            } catch (RabbitmqRequeueException $queue_exception) {  // 重新入队（该消息的 handler 会重新运行）
                \Loggers::getInstance('rabbitmq_task')->warning("rabbitmq task requeue: [queue_name {$queue_name}] {$msg->body}");
                $channel->basic_nack($msg->delivery_info['delivery_tag'], false, true); // 发送信号提醒mq该消息不能被删除，且重新入队列
                return;
            } catch (Exception $e) {
                $exceptionInfoStr = "{$e->getFile()} {$e->getLine()} {$e->getMessage()}";
                \Loggers::getInstance('rabbitmq_task')->warning("rabbitmq task catch error: [queue_name {$queue_name}] {$msg->body}, exception: {$exceptionInfoStr}");
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
        exit;


        return 1;


        swoole_set_process_name('eeee');

        $process = new \Swoole\Process(function(){
            Timer::tick(1000, function(){
                Logger::getInstance()->log(123);
            });







        },null,0,true);



        return $process->start();


        return 'asdsad';

//        go(function(){
//            $redis_config = Config::getInstance()->getConf(REDIS_POOL);
//
//            $redis = new \Swoole\Coroutine\Redis();
//            $redis->connect($redis_config['host'], $redis_config['port']);
//            $redis->auth($redis_config['auth']);
//            $b = $redis->subscribe(['test', 'channel2', 'channel3']);
//            if ($b) // 或者使用psubscribe
//            {
//                while ($msg = $redis->recv()) {
//                    // msg是一个数组, 包含以下信息
//                    // $type # 返回值的类型：显示订阅成功
//                    // $name # 订阅的频道名字 或 来源频道名字
//                    // $info  # 目前已订阅的频道数量 或 信息内容
//                    print_r($msg);
//                    list($type, $name, $info) = $msg;
//                    if ($type == 'subscribe') // 或psubscribe
//                    {
//                        // 频道订阅成功消息，订阅几个频道就有几条
//                    }
//                    else if ($type == 'unsubscribe' && $info == 0) // 或punsubscribe
//                    {
//                        break; // 收到取消订阅消息，并且剩余订阅的频道数为0，不再接收，结束循环
//                    }
//                    else if ($type == 'message') // 若为psubscribe，此处为pmessage
//                    {
//                        // 打印来源频道名字
//                        var_dump($name);
//                        // 打印消息
//                        var_dump($info);
//                        // 处理消息
//                        // balabalaba....
//                        $need_unsubscribe = false;
//                        if ($need_unsubscribe) // 某个情况下需要退订
//                        {
//                            $redis->unsubscribe(); // 继续recv等待退订完成
//                        }
//                    }
//                }
//            }
//        });
//        return '';


        return '';




        go(function(){
            $redis = RedisPool::defer(REDIS_POOL);

//            $a = $redis->keys('*');
//            var_dump($a);
//
//
//
            $b = $redis->subscribe(['test']);
            if($b){
                $a = $redis->keys('*');
                $aa = $redis->set('zxc',456);
                $aaa = $redis->get('zxc');
                var_dump($a);
                var_dump($aa);
                var_dump($aaa);
                echo 123,PHP_EOL;
//                while(true){
//                    $msg = $redis->recv();
//                }
//                while($msg = $redis->recv()){
//                    print_r($msg);
//                }
            }
            var_dump($b);
//            Logger::getInstance()->log($b);
//            while($msg = $redis->recv()){
//                echo 123123213;
//                Logger::getInstance()->log(json_encode($msg));
//            }
//            Logger::getInstance()->log('end');
        });




        return '';
        $this->foo = [];
        for ($i=1;$i<=5;$i++) {
            $this->foo[] = [
//                'uri' => self::BASE_URL . self::GET,
                'uri' => 'http://www.baidu.com',
            ];
        }

//        go(function() {
//            SaberGM::exceptionReport(HttpExceptionMask::E_NONE);
////            $response = SaberGM::get(self::BASE_URL . self::GET);
//            $responses = SaberGM::requests($this->foo);
//            $result = "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" . "consuming-time: {$responses->time}s\n";
//            print_r($result);
//        });
//
//        return '';
//         Logger::getInstance()->console('asd');
        $this->chan = new Channel(2);

        $func = function(){
            while(true) {
                $i = $this->chan->pop();
                fwrite(STDOUT, $i);

                $responses = SaberGM::requests($this->foo);
                $result = "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" . "consuming-time: {$responses->time}s\n";
                fwrite(STDOUT, $result);
            }
        };

//        $this->chan->push(1);
//        return '';
        go($func);


        go(function () {
            for ($i = 1; $i <= 10; $i++) {
                $this->chan->push($i);
            }
        });



        return 'test';
    }

    public function help(array $args): ?string
    {
        return '测试一些玩法';
    }

    private function main()
    {
        for ($i = 700515; $i <= 775497; $i += 10) {
            // 这里如果用多协程的话  saber 并发请求里会一起返回  所以不能这样做
            $this->runRequest($i);
        }

        $this->end();
    }

    /**
     * 注册任务分发
     */
    private function runRequest($i)
    {
        foreach (range($i, $i + 9) as $item) {
            $urls[] = [
                'uri' => $this->baseUrl . $page = "/ckj1/{$item}.html",
            ];
        }


        try {
            $responses = SaberGM::requests($urls, ['timeout' => 5, 'retry_time' => 3]);
        } catch (ConnectException $e) {
//            stdout($i);
//            stdout('connect ' . $page);
//            die;
        } catch (RequestException $e) {
//            print_r($e);
//            stdout($i);
//            stdout('timeout ' . $page);
//            die;
//            return;
        } catch (\Exception $e) {
//            stdout($i);
//            stdout('new error' . json_encode($e, JSON_UNESCAPED_UNICODE));
//            die;
//            return;
        }
//        if($responses->error_num > 0 ){

//            die;
//        }
        $result = "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" . "consuming-time: {$responses->time}s";
        stdout($result);

        stdout(count($responses));

        foreach ($responses as $response) {
            /** @see registerParseData() */
            $this->rawChan->push($response);
        }
    }

    /**
     * 数据入库
     */
    private function registerInsertOrUpdate()
    {
        while (true) {
            if ($this->isEnd()) {
                break;
            }

            $data = $this->dataChan->pop();
            if (empty($data) || !is_array($data)) {
                continue;
            }

            Logger::getInstance()->log(print_r($data, true));
            continue;
            // 如果是多维数组 则当批量插入
            $db = MysqlPool::defer(MYSQL_POOL);
            try {
                if (count($data) == count($data, 1)) {
                    $db->insert(self::TABLE, $data);
                } else {
                    $db->insertMulti(self::TABLE, $data);
                }
            } catch (\Throwable $e) {
                Logger::getInstance()->error(catch_exception($e), 'MYSQL');
            }

        }
    }

    /**
     * 处理数据
     */
    private function registerParseData()
    {
        while (true) {
            if ($this->isEnd()) {
                break;
            }

            /**
             * @see runRequest()
             * @var $data Response
             */
            $response = $this->rawChan->pop();
            if (!$response) {
                continue;
            }

            $url = $response->getUri()->__toString();
            $body = $response->getBody()->__toString();
            preg_match('/<span class="cat_pos_l">您的位置：<a href="\/">首页<\/a>&nbsp;&nbsp;&raquo;&nbsp;&nbsp;<a href=\'(.*)\' >(.*)<\/a>&nbsp;&nbsp;&raquo;&nbsp;&nbsp;(.*)<\/span>/', $body, $match);
            $data = [
                'title' => $match[3] ?: '',
                'url' => $url,
                'key' => $match[2] ?: '',
                'add_time' => time(),
            ];

            if ($data) {
                /** @see registerInsertOrUpdate() */
                $this->dataChan->push($data);
            }
        }
    }

    private function isEnd()
    {
        return $this->end;
    }

    private function start()
    {
        // 忽略 saber 所有异常
//        SaberGM::exceptionReport(
//            HttpExceptionMask::E_NONE
//        );


        SaberGM::exceptionHandle(function (\Exception $e) {
            // 异常入库
//            if(is_callable([$e, 'getRequest'])){
//                stdout($e->getRequest()->getUri()->getPath());
//            }
            if ($e instanceof RequestException) {
                $excep_data = [
                    'title' => '',
                    'url' => '',
                    'key' => $e->getRequest()->getUri()->getPath(),
                    'form_data' => json_encode(catch_exception($e), JSON_UNESCAPED_UNICODE),
                    'add_time' => time(),
                ];
                /** @see registerInsertOrUpdate */
                $this->dataChan->push($excep_data);
//                stdout(catch_exception($e, $e->getRequest()->getUri()->getPath().'--'));
            }
//            echo get_class($e) . " is caught!\n";
            return true;
        });


        $this->start_time = microtime(true);
        // 设置结束状态为  没有结束
        $this->end = false;
        // 初始化 channel
        $this->dataChan = new Channel(5);
        $this->rawChan = new Channel(5);

        // 加个定时器 看下chan使用情况
//        $this->chanLogTick = \Swoole\Timer::tick(5000, function(){
//            Logger::getInstance()->notice(print_r([
//                'dataChan' => $this->dataChan->stats(),
//                'rawChan' => $this->rawChan->stats(),
//            ], true));
//        });

    }

    private function end()
    {
        // todo: 清空各项任务
        $this->end = true;

        \Swoole\Coroutine::sleep(20);

        $this->dataChan->close();
        $this->rawChan->close();

        $this->end_time = microtime(true);
        stdout('用时' . ($this->end_time - $this->start_time));

    }

    private function store()
    {
        $url1 = 'm5.amap.com/ws/mapapi/poi/infolite/?ent=2&in=FbZ6o6HqqPaMeUHcGq5hCUd%2FwmjB9cXxFFaFG0DhuduZK3mxihz0B43OO95Mkyk7anxajEB74ECYTPsUfJTNSKx%2FKHiCRsJ8zGlGuTyt8%2FT9IGI%2F%2FowkW4mS0ek6%2FwjM9Cv7DJAXyEovxsi28EW78gVbhWeFUuV7Y3b0IZ5RpvXQ%2B8%2BFSt2MdD9kk1jTdwqb2qwewa1xahHTSl1Y5hR%2BU2CCy3dIXpczS68G3yb0tnt8Bhe91zBzoAv%2F05reU9JoZBMJcvQsEdmLPV6vRUwmoF4cMxPoBCzRBw%2BZZ7OPzwTvAWTeQiGAUAT40EkV%2BONXTwvWU8vrSsiBW3QMKR9z8HsfWPz6CFDjHcagZQ8wngF6x3htLuFLKc3z0BfIULHcDC%2BtOMEXnB2yVCM8abAew6jeVjLNNtIvAKJELFkQ3XTWAtNI7UUz2F7CpuXkRXENrq50AHnTQicbBtXCnGJzbdSKWBDboAB5GNpXfbKXqi%2FAQ3a%2BRB65wY5BmQjFIJDjOfNGWGQvsapfh351WzWiWgN76qD2W2xMt1XwOH9yly4GfIigxelWFSGYKO3oKXpniDWJqfred9xLpmBVktdauvKOLTWmKfgucPocZ8t8dqDDbdI%2B4NZYNgD2VAh7DKfm3oEx4pN0EhLXb8VdcyG8LaSNw1NwV7EbAGH33b2tDtKO3kvoJKeXIVmd%2FG1ElMe8lIWa5Fjp5cUKk4uUFUmFtTsQjMRiNxLTj8eJoB2PWvLmPncXe%2FXDnWMDAfhKc9OOjk6F2gFZMHi%2Be44%2BY%2Fexj5IeXwhGY05FXjoV54xHnRx%2FSelnbZyRvLArGF9SAtB4EZgMUSXkat1FZYh%2BK2mK%2Fs6y6Q6K0Ht5vbleQeN6YXPl%2FfFJfpvJH4h2BKpXBQRwG9QhKI0WR8lhDcWjw0Bxsna%2BzDd%2FmfZv4KLrNW3daCklF2P0veSKg9XRTYnvfHfqfCnlqQW6I7kqE9eIHbHeodajpJRgwoN1frik6yleuyzs0I08smhdYrkDGMtow2X9HJvChInSEtJ9617glSZpZ3TDJqqbnftpTqXtCg7JhsmEix1nuxkP0MzRTg6CR2JyCrHB7wNGCT9VKJqLtj0Ol7IYDCgJupiQDIBBHWAvSrRcL%2FXK%2BnBAlZA7kW3R4PfjPtpCkAVraPPSsM8WA8J7s5bPr6e015nhzquscaPy8DkD0fCkuM6mj7BwmaDt%2F8Jx6RS0fO8R2Dm9lffSfiEN31TLX7TYYTKFyKD%2Fsru6rTJRoxIbRtSzxc71mPwJTi%2F9OV%2B%2Bl8IkllEbi27sUXl%2BN1vnPdwpQN2gsN9banbRZCTCQnmB9zSH%2BaiZVFxZT4kTgdZWCP2eqCl%2Fqw7eL0n5gMpTCjjNiwNPyD4hV0FzZPVG5BdcuJgfllGRjyC0mXLyTAGWkxfl1AHeB42LUKJuO6spPy8mw4CTDwjVXtSSs7kzb9bU%2B9pPKh3%2BBMAXT7K6%2F8PQCI%2B6iHmkoq3N%2BGDh46SPqaUIIykRQ7gvYrrKlOXUQZXf1RgMqHSSKW6rE12UctDzRvCc0JkdJ1MAjCU43kPlG0YXH2QPGW1ewDGsKcy147wsJ4U%2BZl7JIom3Fop0KE0ASMvOkOc4i9bA0GnzymirCgdLLeNAeLpG%2BXyhPeSJAxcL3rYztGaJAnaP12W9ed8GNOnjMlussg7%2B7GYAKmna8tiqNSe%2F9Jbe52BICNwJbC8MYsjSCQ0qaTMyhVgJdN2kEzPt6WUTkgtJoefxKg%3D%3D&csid=c310ad29-61bb-41a9-8a6f-ef49c702fa7d';


        $url2 = 'm5.amap.com/ws/mapapi/poi/newbus/?ent=2&in=%2FV1Ruzqqnu%2BLCNlfDiNeK8HcNvIBwD7mkDrXO5l8JcuHWhoImCBJA3YYTVrp1AIMMDXto3825k9hoEX4MwaNFSziHJ6u%2BpLUY6UkoSci1qz5w8zvnOXfGyevNTF6xh0x9U1b%2BLkP%2BBLPBiCQGiBOnwnq7pe65O1yLvdfgPQw%2F%2FMkxyZGFbjr8r9KkVpJigUV9BJs2YFIk46w93m82lLd1mWzV85YCk1alw9Xe7Pb8LLFBLO1zkufRKGgIgLazyEhbViIR3N085QqjtOL8elyPzMUZcDbxbNUzzAbyrLlorw417%2Fu2OMN95J6MxxZgxySS7wT1468Dol17EcqK4%2BKqkm7Oy5DyF%2F%2FD%2FmKmVZ0%2FivB%2BuWw00IlV%2FOnSo2a7p0cPYGAStNFxEcBaUui8%2FLJSsovYU%2FDb%2Bm6b69i86TD0HjggNWtjMOG3Is2njRmB5kjefsqulFSljX0sJAZxZegKEhoiGzE1f%2FFSGU8pDnlGa8zzVzmS2gawhob9lmMfKiGbUXy6LkTwQgyV3Tg1TJxZtU9xlvd05rZU1A1T4Om5ANQr0sEp103ZWDP88HqEITTJN5wZ2pUQs0WIScYVskVpwRy0%2BWWveFNasoosUPZML%2F8SVPPU%2Fl8o9FxXmDxMSHEG1GBoFjhgW6PUUr0sc%2Ft0g8cL7pimPvdsWHM2G4XSSKqar%2FL6Li14WnpnMXUmRqMywQzNjEUbNFLi3Hv8kWn6mE17H7NUmEMJRSPU%2F59Xx6I%2F9NjzyBkBuQRn8RUjWj1jhL4dt1MSoluJZ9vGlYvtEgysE8%2BOloapTl3KPzQiiWT4wf1fns1lqak1V2a3g9EjYODc53cIW1sLtH%2FyXcfTLnQVYRB7Qxicy5gT8jC8oAEJBiZxJxAqA%3D%3D&csid=6c381592-98db-434b-9f05-d51170766e99';
    }
}