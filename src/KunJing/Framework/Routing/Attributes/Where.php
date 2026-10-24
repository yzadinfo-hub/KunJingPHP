<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：参数约束注解 #[Where(['id' => '[0-9]+'])]
 */

namespace KunJing\Framework\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Where
{
    /** @param array<string,string> $patterns */
    public function __construct(public array $patterns = [])
    {
    }
}
