<?php

namespace Esw\Controller\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;


class Index extends Controller
{
    public function index()
    {
        $data = $this->caller()->getArgs();
        print_r($data);
//        $this->actionNotFound('index');
    }

    public function start()
    {

    }

}