<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：IoC 容器与依赖注入（净室实现）
 * 能力对标 ThinkPHP 8.1 容器：bind / singleton / make / call，基于反射的构造器自动注入。
 */

namespace KunJing\Framework\Container;

class Container
{
    private static ?Container $instance = null;

    /** @var array<string, array{concrete: mixed, shared: bool}> */
    protected array $bindings = [];

    /** @var array<string, object> */
    protected array $instances = [];

    /** @var array<string, string> */
    protected array $aliases = [];

    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->dropStale($abstract);
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => $shared,
        ];
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->dropStale($abstract);
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function bound(string $abstract): bool
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    protected function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    protected function dropStale(string $abstract): void
    {
        unset($this->bindings[$abstract], $this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * 解析一个抽象（类/绑定名），必要时构建并缓存共享实例。
     */
    public function make(string $abstract, array $params = [])
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;
        $shared   = $this->bindings[$abstract]['shared'] ?? false;

        $object = $this->build($concrete, $params);

        if ($shared) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 构建具体实现：闭包直接执行；类名则反射解析构造依赖。
     */
    public function build($concrete, array $params = [])
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this, $params);
        }

        if (!is_string($concrete) || !class_exists($concrete)) {
            if (is_string($concrete) && $this->bound($concrete)) {
                return $this->make($concrete, $params);
            }
            throw new \RuntimeException("类 [{$concrete}] 不存在，容器无法构建。");
        }

        $reflector = new \ReflectionClass($concrete);
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("类 [{$concrete}] 不可实例化。");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }

        $args = $this->resolveParameters($constructor->getParameters(), $params);

        return $reflector->newInstanceArgs($args);
    }

    /**
     * 调用任意 callable，自动注入其依赖。
     */
    public function call(callable $callback, array $params = [])
    {
        if (is_array($callback)) {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
            $parameters = $reflector->getParameters();
        } elseif (is_string($callback) && str_contains($callback, '::')) {
            [$class, $method] = explode('::', $callback, 2);
            $reflector = new \ReflectionMethod($class, $method);
            $parameters = $reflector->getParameters();
        } else {
            $reflector = new \ReflectionFunction(\Closure::fromCallable($callback));
            $parameters = $reflector->getParameters();
        }

        $args = $this->resolveParameters($parameters, $params);
        return call_user_func_array($callback, $args);
    }

    /**
     * 解析方法/构造器的参数列表：命名传参 > 类型提示自动注入 > 默认值。
     */
    protected function resolveParameters(array $parameters, array $params): array
    {
        $args = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->isOptional()) {
                $args[] = null;
                continue;
            }

            throw new \RuntimeException("容器无法解析构造函数参数 [\${$name}]。");
        }
        return $args;
    }
}
