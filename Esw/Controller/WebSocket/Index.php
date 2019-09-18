<?php

namespace Esw\Controller\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Logger;


class Index extends Controller
{
    public function index()
    {
        $data = $this->caller()->getArgs();
        print_r($data);
        Logger::getInstance()->log(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $message = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->response()->setMessage("WebSocket Response: Request data {$message}");
//        $this->actionNotFound('index');
    }

    public function test()
    {
        $data = $this->caller()->getArgs();
        print_r($data);
        Logger::getInstance()->log(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $message = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->response()->setMessage("WebSocket Response: Request data {$message}");

    }

}