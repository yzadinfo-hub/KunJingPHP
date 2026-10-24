<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成数据填充 make:seeder
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;

class MakeSeederCommand extends BaseCommand
{
    protected string $name = 'make:seeder';
    protected string $description = '生成数据填充器';

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? 'DatabaseSeeder');
        $class = $this->studly($raw);
        if (!str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $dir = $this->pathFor('seeders', 'database/seeders');
        $namespace = (string) $this->config->get('app.seeder_namespace', 'Database\\Seeders');
        $file = $dir . '/' . $class . '.php';

        $content = <<<PHP
<?php

namespace {$namespace};

use KunJing\Framework\Database\Seeder;

class {$class} extends Seeder
{
    public function run(): void
    {
        // \$this->insert('table_name', [
        //     ['name' => '示例数据'],
        // ]);
    }
}

PHP;

        return $this->writeFile($file, $content, $this->force()) ? 0 : 1;
    }
}
