<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：单条路由定义（净室实现）
 */

namespace KunJing\Framework\Routing;

use KunJing\Framework\Http\Request;

class Route
{
    protected string $method;
    protected string $uri;
    protected $action;
    /** @var array<int, string> */
    protected array $middleware = [];
    protected ?string $namespace = null;
    /** @var array<string, string> */
    protected array $patterns = [];
    /** @var array<string, string> */
    protected array $params = [];
    protected ?string $name = null;

    /**
     * @param string|callable|array $action
     */
    public function __construct(string $method, string $uri, $action)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->action = $action;
    }

    /**
     * 追加中间件：支持类名字符串、数组或中间件实例。
     * @param string|object|array<int,string|object> $middleware
     */
    public function middleware($middleware): self
    {
        // 注意：不能对对象做 (array) 强转，否则会变成属性数组
        $list = is_array($middleware) ? array_values($middleware) : [$middleware];
        $this->middleware = array_merge($this->middleware, $list);
        return $this;
    }

    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /** @param array<string, string> $patterns */
    public function where(array $patterns): self
    {
        $this->patterns = array_merge($this->patterns, $patterns);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return array<string, string> */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * 序列化为数组（用于路由缓存）。闭包动作无法缓存，返回 null 表示不可缓存。
     * @return array<string,mixed>|null
     */
    public function toArray(): ?array
    {
        if ($this->action instanceof \Closure) {
            return null;
        }
        foreach ($this->middleware as $mw) {
            if (!is_string($mw)) {
                return null; // 中间件实例不可缓存
            }
        }
        return [
            'method'     => $this->method,
            'uri'        => $this->uri,
            'action'     => $this->action,
            'middleware' => $this->middleware,
            'namespace'  => $this->namespace,
            'patterns'   => $this->patterns,
            'name'       => $this->name,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $route = new self((string) ($data['method'] ?? 'GET'), (string) ($data['uri'] ?? '/'), $data['action'] ?? '');
        if (!empty($data['middleware'])) {
            $route->middleware($data['middleware']);
        }
        if (!empty($data['namespace'])) {
            $route->namespace((string) $data['namespace']);
        }
        if (!empty($data['patterns'])) {
            $route->where((array) $data['patterns']);
        }
        if (!empty($data['name'])) {
            $route->name((string) $data['name']);
        }
        return $route;
    }

    /** @return array<int, string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getAction()
    {
        return $this->action;
    }

    /** @return array<string, string> */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * 判断请求是否匹配本路由，匹配成功时将参数写入 Request。
     */
    public function matches(string $method, string $path, Request $request): bool
    {
        if ($this->method !== 'ANY' && $this->method !== $method) {
            return false;
        }

        $uri = $this->uri ?: '/';
        if (!str_contains($uri, '{')) {
            return rtrim($uri, '/') === rtrim($path, '/');
        }

        $quoted = preg_quote($uri, '#');
        $pattern = preg_replace_callback('/\\\\\{(\w+)\\\\\}/', function ($m) {
            $name = $m[1];
            $regex = $this->patterns[$name] ?? '[^/]+';
            return '(?P<' . $name . '>' . $regex . ')';
        }, $quoted);

        if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
            $this->params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $this->params[$key] = $value;
                    $request->setParam($key, $value);
                }
            }
            return true;
        }

        return false;
    }
}
