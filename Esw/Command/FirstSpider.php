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
use Swoole\Coroutine\Channel;
use Swlib\SaberGM;
use Swlib\Http\Exception\RequestException;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;

/**
 * easyswoole + saber 的第一只小蜘蛛
 * 搞一些普（se）通（qing）的数据
 * Class FirstSpider
 * @package Esw\Command
 */
class FirstSpider implements CommandInterface
{
    private $baseUrl = 'http://www.ckjdh.pw';
    /** @var Channel */
    private $dataChan; // 入库数据
    /** @var Channel */
    private $rawChan; // 原始数据 需要解析
    private $start_time;
    private $end_time;
    private $end = false;


    public function commandName(): string
    {
        return 'spider_1';
    }

    public function exec(array $args): ?string
    {
        Logger::getInstance()->error(111);
        Logger::getInstance()->waring(111);
        Logger::getInstance()->console(111);
        Logger::getInstance()->info(111);
        Logger::getInstance()->notice(111);
        return Config::getInstance()->getConf('TEMP_DIR');
        $this->start();
        // 注册分析
        go([$this, 'registerParseData']);
        // 注册入库
        go([$this, 'registerInsertOrUpdate']);
        // 启动
        go([$this, 'main']);

        return null;
    }

    public function help(array $args): ?string
    {
        return 'easyswoole + saber 的第一只小蜘蛛，搞一些普（se）通（qi）的数据';
    }

    private function main()
    {

//        $a = MysqlPool::defer('mysql')->rawQuery('show tables');
//        stdout($a);
//
//        MysqlPool::invoker('mysql', function ($db) {
//            $a = $db->rawQuery('show tables');
//            stdout($a);
//        });


        for ($i=700515;$i<=775497;$i+=10) {

//            $this->runSpidersHtml($i);
        }
        return;

        $urls = [];
        foreach (range($i, $i+9) as $item) {
            $urls[] = [
                'uri' => $this->baseUrl . $page = "/ckj1/{$item}.html",
            ];
        }

        try {
            $responses = SaberGM::requests($urls, ['timeout' => 5, 'retry_time' => 3]);
        }catch(RequestException $e){
            stdout('timeout ' . $page);
//            die;
            return;
        }catch(\Exception $e){
            stdout('new error' . json_encode($e, JSON_UNESCAPED_UNICODE));
//            die;
            return;
        }
        $result =  "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" ."consuming-time: {$responses->time}s";
        stdout($result);


        $this->end();



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
            if (!$data) {
                continue;
            }

            // TODO: 处理数据

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

            $data = $this->rawChan->pop();
            if (!$data) {
                continue;
            }

            continue;

            $data = [];
            foreach ($responses as $respons) {
                $url = $respons->getUri()->__toString();
                $body = $respons->getBody()->__toString();
                preg_match('/<span class="cat_pos_l">您的位置：<a href="\/">首页<\/a>&nbsp;&nbsp;&raquo;&nbsp;&nbsp;<a href=\'(.*)\' >(.*)<\/a>&nbsp;&nbsp;&raquo;&nbsp;&nbsp;(.*)<\/span>/', $body, $match);
                $data[] = [
                    'title' => $match[3] ?: '',
                    'url' => $url,
                    'key' => $match[2] ?: '',
                    'add_time' => time(),
                ];
            }

            if($data){
                $this->chan->push($data);
            }

            // TODO: 处理数据

        }
    }

    private function isEnd()
    {
        return $this->end;
    }

    private function start()
    {
        $this->start_time = microtime(true);
        // 设置结束状态为  没有结束
        $this->end = false;
        // 初始化 channel
        $this->dataChan = new Channel(5);
        $this->rawChan  = new Channel(5);

        // 加个定时器 看下chan使用情况

    }

    private function end()
    {
        $this->end = true;

        $this->dataChan->close();
        $this->rawChan->close();

        $this->end_time = microtime(true);
        stdout('用时'.$this->end_time - $this->start_time);

    }
}