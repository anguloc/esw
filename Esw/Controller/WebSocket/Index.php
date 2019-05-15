<?php

namespace Esw\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;


class Index extends Controller
{
    public function index()
    {
        $this->actionNotFound('index');
    }

}