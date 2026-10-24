<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：通用路由注解 #[Route('GET', '/path')]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route extends RouteAttribute
{
    public function __construct(
        string $method = 'ANY',
        string $uri = '',
        array|string $middleware = [],
        array $where = [],
        ?string $name = null
    ) {
        parent::__construct(strtoupper($method), $uri, $middleware, $where, $name);
    }
}
