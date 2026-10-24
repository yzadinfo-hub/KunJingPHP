<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：建表 DSL（净室实现）
 *
 * 用法：
 *  $this->create('supplier', function (Blueprint $table) {
 *      $table->id();
 *      $table->string('name', 120)->comment('供应商名称');
 *      $table->string('phone', 30)->nullable();
 *      $table->decimal('credit', 10, 2)->default(0);
 *      $table->timestamps();
 *  });
 */

namespace KunJing\Framework\Database\Schema;

class Blueprint
{
    public string $table;
    /** @var array<int,Column> */
    protected array $columns = [];
    /** @var array<int,string> */
    protected array $indexes = [];
    protected string $engine = 'InnoDB';
    protected string $charset = 'utf8mb4';
    protected string $collation = 'utf8mb4_general_ci';

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /** @return array<int,Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function charset(string $charset, ?string $collation = null): self
    {
        $this->charset = $charset;
        if ($collation !== null) {
            $this->collation = $collation;
        }
        return $this;
    }

    /* ---------- 字段类型 ---------- */

    public function id(string $name = 'id'): Column
    {
        return $this->addColumn(new Column($name, 'BIGINT'))
            ->unsigned()
            ->autoIncrement()
            ->primary();
    }

    public function increments(string $name = 'id'): Column
    {
        return $this->addColumn(new Column($name, 'INT'))->unsigned()->autoIncrement()->primary();
    }

    public function bigIncrements(string $name = 'id'): Column
    {
        return $this->id($name);
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn(new Column($name, 'VARCHAR', $length));
    }

    public function char(string $name, int $length = 255): Column
    {
        return $this->addColumn(new Column($name, 'CHAR', $length));
    }

    public function text(string $name): Column
    {
        return $this->addColumn(new Column($name, 'TEXT'));
    }

    public function mediumText(string $name): Column
    {
        return $this->addColumn(new Column($name, 'MEDIUMTEXT'));
    }

    public function longText(string $name): Column
    {
        return $this->addColumn(new Column($name, 'LONGTEXT'));
    }

    public function integer(string $name, bool $unsigned = false): Column
    {
        $column = $this->addColumn(new Column($name, 'INT'));
        return $unsigned ? $column->unsigned() : $column;
    }

    public function bigInteger(string $name, bool $unsigned = false): Column
    {
        $column = $this->addColumn(new Column($name, 'BIGINT'));
        return $unsigned ? $column->unsigned() : $column;
    }

    public function smallInteger(string $name, bool $unsigned = false): Column
    {
        $column = $this->addColumn(new Column($name, 'SMALLINT'));
        return $unsigned ? $column->unsigned() : $column;
    }

    public function tinyInteger(string $name, bool $unsigned = false): Column
    {
        $column = $this->addColumn(new Column($name, 'TINYINT'));
        return $unsigned ? $column->unsigned() : $column;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        return $this->addColumn(new Column($name, 'DECIMAL', $precision, $scale));
    }

    public function float(string $name, int $precision = 10, int $scale = 2): Column
    {
        return $this->addColumn(new Column($name, 'FLOAT', $precision, $scale));
    }

    public function double(string $name, int $precision = 16, int $scale = 4): Column
    {
        return $this->addColumn(new Column($name, 'DOUBLE', $precision, $scale));
    }

    public function boolean(string $name): Column
    {
        return $this->addColumn(new Column($name, 'TINYINT', 1));
    }

    public function date(string $name): Column
    {
        return $this->addColumn(new Column($name, 'DATE'));
    }

    public function dateTime(string $name): Column
    {
        return $this->addColumn(new Column($name, 'DATETIME'));
    }

    public function time(string $name): Column
    {
        return $this->addColumn(new Column($name, 'TIME'));
    }

    public function timestamp(string $name, bool $nullable = true): Column
    {
        return $this->addColumn(new Column($name, 'TIMESTAMP'))->nullable($nullable);
    }

    public function json(string $name): Column
    {
        return $this->addColumn(new Column($name, 'JSON'));
    }

    /**
     * created_at / updated_at
     */
    public function timestamps(): self
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        return $this;
    }

    /**
     * 软删除字段 delete_time
     */
    public function softDeletes(string $name = 'delete_time'): Column
    {
        return $this->timestamp($name)->nullable();
    }

    /* ---------- 索引 ---------- */

    /** @param string|array<int,string> $columns */
    public function index($columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? 'idx_' . implode('_', $columns);
        $this->indexes[] = 'KEY `' . $name . '` (' . $this->wrapList($columns) . ')';
        return $this;
    }

    /** @param string|array<int,string> $columns */
    public function unique($columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? 'uk_' . implode('_', $columns);
        $this->indexes[] = 'UNIQUE KEY `' . $name . '` (' . $this->wrapList($columns) . ')';
        return $this;
    }

    /** @param string|array<int,string> $columns */
    public function primary($columns): self
    {
        $columns = (array) $columns;
        $this->indexes[] = 'PRIMARY KEY (' . $this->wrapList($columns) . ')';
        return $this;
    }

    /* ---------- 编译 ---------- */

    public function toCreateSql(): string
    {
        $lines = [];
        $indexes = $this->indexes; // 使用局部副本，避免重复调用时索引叠加

        foreach ($this->columns as $column) {
            $lines[] = '  ' . $column->toSql();
            if ($column->primary) {
                $indexes[] = 'PRIMARY KEY (`' . $column->name . '`)';
            }
            if ($column->unique) {
                $indexes[] = 'UNIQUE KEY `uk_' . $column->name . '` (`' . $column->name . '`)';
            } elseif ($column->index) {
                $indexes[] = 'KEY `idx_' . $column->name . '` (`' . $column->name . '`)';
            }
        }
        foreach ($indexes as $index) {
            $lines[] = '  ' . $index;
        }

        return 'CREATE TABLE `' . $this->table . '` (' . "\n"
            . implode(",\n", $lines) . "\n"
            . ') ENGINE=' . $this->engine . ' DEFAULT CHARSET=' . $this->charset
            . ' COLLATE=' . $this->collation;
    }

    public function toDropSql(): string
    {
        return 'DROP TABLE IF EXISTS `' . $this->table . '`';
    }

    /** @param array<int,string> $columns */
    protected function wrapList(array $columns): string
    {
        return implode(', ', array_map(fn($c) => '`' . $c . '`', $columns));
    }

    protected function addColumn(Column $column): Column
    {
        $this->columns[] = $column;
        return $column;
    }
}
