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
use EasySwoole\Component\AtomicManager;
use EasySwoole\RedisPool\Redis as RedisPool;
use EasySwoole\Component\Timer;

/**
 * 测试一些玩法
 * Class TestCommand
 * @package Esw\Command
 */
class LoopLockDebugCommand implements CommandInterface
{

    private $granularity_list = array(
        1 * 60,
        3 * 60,
        5 * 60,
        15 * 60,
        30 * 60,
        1 * 3600,
        2 * 3600,
        4 * 3600,
        6 * 3600,
        12 * 3600,
        24 * 3600,
        7 * 24 * 3600,
    );

    const LOCK_LOOP_DEBUG_KEY = 'lock_loop_debug_';
    const PERIOD_KEY = 'loop_lock_bug_init';
    const RESET_PERIOD_INC_KEY = 'reset_period_inc_key';

    public function commandName(): string
    {
        return 'loop_lock_bug';
    }

    public function getPeriod()
    {
        $atomic = AtomicManager::getInstance()->get(self::PERIOD_KEY);

        $y = $atomic->get();
        if ($y > (count($this->granularity_list) - 1)) {
            $y = 0;
            $atomic->set(0);
        }
        $atomic->add(1);

        return isset($this->granularity_list[$y]) ? $this->granularity_list[$y] : $this->granularity_list[0];
    }

    public function exec(array $args): ?string
    {
        Timer::getInstance()->loop(1000, function () {
            $this->main();
        });

        return 'loop_lock_bug';
    }

    public function help(array $args): ?string
    {
        return '记录一个由loop任务和lock不当导致重复任务bug';
    }

    private function main()
    {
        $start_time = chr(27) . "[31m" . date('Y-m-d H:i:s') . chr(27) . "[0m";

        AtomicManager::getInstance()->add(self::PERIOD_KEY, 0);
        AtomicManager::getInstance()->add(self::RESET_PERIOD_INC_KEY, 0);

        $period = $this->getPeriod();

        $key_s = "loop_lock_bug_run_" . $period;
        $random = $this->lock($key_s, 2);
        if (empty($random)) {
            Logger::getInstance()->stdout("开始运行时间：" . $start_time . "/粒度id：" . $period . '/' . "获取锁失败：" . $key_s);
            return false;
        }

        // 模拟操作  耗时 2 秒
        \co::sleep(2);
        $info = false;

        if ($info) {
            // 获取到数据  入库操作 这里是复现bug 不需要这一步
        } else {
            //如果没有获取到数据  择下次还是获取本周期
            $this->resetPeriod();
        }

        Logger::getInstance()->stdout("开始运行时间：" . $start_time . "/粒度id：" . $period . '/end');


        $this->unlock($key_s, $random);
        return true;
    }

    private function resetPeriod()
    {
        $atomic_inc = AtomicManager::getInstance()->get(self::RESET_PERIOD_INC_KEY);
        $inc = $atomic_inc->get();
        if ($inc >= 2) {
            $atomic_inc->set(0);
            return;
        }
        $atomic_inc->add(1);

        $atomic = AtomicManager::getInstance()->get(self::PERIOD_KEY);
        $y = $atomic->get();

        if ($y <= 0) {
            $atomic->set((count($this->granularity_list) - 1));
        } else {
            $atomic->sub(1);
        }
    }

    private function lock($key, $ttl)
    {
        mt_srand();
        $random = mt_rand(1000, 9999);
        $redis = RedisPool::defer(REDIS_POOL);

        $ok = $redis->set($key, $random, array('nx', 'ex' => $ttl));

        return $ok ? $random : $ok;
    }

    private function unlock($key, $random)
    {
        RedisPool::invoker(REDIS_POOL, function (\EasySwoole\RedisPool\Connection $redis) use ($key, $random) {
            if ($redis->get($key) == $random) {
                $redis->del($key);
            }
        });
//        RedisPool::getInstance()->pool(REDIS_POOL)::defer();
//        $redis = RedisPool::defer(REDIS_POOL);
//        if ($redis->get($key) == $random) {
//            $redis->del($key);
//        }
    }

}