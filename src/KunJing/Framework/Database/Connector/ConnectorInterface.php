<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：数据库连接器接口（净室实现，能力对标 ThinkPHP Db 连接管理）
 * 各数据库驱动实现此接口，返回原生 PDO 实例。
 */

namespace KunJing\Framework\Database\Connector;

interface ConnectorInterface
{
    /**
     * 根据配置建立并返回 PDO 连接。
     * @param array<string, mixed> $config
     */
    public function connect(array $config): \PDO;
}
