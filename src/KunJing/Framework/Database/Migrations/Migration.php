<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：迁移基类（净室实现）
 *
 * 用法：
 *  class CreateSupplierTable extends Migration
 *  {
 *      public function up(): void
 *      {
 *          $this->create('supplier', function (Blueprint $table) {
 *              $table->id();
 *              $table->string('name', 120);
 *              $table->timestamps();
 *          });
 *      }
 *
 *      public function down(): void
 *      {
 *          $this->dropIfExists('supplier');
 *      }
 *  }
 */

namespace KunJing\Framework\Database\Migrations;

use KunJing\Framework\Database\Connection;
use KunJing\Framework\Database\Schema\Blueprint;
use KunJing\Framework\Database\Schema\Column;
use KunJing\Framework\Exception\FrameworkException;

abstract class Migration
{
    protected ?Connection $connection = null;

    public function setConnection(Connection $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    public function connection(): Connection
    {
        if ($this->connection === null) {
            throw new FrameworkException('迁移未绑定数据库连接。');
        }
        return $this->connection;
    }

    abstract public function up(): void;

    public function down(): void
    {
        // 默认不可回滚
    }

    /* ---------- 建表 / 删表 ---------- */

    /**
     * @param callable(Blueprint):void $callback
     */
    protected function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $this->execute($blueprint->toCreateSql());
    }

    protected function drop(string $table): void
    {
        $this->execute('DROP TABLE `' . $table . '`');
    }

    protected function dropIfExists(string $table): void
    {
        $this->execute('DROP TABLE IF EXISTS `' . $table . '`');
    }

    protected function rename(string $from, string $to): void
    {
        $this->execute('RENAME TABLE `' . $from . '` TO `' . $to . '`');
    }

    /**
     * @param callable(Blueprint):void $callback
     */
    protected function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        foreach ($blueprint->getColumns() as $column) {
            $this->execute('ALTER TABLE `' . $table . '` ADD ' . $column->toSql());
        }
    }

    protected function addColumn(string $table, Column $column): void
    {
        $this->execute('ALTER TABLE `' . $table . '` ADD ' . $column->toSql());
    }

    protected function dropColumn(string $table, string $column): void
    {
        $this->execute('ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`');
    }

    /* ---------- 原生 ---------- */

    /** @param array<int,mixed> $bindings */
    protected function execute(string $sql, array $bindings = []): int
    {
        return $this->connection()->statement($sql, $bindings);
    }

    protected function hasTable(string $table): bool
    {
        try {
            $rows = $this->connection()->select('SHOW TABLES LIKE ' . "'" . str_replace("'", "\\'", $table) . "'");
        } catch (\Throwable $e) {
            return false;
        }
        return $rows !== [];
    }
}
