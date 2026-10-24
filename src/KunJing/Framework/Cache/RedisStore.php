<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：Redis 缓存驱动（净室实现）
 * 优先使用 phpredis 扩展；未安装时回退到 Redis 协议原生 socket 实现（无依赖）。
 */

namespace KunJing\Framework\Cache;

class RedisStore implements CacheInterface
{
    protected array $config;
    /** @var \Redis|null */
    protected $client = null;
    /** @var resource|null 原生 socket 回退 */
    protected $socket = null;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
            'prefix' => 'kj:',
            'timeout' => 2,
        ];
    }

    protected function client()
    {
        if ($this->client !== null) {
            return $this->client;
        }
        if (extension_loaded('redis') && class_exists('\Redis')) {
            $redis = new \Redis();
            $redis->connect($this->config['host'], (int) $this->config['port'], (float) $this->config['timeout']);
            if ($this->config['password'] !== '') {
                $redis->auth($this->config['password']);
            }
            if ($this->config['database'] > 0) {
                $redis->select((int) $this->config['database']);
            }
            $redis->setOption(\Redis::OPT_PREFIX, (string) $this->config['prefix']);
            return $this->client = $redis;
        }
        return $this->connectNative();
    }

    /**
     * 无扩展时的原生 RESP 实现，仅支持本项目用到的命令。
     * @return resource
     */
    protected function connectNative()
    {
        if ($this->socket !== null) {
            return $this->socket;
        }
        $socket = @fsockopen(
            $this->config['host'],
            (int) $this->config['port'],
            $errno,
            $errstr,
            (float) $this->config['timeout']
        );
        if ($socket === false) {
            throw new \RuntimeException("Redis 连接失败：{$errstr}");
        }
        if ($this->config['password'] !== '') {
            $this->command($socket, 'AUTH', $this->config['password']);
        }
        if ($this->config['database'] > 0) {
            $this->command($socket, 'SELECT', (string) $this->config['database']);
        }
        return $this->socket = $socket;
    }

    /**
     * @param resource $socket
     * @return mixed
     */
    protected function command($socket, string $cmd, ...$args)
    {
        $payload = '*' . (count($args) + 1) . "\r\n$" . strlen($cmd) . "\r\n{$cmd}\r\n";
        foreach ($args as $arg) {
            $arg = (string) $arg;
            $payload .= '$' . strlen($arg) . "\r\n{$arg}\r\n";
        }
        fwrite($socket, $payload);
        return $this->readReply($socket);
    }

    /**
     * @param resource $socket
     * @return mixed
     */
    protected function readReply($socket)
    {
        $line = fgets($socket);
        if ($line === false) {
            return null;
        }
        $type = $line[0];
        $body = trim(substr($line, 1));
        switch ($type) {
            case '+':
                return $body === 'OK' ? true : $body;
            case '-':
                throw new \RuntimeException('Redis 错误：' . $body);
            case ':':
                return (int) $body;
            case '$':
                $len = (int) $body;
                if ($len < 0) {
                    return null;
                }
                $data = '';
                while (strlen($data) < $len) {
                    $data .= fread($socket, $len - strlen($data));
                }
                fread($socket, 2);
                return $data;
            case '*':
                $count = (int) $body;
                if ($count < 0) {
                    return null;
                }
                $out = [];
                for ($i = 0; $i < $count; $i++) {
                    $out[] = $this->readReply($socket);
                }
                return $out;
            default:
                return null;
        }
    }

    protected function key(string $key): string
    {
        $prefix = (string) $this->config['prefix'];
        if ($prefix !== '' && !str_starts_with($key, $prefix)) {
            return $prefix . $key;
        }
        return $key;
    }

    public function get(string $key, $default = null)
    {
        $client = $this->client();
        $value = $client instanceof \Redis
            ? $client->get($key)
            : $this->command($client, 'GET', $this->key($key));
        if ($value === false || $value === null) {
            return $default;
        }
        $decoded = @unserialize($value);
        return $decoded === false && $value !== serialize(false) ? $value : $decoded;
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $serialized = serialize($value);
        $client = $this->client();
        if ($client instanceof \Redis) {
            return $ttl > 0 ? $client->setex($key, $ttl, $serialized) : $client->set($key, $serialized);
        }
        $fullKey = $this->key($key);
        return $ttl > 0
            ? (bool) $this->command($client, 'SETEX', $fullKey, (string) $ttl, $serialized)
            : (bool) $this->command($client, 'SET', $fullKey, $serialized);
    }

    public function delete(string $key): bool
    {
        $client = $this->client();
        $res = $client instanceof \Redis
            ? $client->del($key)
            : $this->command($client, 'DEL', $this->key($key));
        return (int) $res > 0;
    }

    public function has(string $key): bool
    {
        $client = $this->client();
        $res = $client instanceof \Redis
            ? $client->exists($key)
            : $this->command($client, 'EXISTS', $this->key($key));
        return (int) $res > 0;
    }

    public function clear(): bool
    {
        $client = $this->client();
        if ($client instanceof \Redis) {
            return $client->flushDB();
        }
        return (bool) $this->command($client, 'FLUSHDB');
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
        $client = $this->client();
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
