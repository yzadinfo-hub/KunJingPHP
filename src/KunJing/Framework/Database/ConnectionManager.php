<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：连接管理器（净室实现，能力对标 ThinkPHP 多连接管理）
 * 按配置名懒加载并缓存 PDO 连接；支持 mysql / sqlite / pgsql 驱动。
 */

namespace KunJing\Framework\Database;

use KunJing\Framework\Database\Connector\ConnectorInterface;
use KunJing\Framework\Database\Connector\MysqlConnector;
use KunJing\Framework\Exception\DbException;

class ConnectionManager
{
    /** @var array<string,Connection> */
    protected array $connections = [];
    /** @var array<string,array<string,mixed>> */
    protected array $configs = [];
    /** @var array<string,ConnectorInterface> */
    protected array $connectors = [];

    /**
     * @param array<string,array<string,mixed>> $configs
     */
    public function setConfigs(array $configs): void
    {
        $this->configs = $configs;
    }

    public function addConnector(string $driver, ConnectorInterface $connector): void
    {
        $this->connectors[$driver] = $connector;
    }

    /**
     * 取连接（懒加载 + 缓存）。
     */
    public function connection(string $name = 'default'): Connection
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        $config = $this->configs[$name] ?? $this->configs['default'] ?? null;
        if ($config === null) {
            throw new DbException("未配置数据库连接器 [{$name}]。");
        }
        $driver = $config['driver'] ?? 'mysql';
        $pdo = $this->makePdo($driver, $config);
        $this->connections[$name] = new Connection($pdo, $name);
        return $this->connections[$name];
    }

    protected function makePdo(string $driver, array $config): \PDO
    {
        if (isset($this->connectors[$driver])) {
            return $this->connectors[$driver]->connect($config);
        }
        return match ($driver) {
            'mysql'  => (new MysqlConnector())->connect($config),
            'sqlite' => $this->sqlitePdo($config),
            'pgsql'  => $this->pgsqlPdo($config),
            default  => throw new DbException("不支持的数据库驱动 [{$driver}]。"),
        };
    }

    protected function sqlitePdo(array $config): \PDO
    {
        $dsn = 'sqlite:' . ($config['database'] ?? ':memory:');
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $pdo;
    }

    protected function pgsqlPdo(array $config): \PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $dbname = $config['database'] ?? '';
        $dsn = "pgsql:host={$host};port={$port}" . ($dbname !== '' ? ";dbname={$dbname}" : '');
        $pdo = new \PDO($dsn, $config['username'] ?? 'postgres', $config['password'] ?? '');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $pdo;
    }

    public function purge(string $name = ''): void
    {
        if ($name === '') {
            $this->connections = [];
        } else {
            unset($this->connections[$name]);
        }
    }
}
