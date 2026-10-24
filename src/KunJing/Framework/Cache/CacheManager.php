<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：缓存管理器（净室实现，多驱动 + remember 语义）
 *
 * 用法：
 *  Cache::set('k', 'v', 60);  Cache::get('k');
 *  Cache::remember('k', 60, fn() => 查询DB());
 */

namespace KunJing\Framework\Cache;

class CacheManager
{
    protected array $config;
    /** @var array<string,CacheInterface> */
    protected array $stores = [];
    protected string $default;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'default' => 'file',
            'stores' => [
                'array' => ['driver' => 'array'],
                'file'  => ['driver' => 'file', 'path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing_cache'],
                'redis' => ['driver' => 'redis', 'host' => '127.0.0.1', 'port' => 6379],
            ],
        ];
        $this->default = $this->config['default'] ?? 'file';
    }

    public function store(?string $name = null): CacheInterface
    {
        $name = $name ?? $this->default;
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }
        $cfg = $this->config['stores'][$name] ?? ['driver' => 'file'];
        return $this->stores[$name] = $this->makeStore($cfg);
    }

    protected function makeStore(array $cfg): CacheInterface
    {
        return match ($cfg['driver'] ?? 'file') {
            'array' => new ArrayStore(),
            'file'  => new FileStore($cfg['path'] ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing_cache', $cfg['prefix'] ?? 'kj_'),
            'redis' => new RedisStore($cfg),
            default => throw new \RuntimeException('不支持的缓存驱动：' . ($cfg['driver'] ?? '未知')),
        };
    }

    /* ---------- 快捷代理到默认存储 ---------- */

    public function get(string $key, $default = null)
    {
        return $this->store()->get($key, $default);
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function clear(): bool
    {
        return $this->store()->clear();
    }

    public function increment(string $key, int $step = 1)
    {
        return $this->store()->increment($key, $step);
    }

    public function decrement(string $key, int $step = 1)
    {
        return $this->store()->decrement($key, $step);
    }

    /**
     * 存在则取，不存在则算出来写入（原子语义：单次读取）。
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        if ($this->store()->has($key)) {
            return $this->store()->get($key);
        }
        $value = $callback();
        $this->store()->set($key, $value, $ttl);
        return $value;
    }

    /**
     * 永久记住（无过期）。
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function rememberForever(string $key, callable $callback)
    {
        if ($this->store()->has($key)) {
            return $this->store()->get($key);
        }
        $value = $callback();
        $this->store()->set($key, $value);
        return $value;
    }
}
