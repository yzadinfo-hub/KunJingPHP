<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 内置命令：环境自检（用于验证框架核心模块是否就绪）
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\Command;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Database\Manager;
use KunJing\Framework\Cache\CacheManager;

class EnvCheckCommand extends Command
{
    protected string $name = 'env:check';
    protected string $description = '环境自检（MySQL / 缓存 / 配置）';

    protected function handle(): int
    {
        $this->info('开始环境自检');

        // PHP 版本
        $phpVersion = PHP_VERSION;
        $this->line('PHP 版本      : ' . $phpVersion . (version_compare($phpVersion, '8.1.0', '>=') ? ' [OK]' : ' [过低]'));

        // 数据库
        try {
            $conn = Manager::instance()->connection();
            $conn->getPdo()->query('SELECT 1');
            $this->line('数据库        : 已连接 [OK]');
        } catch (\Throwable $e) {
            $this->error('数据库        : 连接失败 - ' . $e->getMessage());
        }

        // 缓存
        try {
            $cache = new CacheManager();
            $cache->set('__kj_probe', 'ok', 10);
            $ok = $cache->get('__kj_probe') === 'ok';
            $this->line('缓存          : ' . ($ok ? '可用 [OK]' : '写入失败'));
        } catch (\Throwable $e) {
            $this->error('缓存          : ' . $e->getMessage());
        }

        return 0;
    }
}
