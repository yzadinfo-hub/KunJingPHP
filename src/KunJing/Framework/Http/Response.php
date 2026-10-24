<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：HTTP 响应（净室实现，能力对标 ThinkPHP Response）
 */

namespace KunJing\Framework\Http;

class Response
{
    protected mixed $content;
    protected int $status;
    /** @var array<string, string> */
    protected array $headers;
    protected string $type; // html | json | raw

    public function __construct(mixed $content = '', int $status = 200, array $headers = [], string $type = 'html')
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
        $this->type = $type;
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, $headers, 'html');
    }

    /**
     * @param mixed $data
     */
    public static function json($data, int $status = 200, array $headers = []): self
    {
        return new self($data, $status, $headers, 'json');
    }

    public static function make(mixed $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers, 'raw');
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url], 'raw');
    }

    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 发送响应到客户端。
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $key => $value) {
                header("$key: $value");
            }
            if ($this->type === 'json') {
                header('Content-Type: application/json; charset=utf-8');
            } elseif ($this->type === 'html') {
                header('Content-Type: text/html; charset=utf-8');
            }
        }

        if ($this->type === 'json') {
            echo json_encode($this->content, JSON_UNESCAPED_UNICODE);
        } else {
            echo is_string($this->content) ? $this->content : (string) $this->content;
        }
    }
}
