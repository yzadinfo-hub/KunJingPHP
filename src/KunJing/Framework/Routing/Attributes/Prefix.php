<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：控制器前缀注解 #[Prefix('/admin')]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Prefix
{
    public function __construct(public string $prefix = '')
    {
    }
}
