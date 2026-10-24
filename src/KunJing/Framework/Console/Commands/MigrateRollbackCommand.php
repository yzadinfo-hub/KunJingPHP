<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：回滚迁移 migrate:rollback（--step=N，--all 全部回滚）
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Database\Migrations\Migrator;

class MigrateRollbackCommand extends BaseCommand
{
    protected string $name = 'migrate:rollback';
    protected string $description = '回滚迁移（默认回滚上一批，--step=N / --all）';

    protected ?Kernel $kernel = null;

    public function __construct(string $basePath, Repository $config, ?Kernel $kernel = null)
    {
        parent::__construct($basePath, $config);
        $this->kernel = $kernel;
    }

    protected function handle(): int
    {
        $path = $this->pathFor('migrations', 'database/migrations');
        $connection = $this->kernel !== null
            ? $this->kernel->db()->connection()
            : \KunJing\Framework\Database\Manager::instance()->connection();

        $migrator = new Migrator($connection, $path);
        $all = $this->hasOption('all');
        $step = (int) ($this->option('step', 1) ?: 1);

        try {
            $rolled = $all ? $migrator->reset($path) : $migrator->rollback(max(1, $step), $path);
        } catch (\Throwable $e) {
            $this->error('回滚失败：' . $e->getMessage());
            return 1;
        }

        if ($rolled === []) {
            $this->info('没有可回滚的迁移。');
            return 0;
        }
        foreach ($rolled as $file) {
            $this->warning('已回滚：' . $file);
        }
        $this->line('共回滚 ' . count($rolled) . ' 个迁移。');
        return 0;
    }
}
