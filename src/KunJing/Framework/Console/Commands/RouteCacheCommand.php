<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成路由缓存 route:cache
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;

class RouteCacheCommand extends BaseCommand
{
    protected string $name = 'route:cache';
    protected string $description = '生成路由缓存（闭包路由无法缓存）';

    protected ?Kernel $kernel = null;

    public function __construct(string $basePath, Repository $config, ?Kernel $kernel = null)
    {
        parent::__construct($basePath, $config);
        $this->kernel = $kernel;
    }

    protected function handle(): int
    {
        if ($this->kernel === null) {
            $this->error('缺少命令行内核，无法加载路由。');
            return 1;
        }
        $router = $this->kernel->loadRoutes();
        $file = $this->cacheFile();
        $router->cacheFile($file);

        $before = count($router->getRoutes());
        $ok = $router->dumpCache();
        if (!$ok) {
            $this->error('路由缓存写入失败：' . $file);
            return 1;
        }
        $after = count($router->getRoutes());
        $this->info('路由缓存已生成：' . $file);
        $this->line("已注册 {$before} 条路由，其中 {$after} 条可缓存（闭包路由已被跳过）。");
        return 0;
    }

    protected function cacheFile(): string
    {
        $configured = $this->config->get('route.cache_file');
        if (is_string($configured) && $configured !== '') {
            return $this->absolute($configured);
        }
        return $this->absolute('runtime/cache/routes.php');
    }
}
