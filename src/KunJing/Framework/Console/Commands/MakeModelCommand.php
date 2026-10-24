<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成模型 make:model
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;

class MakeModelCommand extends BaseCommand
{
    protected string $name = 'make:model';
    protected string $description = '生成数据模型（--table=xxx 指定表名）';

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? '');
        if ($raw === '') {
            $this->error('请提供模型名称：php kunjing make:model Supplier');
            return 1;
        }

        $class = $this->studly($raw);
        $table = (string) ($this->option('table') ?? $this->snake($class));

        $dir = $this->pathFor('models', 'app/Models');
        $namespace = $this->namespace() . '\\Models';
        $file = $dir . '/' . $class . '.php';

        $content = <<<PHP
<?php

namespace {$namespace};

use KunJing\Framework\Database\Model\Model;
use KunJing\Framework\Database\Model\Relations\BelongsTo;
use KunJing\Framework\Database\Model\Relations\HasMany;
use KunJing\Framework\Database\Model\Relations\HasOne;

class {$class} extends Model
{
    /** 数据表名 */
    protected static ?string \$table = '{$table}';

    /** 主键 */
    protected static string \$primaryKey = 'id';

    /** 允许批量赋值的字段 */
    protected static array \$fillable = [];

    /** 关联定义：'关系名' => \$this->hasMany(...) */
    public function relations(): array
    {
        return [];
    }
}

PHP;

        return $this->writeFile($file, $content, $this->force()) ? 0 : 1;
    }
}
