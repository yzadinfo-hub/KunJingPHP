<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：执行数据填充 db:seed（默认 DatabaseSeeder）
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Database\Seeder;

class DbSeedCommand extends BaseCommand
{
    protected string $name = 'db:seed';
    protected string $description = '执行数据填充（php kunjing db:seed UserSeeder）';

    protected ?Kernel $kernel = null;

    public function __construct(string $basePath, Repository $config, ?Kernel $kernel = null)
    {
        parent::__construct($basePath, $config);
        $this->kernel = $kernel;
    }

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? 'DatabaseSeeder');
        $class = $this->studly($raw);
        if (!str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $dir = $this->pathFor('seeders', 'database/seeders');
        $file = $dir . '/' . $class . '.php';
        if (!is_file($file)) {
            $this->error('填充器不存在：' . $file);
            return 1;
        }

        require_once $file;
        $fqcn = $this->resolveClass($class);
        if ($fqcn === null || !class_exists($fqcn)) {
            $this->error("填充类 [{$class}] 未找到。");
            return 1;
        }

        $seeder = new $fqcn();
        if (!$seeder instanceof Seeder) {
            $this->error("填充类 [{$fqcn}] 必须继承 Seeder。");
            return 1;
        }

        $manager = $this->kernel !== null
            ? $this->kernel->db()
            : \KunJing\Framework\Database\Manager::instance();
        $seeder->setManager($manager);

        try {
            $seeder->run();
        } catch (\Throwable $e) {
            $this->error('填充失败：' . $e->getMessage());
            return 1;
        }

        $this->info('已完成数据填充：' . $fqcn);
        return 0;
    }

    protected function resolveClass(string $class): ?string
    {
        if (class_exists($class)) {
            return $class;
        }
        foreach (get_declared_classes() as $declared) {
            if (str_ends_with($declared, '\\' . $class)) {
                return $declared;
            }
        }
        return null;
    }
}
