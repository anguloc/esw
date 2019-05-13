<?php

namespace Esw\Http;

use EasySwoole\Http\AbstractInterface\Controller;


class Index extends Controller
{
    function index()
    {
//        $this->actionNotFound('index');
        $this->response()->write('ok');
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