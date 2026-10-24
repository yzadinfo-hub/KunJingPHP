<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：迁移状态 migrate:status
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Database\Migrations\Migrator;

class MigrateStatusCommand extends BaseCommand
{
    protected string $name = 'migrate:status';
    protected string $description = '查看迁移执行状态';

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
        $status = $migrator->status($path);

        if ($status === []) {
            $this->warning('迁移目录为空：' . $path);
            return 0;
        }

        $rows = [];
        foreach ($status as $row) {
            $rows[] = [
                '已执行' => $row['ran'] ? '是' : '否',
                '批次'  => (string) ($row['batch'] ?? '-'),
                '文件'  => $row['file'],
                '类名'  => $row['name'],
            ];
        }
        $this->output()->table($rows);
        return 0;
    }
}
