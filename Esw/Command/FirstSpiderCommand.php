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
use Swlib\Http\Exception\ClientException;
use Swlib\Http\Exception\ConnectException;
use Swlib\Http\Exception\HttpExceptionMask;
use Swlib\Saber\Response;
use Swoole\Coroutine\Channel;
use Swlib\SaberGM;
use Swlib\Http\Exception\RequestException;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;

/**
 * easyswoole + saber 的第一只小蜘蛛
 * 搞一些普（se）通（qing）的数据
 * Class FirstSpiderCommand
 * @package Esw\Command
 */
class FirstSpiderCommand implements CommandInterface
{
    private $baseUrl = 'http://www.ckjdh.pw';
    /** @var Channel */
    private $dataChan; // 入库数据
    /** @var Channel */
    private $rawChan; // 原始数据 需要解析
    /** @var Channel */
    private $jobChan; // 原始数据 需要解析

    private $start_time;
    private $end_time;
    private $end = false;

    private $chanLogTick;

    const TABLE = 'spiders_copy2';


    public function commandName(): string
    {
        return 'spider_1';
    }

    public function exec(array $args): ?string
    {
        // TODO: 多协程使用saber并发发送请求，未清除之前的请求问题
        // TODO: 数据库问题。1、多个类型数据；2、重试机制导致异常重复问题
        // TODO: 目前抓取 目标数据74982条，实际入库76864（包含重复异常，缺失异常数据），有效数据74668
        // TODO: 结束问题。1、需要等chan为空再close；2、实际未退出
        // TODO:
        $this->start();
        // 注册执行请求
        go([$this, 'dispatchJobs']);
        go([$this, 'dispatchJobs']);
        go([$this, 'dispatchJobs']);
        // 注册数据解析
        go([$this, 'registerParseData']);
        // 注册入库
        go([$this, 'registerInsertOrUpdate']);
        // 启动
        go([$this, 'main1']);

        return null;
    }

    public function help(array $args): ?string
    {
        return 'easyswoole + saber 的第一只小蜘蛛，采用单进程多协程，搞一些普（se）通（qi）的数据';
    }

    private function main()
    {
        for ($i=700515;$i<=775497;$i+=10) {
            // 这里如果用多协程的话  saber 并发请求里会一起返回  所以不能这样做
            $this->runRequest($i);
        }

        $this->end();
    }

    private function main1()
    {
        for ($i=700515;$i<=775497;$i+=10) {
            // 这里如果用多协程的话  saber 并发请求里会一起返回  所以不能这样做
//            $this->runRequest($i);
            /** @see dispatchJobs()*/
            $this->jobChan->push($i);
        }

        $this->end();
    }

    /**
     * 注册任务分发
     */
    private function runRequest($i)
    {
        foreach (range($i, $i+9) as $item) {
            $urls[] = [
                'uri' => $this->baseUrl . $page = "/ckj1/{$item}.html",
            ];
        }


        try {
            $responses = SaberGM::requests($urls, ['timeout' => 5, 'retry_time' => 3]);
        }catch(ConnectException $e){
//            stdout($i);
//            stdout('connect ' . $page);
//            die;
        }catch(RequestException $e){
//            print_r($e);
//            stdout($i);
//            stdout('timeout ' . $page);
//            die;
//            return;
        }catch(\Exception $e){
//            stdout($i);
//            stdout('new error' . json_encode($e, JSON_UNESCAPED_UNICODE));
//            die;
//            return;
        }
//        if($responses->error_num > 0 ){

//            die;
//        }
        $result =  "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" ."consuming-time: {$responses->time}s";
        stdout($result);

        stdout(count($responses));

        foreach ($responses as $response) {
            /** @see registerParseData()*/
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
            continue;
            if (empty($data) || !is_array($data)) {
                continue;
            }

            // 如果是多维数组 则当批量插入
            $db = MysqlPool::defer(MYSQL_POOL);
            try {
                if (count($data) == count($data, 1)) {
                    $db->insert(self::TABLE, $data);
                } else {
                    $db->insertMulti(self::TABLE, $data);
                }
            }catch(\Throwable $e){
                Logger::getInstance()->error(catch_exception($e), 'MYSQL');
            }

        }
    }

    /**
     * 注册任务分发
     */
    private function dispatchJobs()
    {
        while (true) {
            if ($this->isEnd()) {
                break;
            }

            $i = $this->jobChan->pop();
            $this->runRequest($i);
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

            if($data){
                /** @see registerInsertOrUpdate()*/
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
            if($e instanceof RequestException){
                $excep_data = [
                    'title' => '',
                    'url' => '',
                    'key' => $e->getRequest()->getUri()->getPath(),
                    'form_data' => json_encode(catch_exception($e), JSON_UNESCAPED_UNICODE),
                    'add_time' => time(),
                ];
                /** @see registerInsertOrUpdate*/
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
        $this->rawChan  = new Channel(5);
        $this->jobChan  = new Channel(20);

        // 加个定时器 看下chan使用情况
        $this->chanLogTick = \Swoole\Timer::tick(5000, function(){
            Logger::getInstance()->notice(print_r([
                'dataChan' => $this->dataChan->stats(),
                'rawChan' => $this->rawChan->stats(),
                'jobChan' => $this->jobChan->stats(),
            ], true));
        });

    }

    private function end()
    {
        // todo: 清空各项任务  这个判断比较麻烦   调试阶段也不重要 直接先wait 20s
        $this->end = true;

        \Swoole\Coroutine::sleep(20);

        $this->dataChan->close();
        $this->rawChan->close();
        $this->jobChan->close();

        $this->end_time = microtime(true);
        stdout('用时'.($this->end_time - $this->start_time));

    }
}