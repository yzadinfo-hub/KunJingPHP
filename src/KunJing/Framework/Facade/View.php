<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：View 门面
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\View\Engine;

class View extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Engine::class;
    }
}
