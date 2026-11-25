<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：SQL 查询构造器（净室实现，能力对标 ThinkPHP Query）
 *
 * 设计要点：
 *  - 所有用户输入经占位符 ? 参数绑定，杜绝 SQL 注入；
 *  - 链式调用，最终由 Connection 执行；
 *  - 语法兼容 MySQL / SQLite（反引号包裹、LIMIT 一致）。
 */

namespace KunJing\Framework\Database\Query;

use KunJing\Framework\Database\Connection;
use KunJing\Framework\Exception\DbException;

class Builder
{
    protected Connection $connection;
    protected string $from = '';
    protected string $alias = '';
    /** @var array<int,string> */
    protected array $columns = ['*'];
    protected bool $distinct = false;
    /** @var array<int,array{bool:string,sql:string,bindings:array}> */
    protected array $wheres = [];
    /** @var array<int,string> */
    protected array $orders = [];
    /** @var array<int,string> */
    protected array $groups = [];
    /** @var array<int,array{sql:string,bindings:array}> */
    protected array $havings = [];
    /** @var array<int,array{table:string,first:string,op:?string,second:?string,type:string}> */
    protected array $joins = [];
    protected int $limitValue = 0;
    protected int $offsetValue = 0;
    protected ?string $modelClass = null;
    /** @var array<int,string> */
    protected array $eagerLoads = [];

    /**
     * 全局表前缀（由 App 在启动时按 db.prefix 配置注入）。
     * 应用到 wrapTable()，让裸表名自动加上前缀（已含前缀的跳过）。
     */
    private static string $tablePrefix = '';

    /** 由框架 App / 管理器在连接就绪时调用。 */
    public static function setTablePrefix(string $prefix): void
    {
        self::$tablePrefix = $prefix;
    }

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * 绑定模型类，使 get()/first()/find() 返回模型实例而非裸数组。
     */
    public function setModel(string $class): static
    {
        $this->modelClass = $class;
        return $this;
    }

    /**
     * 声明预加载的关联关系名。
     * @param string|array<int,string> $relations
     */
    public function with($relations): static
    {
        $this->eagerLoads = array_merge($this->eagerLoads, (array) $relations);
        return $this;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /* ---------- 基础片段 ---------- */

    public function table(string $table, string $alias = ''): static
    {
        $this->from = $table;
        $this->alias = $alias;
        return $this;
    }

    /**
     * @param string|array<int,string>|Expression $columns
     */
    public function select($columns = '*'): static
    {
        if ($columns instanceof Expression) {
            $this->columns = [$columns->getValue()];
        } elseif (is_array($columns)) {
            $this->columns = $columns;
        } else {
            $this->columns = explode(',', $columns);
        }
        return $this;
    }

    public function distinct(bool $flag = true): static
    {
        $this->distinct = $flag;
        return $this;
    }

    /* ---------- WHERE ---------- */

    /**
     * 多种形态：
     *  where('col', 'val')              => col = ?
     *  where('col', '>', $val)          => col > ?
     *  where('col', 'in', [1,2])        => col IN (?,?)
     *  where(['col','=',val], ...)      => 数组条件
     *  where('col', null)               => col IS NULL
     */
    public function where($column, $operator = null, $value = null): static
    {
        if (is_array($column)) {
            foreach ($column as $cond) {
                if (is_array($cond)) {
                    $this->where(...array_values($cond));
                }
            }
            return $this;
        }
        return $this->addWhere('AND', $column, $operator, $value);
    }

    public function orWhere($column, $operator = null, $value = null): static
    {
        return $this->addWhere('OR', $column, $operator, $value);
    }

    protected function addWhere(string $bool, $column, $operator, $value): static
    {
        // where('col', null) => IS NULL
        if ($operator === null && $value === null) {
            $this->wheres[] = ['bool' => $bool, 'sql' => $this->wrap($column) . ' IS NULL', 'bindings' => []];
            return $this;
        }
        // where('col', 'val') 缺省等号
        if ($value === null && $operator !== null && !is_array($operator) && !($operator instanceof Expression)) {
            // operator 实为值
            $value = $operator;
            $operator = '=';
        }
        $operator = strtoupper((string) $operator);

        if (is_array($value)) {
            $operator = $operator === '=' ? 'IN' : $operator;
            $placeholders = implode(',', array_fill(0, count($value), '?'));
            $this->wheres[] = [
                'bool' => $bool,
                'sql'  => $this->wrap($column) . " {$operator} ({$placeholders})",
                'bindings' => array_values($value),
            ];
            return $this;
        }

        if ($value instanceof Expression) {
            $this->wheres[] = [
                'bool' => $bool,
                'sql'  => $this->wrap($column) . " {$operator} " . $value->getValue(),
                'bindings' => [],
            ];
            return $this;
        }

        $this->wheres[] = [
            'bool' => $bool,
            'sql'  => $this->wrap($column) . " {$operator} ?",
            'bindings' => [$value],
        ];
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        return $this->addWhere('AND', $column, 'IN', $values);
    }

    public function whereNotIn(string $column, array $values): static
    {
        return $this->addWhere('AND', $column, 'NOT IN', $values);
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['bool' => 'AND', 'sql' => $this->wrap($column) . ' IS NULL', 'bindings' => []];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['bool' => 'AND', 'sql' => $this->wrap($column) . ' IS NOT NULL', 'bindings' => []];
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[] = ['bool' => 'AND', 'sql' => $sql, 'bindings' => $bindings];
        return $this;
    }

    /* ---------- JOIN ---------- */

    public function join(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    protected function addJoin(string $type, string $table, string $first, ?string $operator, ?string $second): static
    {
        // 支持原生：join('b', 'b.id = a.bid')
        if ($operator === null) {
            $this->joins[] = ['table' => $table, 'first' => $first, 'op' => null, 'second' => null, 'type' => $type];
        } else {
            $this->joins[] = [
                'table' => $table,
                'first' => $this->wrap($first),
                'op'    => $operator,
                'second'=> $this->wrap((string) $second),
                'type'  => $type,
            ];
        }
        return $this;
    }

    /* ---------- 排序 / 分组 ---------- */

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->wrap($column) . ' ' . $direction;
        return $this;
    }

    public function order(string $column, string $direction = 'ASC'): static
    {
        return $this->orderBy($column, $direction);
    }

    public function groupBy(string $column): static
    {
        $this->groups[] = $this->wrap($column);
        return $this;
    }

    public function group(string $column): static
    {
        return $this->groupBy($column);
    }

    public function having(string $column, string $operator, $value): static
    {
        $this->havings[] = ['sql' => $this->wrap($column) . " {$operator} ?", 'bindings' => [$value]];
        return $this;
    }

    /* ---------- 分页 ---------- */

    public function limit(int $value): static
    {
        $this->limitValue = max(0, $value);
        return $this;
    }

    public function offset(int $value): static
    {
        $this->offsetValue = max(0, $value);
        return $this;
    }

    public function page(int $page, int $perPage): static
    {
        $page = max(1, $page);
        $this->limitValue = $perPage;
        $this->offsetValue = ($page - 1) * $perPage;
        return $this;
    }

    /* ---------- 写操作 ---------- */

    /**
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $this->wrapTable($this->from) . ' ('
            . implode(', ', array_map([$this, 'wrap'], $columns))
            . ') VALUES (' . $placeholders . ')';
        return $this->connection->statement($sql, array_values($data));
    }

    /**
     * @param array<string,mixed> $data
     * @return int 自增主键
     */
    public function insertGetId(array $data): int
    {
        $this->insert($data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        $sets = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            if ($val instanceof Expression) {
                $sets[] = $this->wrap($col) . ' = ' . $val->getValue();
            } else {
                $sets[] = $this->wrap($col) . ' = ?';
                $bindings[] = $val;
            }
        }
        $sql = 'UPDATE ' . $this->wrapTable($this->from) . ' SET ' . implode(', ', $sets);
        $sql .= $this->compileWheres();
        return $this->connection->statement($sql, array_merge($bindings, $this->whereBindings()));
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->wrapTable($this->from);
        $sql .= $this->compileWheres();
        return $this->connection->statement($sql, $this->whereBindings());
    }

    /* ---------- 聚合 ---------- */

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    protected function aggregate(string $func, string $column): mixed
    {
        $col = $column === '*' ? '*' : $this->wrap($column);
        $sql = 'SELECT ' . $func . '(' . $col . ') AS agg FROM ' . $this->compileFrom();
        $sql .= $this->compileJoins() . $this->compileWheres() . $this->compileGroups() . $this->compileHavings();
        $row = $this->connection->selectOne($sql, $this->compileBindings());
        return $row['agg'] ?? null;
    }

    /* ---------- 读取 ---------- */

    /**
     * @return array<int,array<string,mixed>>
     */
    public function get(): array
    {
        $sql = $this->toSql();
        $rows = $this->connection->select($sql, $this->compileBindings());
        if ($this->modelClass === null) {
            return $rows;
        }
        $models = array_map(fn($row) => $this->modelClass::newFromDb($row), $rows);
        if (!empty($this->eagerLoads)) {
            call_user_func([$this->modelClass, 'eagerLoad'], $models, $this->eagerLoads);
        }
        return $models;
    }

    /**
     * @return mixed 裸数组或模型实例或 null
     */
    public function first(): mixed
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /**
     * 按主键查找单行。
     */
    public function find($id, string $pk = 'id')
    {
        return $this->where($pk, '=', $id)->first();
    }

    public function value(string $column)
    {
        $row = $this->first();
        return $row[$column] ?? null;
    }

    /**
     * @return array<int,mixed>
     */
    public function column(string $column): array
    {
        $rows = $this->get();
        return array_map(function ($r) use ($column) {
            if (is_array($r)) {
                return $r[$column] ?? null;
            }
            return method_exists($r, 'getAttribute') ? $r->getAttribute($column) : null;
        }, $rows);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 分页：返回 ['total'=>int, 'per_page'=>int, 'current_page'=>int, 'data'=>array]
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $total = $this->cloneWithoutLimit()->count();
        $this->page($page, $perPage);
        return [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
            'data'         => $this->get(),
        ];
    }

    /* ---------- 拼装 SQL ---------- */

    public function toSql(): string
    {
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '')
            . implode(', ', array_map([$this, 'wrapColumnAlias'], $this->columns))
            . ' FROM ' . $this->compileFrom();
        $sql .= $this->compileJoins();
        $sql .= $this->compileWheres();
        $sql .= $this->compileGroups();
        $sql .= $this->compileHavings();
        $sql .= $this->compileOrders();
        $sql .= $this->compileLimit();
        return $sql;
    }

    protected function compileFrom(): string
    {
        $from = $this->wrapTable($this->from);
        if ($this->alias !== '') {
            $from .= ' AS ' . $this->wrap($this->alias);
        }
        return $from;
    }

    protected function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }
        $sql = '';
        foreach ($this->joins as $j) {
            $sql .= " {$j['type']} JOIN " . $this->wrapTable($j['table']);
            if ($j['op'] === null) {
                $sql .= ' ON ' . $j['first'];
            } else {
                $sql .= ' ON ' . $j['first'] . ' ' . $j['op'] . ' ' . $j['second'];
            }
        }
        return $sql;
    }

    protected function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => $w) {
            $sql .= ($i === 0 ? ' WHERE ' : ' ' . $w['bool'] . ' ') . $w['sql'];
        }
        return $sql;
    }

    protected function compileGroups(): string
    {
        return empty($this->groups) ? '' : ' GROUP BY ' . implode(', ', $this->groups);
    }

    protected function compileHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }
        $sql = '';
        foreach ($this->havings as $i => $h) {
            $sql .= ($i === 0 ? ' HAVING ' : ' AND ') . $h['sql'];
        }
        return $sql;
    }

    protected function compileOrders(): string
    {
        return empty($this->orders) ? '' : ' ORDER BY ' . implode(', ', $this->orders);
    }

    protected function compileLimit(): string
    {
        if ($this->limitValue <= 0) {
            return '';
        }
        $sql = ' LIMIT ' . $this->limitValue;
        if ($this->offsetValue > 0) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }
        return $sql;
    }

    /**
     * 收集全部绑定（按拼装顺序：where → having）。
     * @return array<int,mixed>
     */
    protected function compileBindings(): array
    {
        $bindings = $this->whereBindings();
        foreach ($this->havings as $h) {
            $bindings = array_merge($bindings, $h['bindings']);
        }
        return $bindings;
    }

    /**
     * @return array<int,mixed>
     */
    protected function whereBindings(): array
    {
        $bindings = [];
        foreach ($this->wheres as $w) {
            $bindings = array_merge($bindings, $w['bindings']);
        }
        return $bindings;
    }

    protected function cloneWithoutLimit(): static
    {
        $clone = clone $this;
        $clone->limitValue = 0;
        $clone->offsetValue = 0;
        return $clone;
    }

    /* ---------- 标识符包裹 ---------- */

    public function wrap(string $column): string
    {
        if ($column === '*' || str_contains($column, '(') || str_contains($column, ' ')) {
            return $column;
        }
        // table.* 形式：只包裹表名，通配符保持原样
        if (str_ends_with($column, '.*')) {
            return $this->wrap(substr($column, 0, -2)) . '.*';
        }
        if (str_contains($column, '.')) {
            return implode('.', array_map([$this, 'wrapOne'], explode('.', $column)));
        }
        if (str_starts_with($column, '`')) {
            return $column;
        }
        return $this->wrapOne($column);
    }

    protected function wrapOne(string $ident): string
    {
        return '`' . str_replace('`', '', $ident) . '`';
    }

    protected function wrapTable(string $table): string
    {
        // 应用全局表前缀：仅当表名尚未以该前缀开头时拼接（避免双重前缀）
        if (self::$tablePrefix !== '' && strpos($table, self::$tablePrefix) !== 0) {
            $table = self::$tablePrefix . $table;
        }
        return $this->wrap($table);
    }

    protected function wrapColumnAlias(string $col): string
    {
        // 支持 "col as alias"
        if (preg_match('/\s+as\s+/i', $col)) {
            [$c, $alias] = preg_split('/\s+as\s+/i', $col, 2);
            return $this->wrap(trim($c)) . ' AS ' . $this->wrapOne(trim($alias));
        }
        return $this->wrap($col);
    }
}
