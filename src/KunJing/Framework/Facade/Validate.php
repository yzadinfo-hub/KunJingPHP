<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Validate 门面
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Validate\Validator;

class Validate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Validator::class;
    }
}
