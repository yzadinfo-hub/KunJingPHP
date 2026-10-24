<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：中间件洋葱管道（净室实现，能力对标 ThinkPHP Middleware）
 * 用法：$pipe->send($request)->through($middlewares)->then($core);
 */

namespace KunJing\Framework\Http\Middleware;

class Pipeline
{
    protected mixed $passable;
    /** @var array<int, mixed> */
    protected array $pipes = [];
    protected string $method = 'handle';

    public function send(mixed $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /** @param array<int, mixed> $pipes */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function via(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 执行管道，最终抵达核心回调。
     */
    public function then(callable $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            function ($passable) use ($destination) {
                return $destination($passable);
            }
        );

        return $pipeline($this->passable);
    }

    protected function carry(): \Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_callable($pipe)) {
                    return $pipe($passable, $stack);
                }
                if (is_string($pipe) && class_exists($pipe)) {
                    $instance = new $pipe();
                    $method = $this->method;
                    return $instance->$method($passable, $stack);
                }
                if (is_object($pipe) && method_exists($pipe, $this->method)) {
                    $method = $this->method;
                    return $pipe->$method($passable, $stack);
                }
                throw new \RuntimeException('中间件类型无效，无法执行。');
            };
        };
    }
}
