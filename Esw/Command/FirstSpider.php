<?php
/**
 * Created by PhpStorm.
 * User: gk
 * Date: 2019/6/27
 * Time: 23:25
 */

namespace Esw\Command;

use EasySwoole\EasySwoole\Command\CommandInterface;

/**
 * easyswoole + saber 的第一只小蜘蛛
 * 搞一些普（se）通（qing）的数据
 * Class FirstSpider
 * @package Esw\Command
 */
class FirstSpider implements CommandInterface
{
    public function commandName(): string
    {
        return 'spider_1';
    }

    public function exec(array $args): ?string
    {
        return 'note';
    }

    public function help(array $args): ?string
    {
        return 'easyswoole + saber 的第一只小蜘蛛，搞一些普（se）通（qi）的数据';
    }
}