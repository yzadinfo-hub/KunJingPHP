<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：会话数据容器（净室实现）
 * 支持一次性闪存数据（flash），用于表单校验错误/提示。
 */

namespace KunJing\Framework\Session;

class Store
{
    protected ?string $id = null;
    /** @var array<string,mixed> */
    protected array $attributes = [];
    /** @var array<string,mixed> 上一请求的闪存（可读） */
    protected array $flashOld = [];
    /** @var array<string,mixed> 写入后下一请求可用的闪存 */
    protected array $flashNew = [];

    public function __construct(?string $id = null, array $attributes = [])
    {
        $this->id = $id;
        $this->attributes = $attributes;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function forget(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * 取出并删除。
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function flush(): void
    {
        $this->attributes = [];
        $this->flashNew = [];
    }

    /* ---------- 闪存 ---------- */

    public function flash(string $key, $value): void
    {
        $this->flashNew[$key] = $value;
        // 闪存同时也立即可读
        $this->flashOld[$key] = $value;
    }

    public function getFlash(string $key, $default = null)
    {
        return $this->flashOld[$key] ?? $default;
    }

    public function hasFlash(string $key): bool
    {
        return array_key_exists($key, $this->flashOld);
    }

    /**
     * @return array<string,mixed>
     */
    public function getFlashNew(): array
    {
        return $this->flashNew;
    }

    /**
     * @param array<string,mixed> $old
     */
    public function setFlashOld(array $old): void
    {
        $this->flashOld = $old;
    }

    /**
     * @return array<string,mixed>
     */
    public function getFlashOld(): array
    {
        return $this->flashOld;
    }

    /**
     * 请求开始时调用：旧闪存可本次读取，新闪存待下次生效。
     */
    public function rotateFlash(): void
    {
        $this->flashNew = [];
    }

    /**
     * 序列化为可持久化的数组。
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'attributes' => $this->attributes,
            'flash'      => $this->flashNew,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromArray(?string $id, array $payload): static
    {
        $store = new static($id, $payload['attributes'] ?? []);
        $store->setFlashOld($payload['flash'] ?? []);
        return $store;
    }
}
