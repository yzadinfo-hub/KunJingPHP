<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：PSR-4 自动加载器（净室实现，不依赖 Composer ClassLoader 源码）
 */

namespace KunJing\Framework\Support;

class Autoload
{
    /** @var array<string, array<int, string>> */
    private static array $prefixes = [];

    private static bool $registered = false;

    /**
     * 注册一个 PSR-4 命名空间前缀到基础目录的映射。
     */
    public static function addNamespace(string $prefix, string $baseDir, string $separator = '\\'): void
    {
        $prefix = trim($prefix, $separator) . $separator;
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        self::$prefixes[$prefix][] = $baseDir;
    }

    /**
     * 注册 SPL 自动加载函数（幂等）。
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        spl_autoload_register([self::class, 'loadClass']);
        self::$registered = true;
    }

    /**
     * 按类名尝试加载对应文件。
     */
    public static function loadClass(string $class): bool
    {
        foreach (self::$prefixes as $prefix => $dirs) {
            $len = strlen($prefix);
            if (strncmp($class, $prefix, $len) !== 0) {
                continue;
            }
            $relative = substr($class, $len);
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            foreach ($dirs as $baseDir) {
                $file = $baseDir . $relativePath;
                if (is_file($file)) {
                    require $file;
                    return true;
                }
            }
        }
        return false;
    }
}
