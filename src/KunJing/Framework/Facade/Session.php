<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Session 门面
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Session\SessionManager;

class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SessionManager::class;
    }
}
