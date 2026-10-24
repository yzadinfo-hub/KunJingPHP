<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：校验异常（净室实现）
 */

namespace KunJing\Framework\Exception;

class ValidateException extends FrameworkException
{
    /** @var array<string, string> */
    protected array $errors = [];

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
