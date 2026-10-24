<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：清理路由缓存 route:clear
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;

class RouteClearCommand extends BaseCommand
{
    protected string $name = 'route:clear';
    protected string $description = '清理路由缓存文件';

    protected ?Kernel $kernel = null;

    public function __construct(string $basePath, Repository $config, ?Kernel $kernel = null)
    {
        parent::__construct($basePath, $config);
        $this->kernel = $kernel;
    }

    protected function handle(): int
    {
        $configured = $this->config->get('route.cache_file');
        $file = is_string($configured) && $configured !== ''
            ? $this->absolute($configured)
            : $this->absolute('runtime/cache/routes.php');

        if (!is_file($file)) {
            $this->warning('路由缓存不存在：' . $file);
            return 0;
        }
        if (!@unlink($file)) {
            $this->error('删除失败：' . $file);
            return 1;
        }
        $this->info('已清理路由缓存：' . $file);
        return 0;
    }
}
