<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：DELETE 路由注解 #[Delete('/path')]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Delete extends RouteAttribute
{
    public function __construct(
        string $uri = '',
        array|string $middleware = [],
        array $where = [],
        ?string $name = null
    ) {
        parent::__construct('DELETE', $uri, $middleware, $where, $name);
    }
}
