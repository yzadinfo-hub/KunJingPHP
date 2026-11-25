<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：HTTP 请求封装（净室实现，能力对标 ThinkPHP Request）
 */

namespace KunJing\Framework\Http;

class Request
{
    protected array $get;
    protected array $post;
    protected array $server;
    protected array $headers;
    protected ?string $rawBody;
    /** @var array<string, mixed> */
    protected array $params = [];
    /** @var array<string, mixed> */
    protected array $files = [];

    public function __construct(array $get = [], array $post = [], array $server = [], ?string $rawBody = null, array $files = [])
    {
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->rawBody = $rawBody;
        $this->files = $files;
        $this->headers = $this->parseHeaders($server);
    }

    /**
     * 从 PHP 全局变量捕获当前请求。
     */
    public static function capture(): self
    {
        $raw = null;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $post = $_POST;
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $raw = file_get_contents('php://input') ?: null;
            // JSON body 兼容：$_POST 在 application/json 下为空，需从 raw 解析合并
            $json = self::tryParseJsonBody($raw, $method);
            if (is_array($json)) {
                $post = array_merge($json, $post);
            }
        }
        return new self($_GET, $post, $_SERVER, $raw, $_FILES ?? []);
    }

    /**
     * 尝试把 POST body 解析为 JSON 数组（仅 application/json 或 body 以 {/[ 开头）。
     * @return array<string,mixed>|null
     */
    private static function tryParseJsonBody(?string $raw, string $method): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $trimmed = ltrim($raw);
        if ($trimmed === '') {
            return null;
        }
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        $isJson = (stripos($ct, 'application/json') !== false) || $trimmed[0] === '{' || $trimmed[0] === '[';
        if (!$isJson) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $files */
    public function setFiles(array $files): self
    {
        $this->files = $files;
        return $this;
    }

    /** @return array<string,mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    /**
     * 取上传文件：单个返回 UploadedFile，多文件返回数组，无则返回 null。
     * @return UploadedFile|array<int,UploadedFile>|null
     */
    public function file(string $key)
    {
        $info = $this->files[$key] ?? null;
        if ($info === null) {
            return null;
        }
        return UploadedFile::normalize($info);
    }

    /**
     * 从 SAPI 全局构造（用于测试）。
     */
    public static function fromGlobals(): self
    {
        return self::capture();
    }

    protected function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', ucwords(strtolower(substr($key, 5)), '_'));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', ucwords(strtolower($key), '_'));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($this->post['_method'])) {
            $method = strtoupper((string) $this->post['_method']);
        }
        return $method;
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        return $uri === '' ? '/' : $uri;
    }

    public function url(): string
    {
        $scheme = (($this->server['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->path();
    }

    public function input(string $key, $default = null)
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function header(string $key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    public function server(string $key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }

    public function setParam(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->params);
    }

    /** @param string[] $keys */
    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($this->header('X-Requested-With') ?? '')) === 'xmlhttprequest';
    }

    /** 期望 JSON 响应（AJAX 或 Accept 头含 json） */
    public function wantsJson(): bool
    {
        if ($this->isAjax()) {
            return true;
        }
        $accept = strtolower((string) ($this->header('Accept') ?? ''));
        return $accept !== '' && str_contains($accept, 'application/json');
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || strtolower((string) ($this->header('X-Forwarded-Proto') ?? '')) === 'https';
    }

    /**
     * 客户端 IP（兼容反向代理）。
     */
    public function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            $value = $this->server[$key] ?? null;
            if (!empty($value)) {
                $parts = explode(',', (string) $value);
                return trim($parts[0]);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return (string) ($this->header('User-Agent') ?? $this->server['HTTP_USER_AGENT'] ?? '');
    }

    /**
     * Bearer Token（Authorization: Bearer xxx）
     */
    public function bearerToken(): ?string
    {
        $header = (string) ($this->header('Authorization') ?? '');
        if ($header === '') {
            return null;
        }
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    public function fullUrl(): string
    {
        $query = $this->server['QUERY_STRING'] ?? '';
        return $this->url() . ($query !== '' ? '?' . $query : '');
    }

    /** 上一页地址（用于表单校验失败回跳） */
    public function referer(?string $default = null): ?string
    {
        return $this->header('Referer') ?? $default;
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    public function json(bool $assoc = true)
    {
        if ($this->rawBody === null) {
            return null;
        }
        return json_decode($this->rawBody, $assoc);
    }
}
