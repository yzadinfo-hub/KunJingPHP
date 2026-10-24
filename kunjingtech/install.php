<?php
/**
 * 永州堃景科技 · 企业官网 — 安装脚本
 * 作用：创建留言表（contact_messages）。联系表单默认也支持文件存储回退，可不依赖本脚本。
 *
 *   php kunjingtech/install.php
 */

require_once __DIR__ . '/../bootstrap.php';
use KunJing\Framework\Database\Manager;

$config = require __DIR__ . '/config.php';
$db = $config['db']['connections'][$config['db']['default']];

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}",
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['database']}` CHARACTER SET utf8mb4");
    $pdo->exec("USE `{$db['database']}`");
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(60) NOT NULL DEFAULT '',
  `phone` VARCHAR(40) NOT NULL DEFAULT '',
  `email` VARCHAR(120) NOT NULL DEFAULT '',
  `type` VARCHAR(60) NOT NULL DEFAULT '',
  `content` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    );
    echo "OK：数据库 `{$db['database']}` 与表 `contact_messages` 已就绪。\n";
} catch (Throwable $e) {
    echo "ERR：" . $e->getMessage() . "\n";
}
