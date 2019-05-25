<?php

namespace Esw\Controller\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use Esw\Tetris\Service\Player;
use Esw\Tetris\Service\Room;
use Esw\Tetris\Service\Stage;


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
        $room = new Room();
        // 确定人数
        $player_num = 1;
        // 创建场地
        $stage = new Stage();
//        $player = [];
        $player = new Player(0);

    }

    public function ping()
    {
        $result = createReturn(SUCCESS_CODE, [],'', ['method' => __FUNCTION__]);
        $this->response()->setMessage($result);
    }

}