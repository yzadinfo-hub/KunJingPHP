<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：数据库连接（净室实现）
 * 封装 PDO，提供参数化查询、事务与统一异常。
 */

namespace KunJing\Framework\Database;

use KunJing\Framework\Exception\DbException;

class Connection
{
    protected \PDO $pdo;
    protected string $name;
    /** @var array<int,mixed> */
    protected array $transactions = [];

    public function __construct(\PDO $pdo, string $name = 'default')
    {
        $this->pdo = $pdo;
        $this->name = $name;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 执行 SELECT，返回多行。
     * @param array<int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->runQuery($sql, $bindings)->fetchAll();
    }

    /**
     * 执行 SELECT，返回首行或 null。
     * @param array<int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $rows = $this->select($sql, $bindings);
        return $rows[0] ?? null;
    }

    /**
     * 执行写操作（INSERT/UPDATE/DELETE），返回影响行数。
     * @param array<int,mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->runQuery($sql, $bindings)->rowCount();
    }

    /**
     * 原生查询（只读）。
     * @param array<int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        return $this->select($sql, $bindings);
    }

    /**
     * 原生执行（写）。返回影响行数。
     * @param array<int,mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings);
    }

    /**
     * 预处理并执行，返回 PDOStatement（统一异常）。
     * @param array<int,mixed> $bindings
     */
    protected function runQuery(string $sql, array $bindings = []): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($bindings));
            return $stmt;
        } catch (\PDOException $e) {
            throw new DbException('SQL 执行失败：' . $e->getMessage() . " | SQL: {$sql}", (int) $e->getCode(), $e);
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /* ---------- 事务 ---------- */

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * 在事务中执行闭包，自动提交/回滚。
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
