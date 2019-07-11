<?php

namespace Esw\Controller\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;


class Common extends Controller
{
    public function ping()
    {
        $result = create_return(SUCCESS_CODE, [], '', ['method' => __FUNCTION__]);
        $this->response()->setMessage($result);
    }

}