<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：POST 路由注解 #[Post('/path')]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Post extends RouteAttribute
{
    public function __construct(
        string $uri = '',
        array|string $middleware = [],
        array $where = [],
        ?string $name = null
    ) {
        parent::__construct('POST', $uri, $middleware, $where, $name);
    }
}
