<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：事件调度器（净室实现，能力对标 ThinkPHP Event）
 *
 * 用法：
 *  Event::listen('user.created', function($payload){...});
 *  Event::dispatch('user.created', $user);
 */

namespace KunJing\Framework\Event;

class Dispatcher
{
    /**
     * @var array<string,array<int,array{listener:callable,priority:int,once:bool}>>
     */
    protected array $listeners = [];

    /**
     * @var array<string,string> 事件类 => 描述
     */
    protected array $eventMap = [];

    /**
     * 注册监听器，priority 越大越先执行。
     */
    public function listen(string $event, callable $listener, int $priority = 0, bool $once = false): void
    {
        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority,
            'once'     => $once,
        ];
        usort($this->listeners[$event], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * 只触发一次的监听器。
     */
    public function once(string $event, callable $listener, int $priority = 0): void
    {
        $this->listen($event, $listener, $priority, true);
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * @return array<int,array{listener:callable,priority:int,once:bool}>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * 派发事件。
     * @param mixed $payload
     * @param bool $halt 监听器返回 false 时中断
     * @return array<int,mixed>
     */
    public function dispatch(string $event, $payload = null, bool $halt = false): array
    {
        $results = [];
        foreach ($this->listeners[$event] ?? [] as $index => $item) {
            $result = call_user_func($item['listener'], $payload, $this);
            $results[] = $result;
            if ($item['once']) {
                unset($this->listeners[$event][$index]);
            }
            if ($halt && $result === false) {
                break;
            }
        }
        if (empty($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
        return $results;
    }

    /**
     * 事件类映射（可选）。
     */
    public function map(string $eventClass, string $alias): void
    {
        $this->eventMap[$eventClass] = $alias;
    }

    public function getEvents(): array
    {
        return array_keys($this->listeners);
    }
}
