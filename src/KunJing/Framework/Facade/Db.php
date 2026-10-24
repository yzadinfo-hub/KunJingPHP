<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Db 门面（净室实现，能力对标 ThinkPHP facade\Db）
 * 静态调用转发到数据库管理器单例。
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Database\Manager;

class Db extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
