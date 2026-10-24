<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：命令基类（持有项目根路径与配置，供内置命令复用）
 */

namespace KunJing\Framework\Console;

use KunJing\Framework\Config\Repository;

abstract class BaseCommand extends Command
{
    protected string $basePath;
    protected Repository $config;

    public function __construct(string $basePath, Repository $config)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->config = $config;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getConfig(): Repository
    {
        return $this->config;
    }

    /**
     * 解析目录：优先取配置 app.paths.{key}，否则用默认相对路径。
     */
    protected function pathFor(string $key, string $default): string
    {
        $configured = $this->config->get('app.paths.' . $key);
        $relative = is_string($configured) && $configured !== '' ? $configured : $default;
        return $this->absolute($relative);
    }

    protected function absolute(string $relative): string
    {
        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $relative)) {
            return rtrim($relative, '/\\');
        }
        return $this->basePath . '/' . trim($relative, '/\\');
    }

    /**
     * 应用根命名空间，默认 App。
     */
    protected function namespace(): string
    {
        $ns = $this->config->get('app.namespace', 'App');
        return is_string($ns) && $ns !== '' ? rtrim($ns, '\\') : 'App';
    }

    /**
     * 写入文件并提示，目录不存在时自动创建；文件已存在则不覆盖（除非 force）。
     */
    protected function writeFile(string $path, string $content, bool $force = false): bool
    {
        if (is_file($path) && !$force) {
            $this->error("文件已存在：{$path}（加 --force 覆盖）");
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (file_put_contents($path, $content) === false) {
            $this->error("写入失败：{$path}");
            return false;
        }
        $this->info('已生成：' . $path);
        return true;
    }

    protected function force(): bool
    {
        $value = $this->option('force', false);
        return $value === true || $value === 'true' || $value === '1' || $value === '';
    }

    /**
     * 首字母大写的驼峰类名。
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }

    /**
     * 下划线蛇形。
     */
    protected function snake(string $value): string
    {
        $value = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value);
        return strtolower($value);
    }
}
