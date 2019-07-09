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
 * 测试一些玩法
 * Class TestCommand
 * @package Esw\Command
 */
class TestCommand implements CommandInterface
{
    private $baseUrl = 'http://www.httpbin.org';
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

    const A = [[
        'uri' => $this->baseUrl . self::GET,
    ],
    [
        'uri' => $this->baseUrl . self::GET,
    ],
    [
        'uri' => $this->baseUrl . self::GET,
    ],
    [
        'uri' => $this->baseUrl . self::GET,
    ],
    [
        'uri' => $this->baseUrl . self::GET,
    ]];


    public function commandName(): string
    {
        return 'test';
    }

    public function exec(array $args): ?string
    {
        $url1 = 'm5.amap.com/ws/mapapi/poi/infolite/?ent=2&in=FbZ6o6HqqPaMeUHcGq5hCUd%2FwmjB9cXxFFaFG0DhuduZK3mxihz0B43OO95Mkyk7anxajEB74ECYTPsUfJTNSKx%2FKHiCRsJ8zGlGuTyt8%2FT9IGI%2F%2FowkW4mS0ek6%2FwjM9Cv7DJAXyEovxsi28EW78gVbhWeFUuV7Y3b0IZ5RpvXQ%2B8%2BFSt2MdD9kk1jTdwqb2qwewa1xahHTSl1Y5hR%2BU2CCy3dIXpczS68G3yb0tnt8Bhe91zBzoAv%2F05reU9JoZBMJcvQsEdmLPV6vRUwmoF4cMxPoBCzRBw%2BZZ7OPzwTvAWTeQiGAUAT40EkV%2BONXTwvWU8vrSsiBW3QMKR9z8HsfWPz6CFDjHcagZQ8wngF6x3htLuFLKc3z0BfIULHcDC%2BtOMEXnB2yVCM8abAew6jeVjLNNtIvAKJELFkQ3XTWAtNI7UUz2F7CpuXkRXENrq50AHnTQicbBtXCnGJzbdSKWBDboAB5GNpXfbKXqi%2FAQ3a%2BRB65wY5BmQjFIJDjOfNGWGQvsapfh351WzWiWgN76qD2W2xMt1XwOH9yly4GfIigxelWFSGYKO3oKXpniDWJqfred9xLpmBVktdauvKOLTWmKfgucPocZ8t8dqDDbdI%2B4NZYNgD2VAh7DKfm3oEx4pN0EhLXb8VdcyG8LaSNw1NwV7EbAGH33b2tDtKO3kvoJKeXIVmd%2FG1ElMe8lIWa5Fjp5cUKk4uUFUmFtTsQjMRiNxLTj8eJoB2PWvLmPncXe%2FXDnWMDAfhKc9OOjk6F2gFZMHi%2Be44%2BY%2Fexj5IeXwhGY05FXjoV54xHnRx%2FSelnbZyRvLArGF9SAtB4EZgMUSXkat1FZYh%2BK2mK%2Fs6y6Q6K0Ht5vbleQeN6YXPl%2FfFJfpvJH4h2BKpXBQRwG9QhKI0WR8lhDcWjw0Bxsna%2BzDd%2FmfZv4KLrNW3daCklF2P0veSKg9XRTYnvfHfqfCnlqQW6I7kqE9eIHbHeodajpJRgwoN1frik6yleuyzs0I08smhdYrkDGMtow2X9HJvChInSEtJ9617glSZpZ3TDJqqbnftpTqXtCg7JhsmEix1nuxkP0MzRTg6CR2JyCrHB7wNGCT9VKJqLtj0Ol7IYDCgJupiQDIBBHWAvSrRcL%2FXK%2BnBAlZA7kW3R4PfjPtpCkAVraPPSsM8WA8J7s5bPr6e015nhzquscaPy8DkD0fCkuM6mj7BwmaDt%2F8Jx6RS0fO8R2Dm9lffSfiEN31TLX7TYYTKFyKD%2Fsru6rTJRoxIbRtSzxc71mPwJTi%2F9OV%2B%2Bl8IkllEbi27sUXl%2BN1vnPdwpQN2gsN9banbRZCTCQnmB9zSH%2BaiZVFxZT4kTgdZWCP2eqCl%2Fqw7eL0n5gMpTCjjNiwNPyD4hV0FzZPVG5BdcuJgfllGRjyC0mXLyTAGWkxfl1AHeB42LUKJuO6spPy8mw4CTDwjVXtSSs7kzb9bU%2B9pPKh3%2BBMAXT7K6%2F8PQCI%2B6iHmkoq3N%2BGDh46SPqaUIIykRQ7gvYrrKlOXUQZXf1RgMqHSSKW6rE12UctDzRvCc0JkdJ1MAjCU43kPlG0YXH2QPGW1ewDGsKcy147wsJ4U%2BZl7JIom3Fop0KE0ASMvOkOc4i9bA0GnzymirCgdLLeNAeLpG%2BXyhPeSJAxcL3rYztGaJAnaP12W9ed8GNOnjMlussg7%2B7GYAKmna8tiqNSe%2F9Jbe52BICNwJbC8MYsjSCQ0qaTMyhVgJdN2kEzPt6WUTkgtJoefxKg%3D%3D&csid=c310ad29-61bb-41a9-8a6f-ef49c702fa7d';


        $url2 = 'm5.amap.com/ws/mapapi/poi/newbus/?ent=2&in=%2FV1Ruzqqnu%2BLCNlfDiNeK8HcNvIBwD7mkDrXO5l8JcuHWhoImCBJA3YYTVrp1AIMMDXto3825k9hoEX4MwaNFSziHJ6u%2BpLUY6UkoSci1qz5w8zvnOXfGyevNTF6xh0x9U1b%2BLkP%2BBLPBiCQGiBOnwnq7pe65O1yLvdfgPQw%2F%2FMkxyZGFbjr8r9KkVpJigUV9BJs2YFIk46w93m82lLd1mWzV85YCk1alw9Xe7Pb8LLFBLO1zkufRKGgIgLazyEhbViIR3N085QqjtOL8elyPzMUZcDbxbNUzzAbyrLlorw417%2Fu2OMN95J6MxxZgxySS7wT1468Dol17EcqK4%2BKqkm7Oy5DyF%2F%2FD%2FmKmVZ0%2FivB%2BuWw00IlV%2FOnSo2a7p0cPYGAStNFxEcBaUui8%2FLJSsovYU%2FDb%2Bm6b69i86TD0HjggNWtjMOG3Is2njRmB5kjefsqulFSljX0sJAZxZegKEhoiGzE1f%2FFSGU8pDnlGa8zzVzmS2gawhob9lmMfKiGbUXy6LkTwQgyV3Tg1TJxZtU9xlvd05rZU1A1T4Om5ANQr0sEp103ZWDP88HqEITTJN5wZ2pUQs0WIScYVskVpwRy0%2BWWveFNasoosUPZML%2F8SVPPU%2Fl8o9FxXmDxMSHEG1GBoFjhgW6PUUr0sc%2Ft0g8cL7pimPvdsWHM2G4XSSKqar%2FL6Li14WnpnMXUmRqMywQzNjEUbNFLi3Hv8kWn6mE17H7NUmEMJRSPU%2F59Xx6I%2F9NjzyBkBuQRn8RUjWj1jhL4dt1MSoluJZ9vGlYvtEgysE8%2BOloapTl3KPzQiiWT4wf1fns1lqak1V2a3g9EjYODc53cIW1sLtH%2FyXcfTLnQVYRB7Qxicy5gT8jC8oAEJBiZxJxAqA%3D%3D&csid=6c381592-98db-434b-9f05-d51170766e99';



        // Logger::getInstance()->console('asd');
        $this->chan = new Channel(2);
        go(function(){


            for($i=1;$i<2;$i++){
                $this->chan->push();
            }


            

            go(function()use($chan){
                $chan->pop();
                $urls = [
                    
                ];
                $responses = SaberGM::requests($urls);
                $result =  "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" ."consuming-time: {$responses->time}s\n";
                fwrite(STDOUT, $result);
            });
            go(function()use($chan){
                $chan->pop();
                $urls = [
                    [
                        'uri' => $this->baseUrl . self::GET,
                    ],
                    [
                        'uri' => $this->baseUrl . self::GET,
                    ],
                    [
                        'uri' => $this->baseUrl . self::GET,
                    ],
                    [
                        'uri' => $this->baseUrl . self::GET,
                    ],
                    [
                        'uri' => $this->baseUrl . self::GET,
                    ],
                ];
                $responses = SaberGM::requests($urls);
                $result =  "multi-requests [ {$responses->success_num} ok, {$responses->error_num} error ]:" ."consuming-time: {$responses->time}s\n";
                fwrite(STDOUT, $result);
            });



            $chan->push(1);
            $chan->push(2);
        });





        // Logger::getInstance()->error(123);
        return 'test';
        $this->start();
//        // 注册数据解析
        go([$this, 'registerParseData']);
//        // 注册入库
        go([$this, 'registerInsertOrUpdate']);
        // 启动
        go([$this, 'main']);

        return null;
    }

    public function help(array $args): ?string
    {
        return '测试一些玩法';
    }

    private function main()
    {
        for ($i=700515;$i<=775497;$i+=10) {
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
            }catch(\Throwable $e){
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
        stdout('用时'.($this->end_time - $this->start_time));

    }
}