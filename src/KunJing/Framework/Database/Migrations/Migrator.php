<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：迁移执行器（净室实现）
 *
 * 用法：
 *  $migrator = new Migrator($connection, __DIR__ . '/database/migrations');
 *  $migrator->run();              // 执行未迁移的文件
 *  $migrator->rollback();         // 回滚上一批
 *  $migrator->status();           // 查看状态
 */

namespace KunJing\Framework\Database\Migrations;

use KunJing\Framework\Database\Connection;

class Migrator
{
    protected Connection $connection;
    protected string $path;
    protected string $table = 'migrations';

    public function __construct(Connection $connection, string $path = '', string $table = 'migrations')
    {
        $this->connection = $connection;
        $this->path = $path;
        $this->table = $table;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;
        return $this;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * 确保迁移记录表存在。
     */
    public function ensureRepository(): void
    {
        $this->connection->statement(
            'CREATE TABLE IF NOT EXISTS `' . $this->table . '` ('
            . '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, '
            . '`migration` VARCHAR(255) NOT NULL, '
            . '`batch` INT UNSIGNED NOT NULL DEFAULT 1, '
            . '`created_at` TIMESTAMP NULL DEFAULT NULL, '
            . 'PRIMARY KEY (`id`), UNIQUE KEY `uk_migration` (`migration`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * 已执行记录：['2026_09_25_000000_create_x' => 1, ...]（值为批次）
     * @return array<string,int>
     */
    public function getRan(): array
    {
        $this->ensureRepository();
        $rows = $this->connection->select('SELECT `migration`, `batch` FROM `' . $this->table . '` ORDER BY `id` ASC');
        $ran = [];
        foreach ($rows as $row) {
            $ran[(string) $row['migration']] = (int) $row['batch'];
        }
        return $ran;
    }

    /**
     * 扫描目录得到迁移文件（按文件名升序）。
     * @return array<int,string> 文件名（不含 .php）
     */
    public function getMigrationFiles(?string $path = null): array
    {
        $dir = $path ?? $this->path;
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (str_ends_with($file, '.php')) {
                $files[] = substr($file, 0, -4);
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    /**
     * 待执行的迁移文件名。
     * @return array<int,string>
     */
    public function pending(?string $path = null): array
    {
        $ran = $this->getRan();
        return array_values(array_filter(
            $this->getMigrationFiles($path),
            fn($file) => !isset($ran[$file])
        ));
    }

    /**
     * 执行迁移，返回本次执行的文件名。
     * @return array<int,string>
     */
    public function run(?string $path = null, bool $pretend = false): array
    {
        $this->ensureRepository();
        $files = $this->pending($path);
        if ($files === []) {
            return [];
        }
        $batch = $this->getNextBatchNumber();
        $executed = [];
        foreach ($files as $file) {
            if ($pretend) {
                $executed[] = $file;
                continue;
            }
            $migration = $this->resolve($file, $path);
            $migration->up();
            $this->log($file, $batch);
            $executed[] = $file;
        }
        return $executed;
    }

    /**
     * 回滚若干批（默认 1 批）。
     * @return array<int,string>
     */
    public function rollback(int $step = 1, ?string $path = null): array
    {
        $this->ensureRepository();
        $ran = $this->getRan();
        if ($ran === []) {
            return [];
        }
        // 按批次分组（倒序）
        $batches = [];
        foreach ($ran as $file => $batch) {
            $batches[$batch][] = $file;
        }
        krsort($batches);

        $rolled = [];
        $count = 0;
        foreach ($batches as $batch => $files) {
            $files = array_reverse($files);
            foreach ($files as $file) {
                $migration = $this->resolve($file, $path);
                $migration->down();
                $this->deleteLog($file);
                $rolled[] = $file;
            }
            $count++;
            if ($count >= $step) {
                break;
            }
        }
        return $rolled;
    }

    /**
     * 回滚全部。
     * @return array<int,string>
     */
    public function reset(?string $path = null): array
    {
        $this->ensureRepository();
        $ran = $this->getRan();
        $rolled = [];
        foreach (array_reverse(array_keys($ran)) as $file) {
            $migration = $this->resolve($file, $path);
            $migration->down();
            $this->deleteLog($file);
            $rolled[] = $file;
        }
        return $rolled;
    }

    /**
     * 状态列表：[['file'=>, 'name'=>, 'batch'=>int|null, 'ran'=>bool], ...]
     * @return array<int,array<string,mixed>>
     */
    public function status(?string $path = null): array
    {
        $ran = $this->getRan();
        $rows = [];
        foreach ($this->getMigrationFiles($path) as $file) {
            $rows[] = [
                'file'  => $file,
                'name'  => $this->className($file),
                'ran'   => isset($ran[$file]),
                'batch' => $ran[$file] ?? null,
            ];
        }
        return $rows;
    }

    /* ---------- 内部 ---------- */

    protected function resolve(string $file, ?string $path = null): Migration
    {
        $dir = $path ?? $this->path;
        $full = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file . '.php';
        if (!is_file($full)) {
            throw new \RuntimeException("迁移文件不存在：{$full}");
        }
        require_once $full;
        $class = $this->className($file);
        if (!class_exists($class)) {
            // 兼容：类名可能带命名空间，取文件中最后一个声明的类
            $declared = get_declared_classes();
            $candidates = array_filter($declared, function ($c) use ($class) {
                return str_ends_with($c, '\\' . $class) || $c === $class;
            });
            if ($candidates === []) {
                throw new \RuntimeException("迁移类 [{$class}] 未定义于 {$full}");
            }
            $class = (string) array_values($candidates)[0];
        }
        $migration = new $class();
        if (!$migration instanceof Migration) {
            throw new \RuntimeException("迁移类 [{$class}] 必须继承 Migration。");
        }
        return $migration->setConnection($this->connection);
    }

    /**
     * 文件名 → 类名：2026_09_25_000000_create_supplier_table → CreateSupplierTable
     */
    public function className(string $file): string
    {
        // 去掉前导的时间戳段：2026_09_25_000000_create_x_table → create_x_table
        $name = preg_replace('/^(\d+_)+/', '', $file) ?? $file;
        $parts = explode('_', $name);
        return implode('', array_map('ucfirst', $parts));
    }

    protected function getNextBatchNumber(): int
    {
        $rows = $this->connection->select('SELECT MAX(`batch`) AS `max` FROM `' . $this->table . '`');
        return ((int) ($rows[0]['max'] ?? 0)) + 1;
    }

    protected function log(string $file, int $batch): void
    {
        $this->connection->statement(
            'INSERT INTO `' . $this->table . '` (`migration`, `batch`, `created_at`) VALUES (?, ?, ?)',
            [$file, $batch, date('Y-m-d H:i:s')]
        );
    }

    protected function deleteLog(string $file): void
    {
        $this->connection->statement(
            'DELETE FROM `' . $this->table . '` WHERE `migration` = ?',
            [$file]
        );
    }
}
