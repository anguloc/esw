<?php

namespace Esw\Controller\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;


class Tetris extends Controller
{
    public function index()
    {
        $data = $this->caller()->getArgs();
        print_r($data);
//        $this->actionNotFound('index');
    }

    public function start()
    {
        // 创建房间
        // 确定人数
        // 创建场地
    }

    public function ping()
    {
        $result = createReturn(SUCCESS_CODE, [],'', ['method' => __FUNCTION__]);
        $this->response()->setMessage($result);
    }

}