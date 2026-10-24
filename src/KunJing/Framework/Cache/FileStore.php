<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：文件缓存驱动（净室实现）
 * 序列化写入文件，带过期时间；无需任何扩展。
 */

namespace KunJing\Framework\Cache;

class FileStore implements CacheInterface
{
    protected string $directory;
    protected string $prefix;

    public function __construct(string $directory, string $prefix = 'kj_')
    {
        $this->directory = rtrim($directory, '/\\');
        $this->prefix = $prefix;
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }
    }

    protected function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->prefix . md5($key) . '.cache';
    }

    /**
     * @return array{expires:int,value:mixed}|null
     */
    protected function read(string $key): ?array
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = @unserialize($raw);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            return null;
        }
        return $data;
    }

    public function get(string $key, $default = null)
    {
        $data = $this->read($key);
        if ($data === null) {
            return $default;
        }
        $expires = (int) ($data['expires'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            $this->delete($key);
            return $default;
        }
        return $data['value'];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $data = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ];
        return @file_put_contents($this->path($key), serialize($data)) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->path($key);
        return !is_file($file) || @unlink($file);
    }

    public function has(string $key): bool
    {
        $data = $this->read($key);
        if ($data === null) {
            return false;
        }
        $expires = (int) ($data['expires'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            $this->delete($key);
            return false;
        }
        return true;
    }

    public function clear(): bool
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . $this->prefix . '*.cache') ?: [] as $file) {
            @unlink($file);
        }
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
