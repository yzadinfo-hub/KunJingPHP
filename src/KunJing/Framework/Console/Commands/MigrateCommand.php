<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：执行迁移 migrate（--pretend 只演练）
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Database\Migrations\Migrator;

class MigrateCommand extends BaseCommand
{
    protected string $name = 'migrate';
    protected string $description = '执行未完成的数据库迁移';

    protected ?Kernel $kernel = null;

    public function __construct(string $basePath, Repository $config, ?Kernel $kernel = null)
    {
        parent::__construct($basePath, $config);
        $this->kernel = $kernel;
    }

    protected function handle(): int
    {
        $path = $this->pathFor('migrations', 'database/migrations');
        if (!is_dir($path)) {
            $this->error('迁移目录不存在：' . $path);
            return 1;
        }

        $connection = $this->kernel !== null
            ? $this->kernel->db()->connection()
            : \KunJing\Framework\Database\Manager::instance()->connection();

        $migrator = new Migrator($connection, $path);
        $pretend = $this->hasOption('pretend');

        $pending = $migrator->pending($path);
        if ($pending === []) {
            $this->info('没有待执行的迁移。');
            return 0;
        }

        if ($pretend) {
            $this->warning('演练模式（不实际执行）：');
            foreach ($pending as $file) {
                $this->line('  - ' . $file);
            }
            return 0;
        }

        try {
            $executed = $migrator->run($path);
        } catch (\Throwable $e) {
            $this->error('迁移失败：' . $e->getMessage());
            return 1;
        }

        foreach ($executed as $file) {
            $this->info('已迁移：' . $file);
        }
        $this->line('共执行 ' . count($executed) . ' 个迁移。');
        return 0;
    }
}
