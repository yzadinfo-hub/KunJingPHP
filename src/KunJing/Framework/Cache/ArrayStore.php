<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：内存数组缓存（进程内，常用于测试与 CLI）
 */

namespace KunJing\Framework\Cache;

class ArrayStore implements CacheInterface
{
    /** @var array<string,array{expires:int,value:mixed}> */
    protected array $items = [];

    public function get(string $key, $default = null)
    {
        if (!isset($this->items[$key])) {
            return $default;
        }
        $expires = $this->items[$key]['expires'];
        if ($expires > 0 && time() > $expires) {
            unset($this->items[$key]);
            return $default;
        }
        return $this->items[$key]['value'];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->items[$key] = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->items[$key])) {
            return false;
        }
        $expires = $this->items[$key]['expires'];
        if ($expires > 0 && time() > $expires) {
            unset($this->items[$key]);
            return false;
        }
        return true;
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    public function getMultiple(array $keys, $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function increment(string $key, int $step = 1)
    {
        if (!$this->has($key)) {
            $this->set($key, 0);
        }
        $value = (int) $this->get($key, 0) + $step;
        $this->set($key, $value);
        return $value;
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->increment($key, -$step);
    }
}
