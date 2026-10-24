<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Cache 门面
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Cache\CacheManager;

class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CacheManager::class;
    }
}
