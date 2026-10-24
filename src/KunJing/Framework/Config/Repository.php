<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：配置仓库（点号路径读写，净室实现）
 */

namespace KunJing\Framework\Config;

class Repository
{
    /** @var array<string, mixed> */
    protected array $items = [];

    /**
     * 写入配置项，支持点号路径（如 app.debug）。
     */
    public function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $array = &$this->items;
        foreach ($segments as $segment) {
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
        $array = $value;
    }

    /**
     * 读取配置项，支持点号路径与默认值。
     */
    public function get(string $key, $default = null)
    {
        if (!str_contains($key, '.')) {
            return $this->items[$key] ?? $default;
        }

        $array = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->items)) {
            return true;
        }
        $array = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }
        return true;
    }

    public function all(): array
    {
        return $this->items;
    }

    /**
     * 合并一批配置（数组形式）。
     */
    public function load(array $items): void
    {
        $this->items = array_merge($this->items, $items);
    }
}
