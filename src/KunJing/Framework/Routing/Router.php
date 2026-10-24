<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：路由器（净室实现）
 *
 * 支持：配置注册 + 分组 + 参数路由 + 注解注册 + 资源路由 + 命名路由 + 路由缓存
 */

namespace KunJing\Framework\Routing;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Routing\Attributes\Middleware as MiddlewareAttribute;
use KunJing\Framework\Routing\Attributes\Prefix;
use KunJing\Framework\Routing\Attributes\RouteAttribute;
use KunJing\Framework\Routing\Attributes\Where as WhereAttribute;

class Router
{
    /** @var array<int, Route> */
    protected array $routes = [];
    /** @var array<string, mixed> */
    protected array $groupAttributes = [];
    /** @var array<int, array<string, mixed>> */
    protected array $groupStack = [];
    /** @var array<string, Route> */
    protected array $namedRoutes = [];
    protected ?string $cacheFile = null;

    /* ---------- 基础注册 ---------- */

    public function get(string $uri, $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function options(string $uri, $action): Route
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    public function any(string $uri, $action): Route
    {
        return $this->addRoute('ANY', $uri, $action);
    }

    /** @param array<int,string> $methods */
    public function match(array $methods, string $uri, $action): array
    {
        $routes = [];
        foreach ($methods as $method) {
            $routes[] = $this->addRoute(strtoupper($method), $uri, $action);
        }
        return $routes;
    }

    /**
     * RESTful 资源路由：index/create/store/show/edit/update/destroy
     */
    public function resource(string $uri, string $controller, ?string $namePrefix = null): array
    {
        $uri = '/' . trim($uri, '/');
        $namePrefix = $namePrefix ?? trim($uri, '/');
        $map = [
            ['GET', $uri, 'index', $namePrefix . '.index'],
            ['GET', $uri . '/create', 'create', $namePrefix . '.create'],
            ['POST', $uri, 'store', $namePrefix . '.store'],
            ['GET', $uri . '/{id}', 'show', $namePrefix . '.show'],
            ['GET', $uri . '/{id}/edit', 'edit', $namePrefix . '.edit'],
            ['PUT', $uri . '/{id}', 'update', $namePrefix . '.update'],
            ['DELETE', $uri . '/{id}', 'destroy', $namePrefix . '.destroy'],
        ];
        $routes = [];
        foreach ($map as [$method, $path, $action, $name]) {
            $routes[] = $this->addRoute($method, $path, [$controller, $action])->name($name)->where(['id' => '[0-9]+']);
        }
        return $routes;
    }

    /**
     * 路由分组：支持 prefix / middleware / namespace。
     * @param array<string, mixed> $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $this->groupAttributes;
        $this->groupAttributes = array_merge($this->groupAttributes, $attributes);
        $callback($this);
        $this->groupAttributes = array_pop($this->groupStack);
    }

    /**
     * @param string|callable|array $action
     */
    protected function addRoute(string $method, string $uri, $action): Route
    {
        $attrs = $this->groupAttributes;
        $prefix = $attrs['prefix'] ?? '';
        $uri = '/' . ltrim($prefix . '/' . ltrim($uri, '/'), '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }
        if ($uri === '') {
            $uri = '/';
        }

        $route = new Route($method, $uri, $action);
        if (isset($attrs['middleware'])) {
            $route->middleware($attrs['middleware']);
        }
        if (isset($attrs['namespace'])) {
            $route->namespace($attrs['namespace']);
        }

        $this->routes[] = $route;
        return $route;
    }

    /**
     * 注册命名路由索引（在 name() 之后由本方法维护）。
     */
    public function registerName(Route $route): void
    {
        $name = $route->getName();
        if ($name !== null && $name !== '') {
            $this->namedRoutes[$name] = $route;
        }
    }

    /** @return array<int, Route> */
    public function getRoutes(): array
    {
        $this->rebuildNameIndex();
        return $this->routes;
    }

    public function getByName(string $name): ?Route
    {
        $this->rebuildNameIndex();
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * 按名称生成 URL。
     * @param array<string,string|int> $params
     */
    public function url(string $name, array $params = [], bool $absolute = false): string
    {
        $route = $this->getByName($name);
        if ($route === null) {
            return '';
        }
        $uri = $route->getUri();
        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }
        // 剩余未替换的可选参数清理
        $uri = preg_replace('/\{(\w+)\}/', '', $uri) ?? $uri;
        return $absolute ? $this->baseUrl() . $uri : $uri;
    }

    protected function baseUrl(): string
    {
        $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    protected function rebuildNameIndex(): void
    {
        $this->namedRoutes = [];
        foreach ($this->routes as $route) {
            $name = $route->getName();
            if ($name !== null && $name !== '') {
                $this->namedRoutes[$name] = $route;
            }
        }
    }

    /* ---------- 注解注册 ---------- */

    /**
     * 通过 PHP 8 注解注册控制器路由。
     * @param array<int,string> $classes 控制器类名列表
     */
    public function attributes(array $classes): void
    {
        foreach ($classes as $class) {
            $this->registerController($class);
        }
    }

    /**
     * 扫描目录下的控制器类并注册注解路由。
     */
    public function attributeDirectory(string $dir, string $baseNamespace): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen(rtrim($dir, '/\\')) + 1);
            $class = rtrim($baseNamespace, '\\') . '\\' . str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relative);
            if (class_exists($class)) {
                $this->registerController($class);
            }
        }
    }

    /**
     * @param string $class 控制器类名
     */
    public function registerController(string $class): void
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("注解路由注册的控制器类 [{$class}] 不存在。");
        }
        $ref = new \ReflectionClass($class);

        $prefix = '';
        $classMiddleware = [];
        $classWhere = [];

        foreach ($ref->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if ($instance instanceof Prefix) {
                $prefix = $instance->prefix;
            } elseif ($instance instanceof MiddlewareAttribute) {
                $classMiddleware = array_merge($classMiddleware, $instance->list());
            } elseif ($instance instanceof WhereAttribute) {
                $classWhere = array_merge($classWhere, $instance->patterns);
            } elseif ($instance instanceof RouteAttribute) {
                if ($instance->uri !== '') {
                    $prefix = $instance->uri;
                }
                $classMiddleware = array_merge($classMiddleware, $instance->middlewareList());
                $classWhere = array_merge($classWhere, $instance->where);
            }
        }

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract() || $method->isConstructor() || $method->isDestructor()) {
                continue;
            }
            // 先收集该方法上的全部注解，再统一注册（避免声明顺序影响 Where/Middleware 生效）
            $routeAttributes = [];
            $methodMiddleware = [];
            $methodWhere = [];

            foreach ($method->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                if ($instance instanceof MiddlewareAttribute) {
                    $methodMiddleware = array_merge($methodMiddleware, $instance->list());
                } elseif ($instance instanceof WhereAttribute) {
                    $methodWhere = array_merge($methodWhere, $instance->patterns);
                } elseif ($instance instanceof RouteAttribute) {
                    $routeAttributes[] = $instance;
                }
            }

            foreach ($routeAttributes as $instance) {
                $uri = $instance->uri !== '' ? $instance->uri : $this->methodToUri($method->getName());
                $uri = '/' . ltrim(rtrim($prefix, '/') . '/' . ltrim($uri, '/'), '/');
                if ($uri !== '/' && strlen($uri) > 1) {
                    $uri = rtrim($uri, '/');
                }

                $route = $this->addRoute($instance->method, $uri, [$class, $method->getName()]);
                $middleware = array_merge($classMiddleware, $methodMiddleware, $instance->middlewareList());
                if ($middleware !== []) {
                    $route->middleware($middleware);
                }
                $where = array_merge($classWhere, $methodWhere, $instance->where);
                if ($where !== []) {
                    $route->where($where);
                }
                if ($instance->name !== null && $instance->name !== '') {
                    $route->name($instance->name);
                }
            }
        }
    }

    /**
     * 方法名 → 默认 URI：index→/，userList→/user-list
     */
    protected function methodToUri(string $method): string
    {
        if ($method === 'index') {
            return '/';
        }
        $kebab = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $method));
        return '/' . $kebab;
    }

    /* ---------- 路由缓存 ---------- */

    public function cacheFile(?string $path): self
    {
        $this->cacheFile = $path;
        return $this;
    }

    public function getCacheFile(): ?string
    {
        return $this->cacheFile;
    }

    /**
     * 引导路由：优先从缓存加载，否则执行注册回调并写缓存。
     */
    public function boot(callable $register, bool $refresh = false): void
    {
        if ($this->cacheFile === null) {
            $register($this);
            return;
        }
        if (!$refresh && $this->loadFromCache()) {
            return;
        }
        $register($this);
        $this->dumpCache();
    }

    /**
     * 从缓存加载路由。
     */
    public function loadFromCache(): bool
    {
        if ($this->cacheFile === null || !is_file($this->cacheFile)) {
            return false;
        }
        $data = require $this->cacheFile;
        if (!is_array($data) || !isset($data['routes']) || !is_array($data['routes'])) {
            return false;
        }
        $this->routes = [];
        foreach ($data['routes'] as $row) {
            $this->routes[] = Route::fromArray($row);
        }
        return $this->routes !== [];
    }

    /**
     * 写入路由缓存。闭包动作与中间件实例无法缓存，会被跳过。
     */
    public function dumpCache(): bool
    {
        if ($this->cacheFile === null) {
            return false;
        }
        $rows = [];
        foreach ($this->routes as $route) {
            $row = $route->toArray();
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $export = $this->exportArray($rows);
        $content = "<?php\n/** 堃景PHP 路由缓存，生成时间：" . date('Y-m-d H:i:s') . " */\nreturn " . $export . ";\n";
        return file_put_contents($this->cacheFile, $content) !== false;
    }

    public function clearCache(): bool
    {
        if ($this->cacheFile === null || !is_file($this->cacheFile)) {
            return false;
        }
        return @unlink($this->cacheFile);
    }

    protected function exportArray(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = "    " . $this->exportRow($row) . ",";
        }
        return "[\n    'routes' => [\n" . implode("\n", $lines) . "\n    ],\n]";
    }

    protected function exportRow(array $row): string
    {
        $parts = [];
        foreach ($row as $key => $value) {
            $parts[] = "'" . $key . "' => " . $this->exportValue($value);
        }
        return "[" . implode(", ", $parts) . "]";
    }

    protected function exportValue($value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            $isList = array_keys($value) === range(0, count($value) - 1);
            $items = [];
            foreach ($value as $k => $v) {
                $items[] = $isList ? $this->exportValue($v) : "'" . $k . "' => " . $this->exportValue($v);
            }
            return '[' . implode(', ', $items) . ']';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
    }

    /* ---------- 分发 ---------- */

    /**
     * 匹配请求，返回命中的 Route 或 null。
     */
    public function dispatch(Request $request): ?Route
    {
        $method = $request->method();
        $path = $request->path();
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path, $request)) {
                return $route;
            }
        }
        return null;
    }
}
