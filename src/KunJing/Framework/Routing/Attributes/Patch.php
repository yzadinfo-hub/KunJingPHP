<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：PATCH 路由注解 #[Patch('/path')]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Patch extends RouteAttribute
{
    public function __construct(
        string $uri = '',
        array|string $middleware = [],
        array $where = [],
        ?string $name = null
    ) {
        parent::__construct('PATCH', $uri, $middleware, $where, $name);
    }
}
