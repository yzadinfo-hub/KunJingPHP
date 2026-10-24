<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：MySQL 连接器（净室实现）
 * 基于 PDO 建立 MySQL 连接；零外部依赖，不复制任何框架源码。
 */

namespace KunJing\Framework\Database\Connector;

use KunJing\Framework\Exception\DbException;

class MysqlConnector implements ConnectorInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function connect(array $config): \PDO
    {
        $host    = $config['host']     ?? '127.0.0.1';
        $port    = $config['port']     ?? 3306;
        $dbname  = $config['database'] ?? '';
        $charset = $config['charset']  ?? 'utf8mb4';
        $user    = $config['username'] ?? $config['user'] ?? 'root';
        $pass    = $config['password'] ?? $config['pass'] ?? '';
        $dsn     = "mysql:host={$host};port={$port};charset={$charset}"
            . ($dbname !== '' ? ";dbname={$dbname}" : '');

        $options = [
            \PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_TIMEOUT          => (int) ($config['timeout'] ?? 5),
        ];

        try {
            return new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new DbException('MySQL 连接失败：' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
