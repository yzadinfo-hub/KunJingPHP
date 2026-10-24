<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：字段定义（净室实现，供迁移 Blueprint 使用）
 */

namespace KunJing\Framework\Database\Schema;

class Column
{
    public string $name;
    public string $type;
    public ?int $length = null;
    public ?int $scale = null;
    public bool $nullable = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public bool $unsigned = false;
    public bool $unique = false;
    public bool $index = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public ?string $comment = null;

    public function __construct(string $name, string $type, ?int $length = null, ?int $scale = null)
    {
        $this->name = $name;
        $this->type = strtoupper($type);
        $this->length = $length;
        $this->scale = $scale;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function index(bool $value = true): self
    {
        $this->index = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function toSql(): string
    {
        $sql = '`' . $this->name . '` ' . $this->type;

        if ($this->length !== null && !in_array($this->type, ['TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'DATE', 'DATETIME', 'TIME', 'TIMESTAMP', 'JSON'], true)) {
            $sql .= '(' . $this->length . ($this->scale !== null ? ',' . $this->scale : '') . ')';
        }
        if ($this->unsigned) {
            $sql .= ' UNSIGNED';
        }
        if ($this->type !== 'TIMESTAMP') {
            $sql .= $this->nullable ? ' NULL' : ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }
        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }
        if ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->quoteDefault($this->default);
        } elseif ($this->nullable && $this->type === 'TIMESTAMP') {
            $sql .= ' DEFAULT NULL';
        }
        if ($this->comment !== null && $this->comment !== '') {
            $sql .= " COMMENT '" . str_replace("'", "\\'", $this->comment) . "'";
        }
        return $sql;
    }

    protected function quoteDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . str_replace("'", "\\'", (string) $value) . "'";
    }
}
