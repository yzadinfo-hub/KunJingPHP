<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Log 门面
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Log\Logger;

class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Logger::class;
    }
}
