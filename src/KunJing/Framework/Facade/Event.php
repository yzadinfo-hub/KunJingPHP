<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Event 门面
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Event\Dispatcher;

class Event extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Dispatcher::class;
    }
}
