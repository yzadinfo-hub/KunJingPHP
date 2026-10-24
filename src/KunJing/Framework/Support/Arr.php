<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：数组辅助（点号路径读写，净室实现）
 */

namespace KunJing\Framework\Support;

class Arr
{
    public static function get(array $array, string $key, $default = null)
    {
        if (!str_contains($key, '.')) {
            return $array[$key] ?? $default;
        }
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    public static function set(array &$array, string $key, $value): void
    {
        $segments = explode('.', $key);
        $ref = &$array;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function exists(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }
        $ref = $array;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                return false;
            }
            $ref = $ref[$segment];
        }
        return true;
    }
}
