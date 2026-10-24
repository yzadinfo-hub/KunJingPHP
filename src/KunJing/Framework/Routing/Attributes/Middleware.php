<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：中间件注解 #[Middleware(AuthMiddleware::class)]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /** @param array<int,string>|string $middleware */
    public function __construct(public array|string $middleware = [])
    {
    }

    /** @return array<int,string> */
    public function list(): array
    {
        return is_array($this->middleware) ? $this->middleware : [$this->middleware];
    }
}
