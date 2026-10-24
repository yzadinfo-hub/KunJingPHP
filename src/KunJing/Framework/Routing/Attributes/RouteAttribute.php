<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：路由注解基类（净室实现）
 *
 * 说明：本文件只定义注解的"数据结构"，不含任何第三方框架代码。
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

/**
 * 路由注解基类。子类（Get/Post/Put/Delete/Patch/Any/Route）只需固定 HTTP 方法。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RouteAttribute
{
    /**
     * @param string        $method     HTTP 方法，ANY 表示任意
     * @param string        $uri        路由地址，留空则使用"方法名"
     * @param array|string  $middleware 中间件类名或实例
     * @param array<string,string> $where 参数正则约束
     * @param string|null   $name       路由名，用于生成 URL
     */
    public function __construct(
        public string $method = 'ANY',
        public string $uri = '',
        public array|string $middleware = [],
        public array $where = [],
        public ?string $name = null
    ) {
    }

    /** @return array<int,string> */
    public function middlewareList(): array
    {
        return is_array($this->middleware) ? $this->middleware : [$this->middleware];
    }
}
