<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：HTTP 异常（携带状态码，净室实现）
 */

namespace KunJing\Framework\Exception;

class HttpException extends FrameworkException
{
    protected int $statusCode;

    public function __construct(int $statusCode, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
