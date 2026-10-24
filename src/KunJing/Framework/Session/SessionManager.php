<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：会话管理器（净室实现，能力对标 ThinkPHP Session）
 * 驱动：file（默认，自有序列化）/ array（进程内，测试与 CLI）。
 * 不依赖 PHP 原生 session，避免 CLI/测试场景的 header 限制。
 */

namespace KunJing\Framework\Session;

class SessionManager
{
    protected array $config;
    protected ?Store $store = null;
    protected bool $started = false;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'driver'   => 'file',
            'files'    => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing_session',
            'name'     => 'KJSESSID',
            'lifetime' => 7200,
        ];
        if (!is_dir($this->config['files'])) {
            @mkdir($this->config['files'], 0755, true);
        }
    }

    public function start(): Store
    {
        if ($this->started && $this->store !== null) {
            return $this->store;
        }
        $id = $this->resolveId();
        $payload = [];
        if ($id !== null) {
            $payload = $this->read($id);
        }
        // 只要客户端携带了合法会话 id，就沿用它（避免匿名访客每次都换新 id 造成 Cookie 抖动）。
        // 仅当完全没有 id 时才新生成。
        if ($id === null) {
            $id = $this->generateId();
        }
        $this->store = Store::fromArray($id, $payload);
        $this->store->rotateFlash();
        $this->started = true;
        return $this->store;
    }

    protected function resolveId(): ?string
    {
        $cookie = $_COOKIE[$this->config['name']] ?? null;
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }
        return null;
    }

    protected function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return array<string,mixed>
     */
    protected function read(string $id): array
    {
        if ($this->config['driver'] === 'array') {
            return $GLOBALS['__kj_sessions'][$id] ?? [];
        }
        $file = $this->filePath($id);
        if (!is_file($file)) {
            return [];
        }
        if (time() - filemtime($file) > (int) $this->config['lifetime']) {
            @unlink($file);
            return [];
        }
        $raw = @file_get_contents($file);
        return $raw === false ? [] : (@unserialize($raw) ?: []);
    }

    protected function write(string $id, array $payload): bool
    {
        if ($this->config['driver'] === 'array') {
            $GLOBALS['__kj_sessions'][$id] = $payload;
            return true;
        }
        return @file_put_contents($this->filePath($id), serialize($payload)) !== false;
    }

    protected function filePath(string $id): string
    {
        return rtrim($this->config['files'], '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $id;
    }

    public function save(): void
    {
        if ($this->store === null) {
            return;
        }
        $id = $this->store->getId();
        if ($id === null) {
            return;
        }
        $this->write($id, $this->store->toArray());
    }

    public function store(): Store
    {
        return $this->start();
    }

    public function id(): ?string
    {
        return $this->store()->getId();
    }

    public function getId(): ?string
    {
        return $this->id();
    }

    public function regenerate(): string
    {
        $newId = $this->generateId();
        $old = $this->store()->getId();
        if ($this->config['driver'] !== 'array' && $old !== null) {
            $file = $this->filePath($old);
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->store()->setId($newId);
        return $newId;
    }

    private function setCookieAttempt(string $id): void
    {
        if (!headers_sent()) {
            @setcookie($this->config['name'], $id, time() + (int) $this->config['lifetime'], '/');
        }
        $_COOKIE[$this->config['name']] = $id;
    }

    /* ---------- 代理 Store ---------- */

    public function get(string $key, $default = null)
    {
        return $this->store()->get($key, $default);
    }

    public function set(string $key, $value): void
    {
        $this->store()->set($key, $value);
        $this->save();
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function forget(string $key): void
    {
        $this->store()->forget($key);
        $this->save();
    }

    public function pull(string $key, $default = null)
    {
        $value = $this->store()->pull($key, $default);
        $this->save();
        return $value;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->store()->all();
    }

    public function flush(): void
    {
        $this->store()->flush();
        $this->save();
    }

    public function flash(string $key, $value): void
    {
        $this->store()->flash($key, $value);
        $this->save();
    }

    public function getFlash(string $key, $default = null)
    {
        return $this->store()->getFlash($key, $default);
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function issueCookie(): void
    {
        $id = $this->store()->getId();
        if ($id !== null) {
            $this->setCookieAttempt($id);
        }
    }
}
