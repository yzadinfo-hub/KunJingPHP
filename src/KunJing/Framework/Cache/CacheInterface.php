<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：缓存驱动接口（净室实现，能力对标 ThinkPHP Cache）
 */

namespace KunJing\Framework\Cache;

interface CacheInterface
{
    /**
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param mixed $value
     * @param int|null $ttl 秒；null 表示永久
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    public function delete(string $key): bool;

    public function has(string $key): bool;

    public function clear(): bool;

    /**
     * @param array<string> $keys
     * @return array<string,mixed>
     */
    public function getMultiple(array $keys, $default = null): array;

    /**
     * @param array<string,mixed> $values
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    public function increment(string $key, int $step = 1);

    public function decrement(string $key, int $step = 1);
}
