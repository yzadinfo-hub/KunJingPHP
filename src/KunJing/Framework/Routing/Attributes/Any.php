<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：任意方法路由注解 #[Any('/path')]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Any extends RouteAttribute
{
    public function __construct(
        string $uri = '',
        array|string $middleware = [],
        array $where = [],
        ?string $name = null
    ) {
        parent::__construct('ANY', $uri, $middleware, $where, $name);
    }
}
