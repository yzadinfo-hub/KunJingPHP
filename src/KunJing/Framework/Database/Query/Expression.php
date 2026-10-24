<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：原生 SQL 表达式（净室实现）
 * 用于注入不受参数绑定的原生片段，如 count(*) 、 now() 等。
 */

namespace KunJing\Framework\Database\Query;

class Expression
{
    public function __construct(protected string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
