<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：数据库管理器 / Db 入口（净室实现，能力对标 ThinkPHP Db 门面后端）
 *
 * 用法：
 *  KunJing\Framework\Database\Manager::instance()->table('user')->where('id',1)->first();
 *  或通过门面 KunJing\Framework\Facade\Db::table('user')...
 */

namespace KunJing\Framework\Database;

use KunJing\Framework\Database\Query\Builder;

class Manager
{
    protected ConnectionManager $connections;
    protected string $default = 'default';

    protected static ?Manager $instance = null;

    public function __construct(?ConnectionManager $connections = null)
    {
        $this->connections = $connections ?? new ConnectionManager();
    }

    public function getConnectionManager(): ConnectionManager
    {
        return $this->connections;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->default = $name;
    }

    public function connection(string $name = ''): Connection
    {
        return $this->connections->connection($name === '' ? $this->default : $name);
    }

    /**
     * 返回针对某表的查询构造器。
     */
    public function table(string $table, string $alias = ''): Builder
    {
        return (new Builder($this->connection()))->table($table, $alias);
    }

    /**
     * 事务：在默认连接上执行。
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback)
    {
        return $this->connection()->transaction($callback);
    }

    /* ---------- 静态单例（供 Model 与门面使用） ---------- */

    public static function instance(): Manager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?Manager $manager): void
    {
        self::$instance = $manager;
    }
}
