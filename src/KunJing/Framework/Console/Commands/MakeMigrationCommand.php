<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成迁移文件 make:migration
 *
 * 用法：php kunjing make:migration create_supplier_table --table=supplier
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;

class MakeMigrationCommand extends BaseCommand
{
    protected string $name = 'make:migration';
    protected string $description = '生成数据库迁移文件';

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? '');
        if ($raw === '') {
            $this->error('请提供迁移名称：php kunjing make:migration create_supplier_table');
            return 1;
        }

        $name = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $raw) ?? $raw);
        $class = $this->studly($name);
        $table = (string) ($this->option('table') ?? $this->guessTable($name));

        $dir = $this->pathFor('migrations', 'database/migrations');
        $file = $dir . '/' . date('Y_m_d_His') . '_' . $name . '.php';

        $create = str_starts_with($name, 'create');
        $content = $create
            ? $this->createStub($class, $table)
            : $this->blankStub($class, $table);

        return $this->writeFile($file, $content, true) ? 0 : 1;
    }

    protected function guessTable(string $name): string
    {
        if (preg_match('/^create_(.+)_table$/', $name, $m)) {
            return $m[1];
        }
        if (preg_match('/^(?:add|update|modify|drop)_(?:.+?)_to_(.+)_table$/', $name, $m)) {
            return $m[1];
        }
        return 'table_name';
    }

    protected function createStub(string $class, string $table): string
    {
        return <<<PHP
<?php

use KunJing\Framework\Database\Migrations\Migration;
use KunJing\Framework\Database\Schema\Blueprint;

class {$class} extends Migration
{
    public function up(): void
    {
        \$this->create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name', 120)->comment('名称');
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        \$this->dropIfExists('{$table}');
    }
}

PHP;
    }

    protected function blankStub(string $class, string $table): string
    {
        return <<<PHP
<?php

use KunJing\Framework\Database\Migrations\Migration;
use KunJing\Framework\Database\Schema\Blueprint;

class {$class} extends Migration
{
    public function up(): void
    {
        \$this->table('{$table}', function (Blueprint \$table) {
            // \$table->string('remark', 255)->nullable()->comment('备注');
        });
    }

    public function down(): void
    {
        // \$this->dropColumn('{$table}', 'remark');
    }
}

PHP;
    }
}
