<?php

namespace Esw\Controller\Http;

use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Http\Message\Status;


class Index extends Controller
{
    public function index()
    {
//        $this->actionNotFound('index');
        
        $this->response()->withStatus(Status::CODE_NOT_FOUND);
        $this->response()->write('not exist http site');
        $this->response()->end();
    }

    protected function onRequest(?string $action): ?bool
    {
        return true;
    }

    protected function onException(\Throwable $throwable): void
    {
        //拦截错误进日志,使控制器继续运行
        Trigger::getInstance()->throwable($throwable);
        $this->writeJson(Status::CODE_INTERNAL_SERVER_ERROR, null, $throwable->getMessage());
    }

}