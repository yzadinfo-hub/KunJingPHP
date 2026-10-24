<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：数据填充基类（净室实现）
 *
 * 用法：
 *  class SupplierSeeder extends Seeder
 *  {
 *      public function run(): void
 *      {
 *          $this->insert('supplier', [
 *              ['name' => '示例供应商', 'phone' => '13800138000'],
 *          ]);
 *      }
 *  }
 */

namespace KunJing\Framework\Database;

use KunJing\Framework\Database\Query\Builder;

abstract class Seeder
{
    protected ?Manager $manager = null;

    public function setManager(Manager $manager): static
    {
        $this->manager = $manager;
        return $this;
    }

    protected function manager(): Manager
    {
        return $this->manager ?? Manager::instance();
    }

    abstract public function run(): void;

    protected function table(string $table): Builder
    {
        return $this->manager()->table($table);
    }

    /**
     * 批量插入。
     * @param array<int,array<string,mixed>> $rows
     */
    protected function insert(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        return $this->table($table)->insert($rows);
    }

    /**
     * 清空表后重置自增。
     */
    protected function truncate(string $table): void
    {
        $this->manager()->connection()->statement('TRUNCATE TABLE `' . $table . '`');
    }

    /**
     * 调用其他 Seeder。
     * @param array<int,string> $seeders
     */
    protected function call(array $seeders): void
    {
        foreach ($seeders as $seeder) {
            $instance = new $seeder();
            if ($instance instanceof Seeder) {
                $instance->setManager($this->manager);
                $instance->run();
            }
        }
    }
}
