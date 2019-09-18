<?php


namespace Esw\Util;

use EasySwoole\Log\LoggerInterface;


/**
 * 复制官方的   受不了亮瞎眼的背景颜色
 * Class Logger
 * @package Esw\Util
 */
class Logger implements LoggerInterface
{
    private $logDir;

    function __construct(string $logDir = null)
    {
        if(empty($logDir)){
            $logDir = getcwd();
        }
        $this->logDir = $logDir;
    }

    function log(?string $msg,int $logLevel = self::LOG_LEVEL_INFO,string $category = 'DEBUG'):string
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $date = date('Y-m-d H:i:s');
        $levelStr = $this->levelMap($logLevel);
        $filePath = $this->logDir."/log.log";
        $str = "[{$date}][{$category}][{$levelStr}] : [{$msg}]\n";
        file_put_contents($filePath,"{$str}",FILE_APPEND|LOCK_EX);
        return $str;
    }

    function console(?string $msg,int $logLevel = self::LOG_LEVEL_INFO,string $category = 'DEBUG')
    {
        $date = date('Y-m-d H:i:s');
        $levelStr = $this->levelMap($logLevel);
        $temp =  $this->colorString("[{$date}][{$category}][{$levelStr}] : [{$msg}]",$logLevel)."\n";
        fwrite(STDOUT,$temp);
    }

    private function colorString(string $str,int $logLevel)
    {
        switch($logLevel) {
            case self::LOG_LEVEL_INFO:
                $out = "[32m";
                break;
            case self::LOG_LEVEL_NOTICE:
                $out = "[33m";
                break;
            case self::LOG_LEVEL_WARNING:
                $out = "[36m";
                break;
            case self::LOG_LEVEL_ERROR:
                $out = "[31m";
                break;
            default:
                $out = "[37m";
                break;
        }
        return chr(27) . "$out" . "{$str}" . chr(27) . "[0m";
    }

    private function levelMap(int $level)
    {
        switch ($level)
        {
            case self::LOG_LEVEL_INFO:
               return 'INFO';
            case self::LOG_LEVEL_NOTICE:
                return 'NOTICE';
            case self::LOG_LEVEL_WARNING:
                return 'WARNING';
            case self::LOG_LEVEL_ERROR:
                return 'ERROR';
            default:
                return 'UNKNOWN';
        }
    }
}