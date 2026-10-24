<?php
/**
 * 组梦ERP 迁移样例 — 备份工具（重点：迁移/改造前必须先备份）
 *
 * 用法：
 *   php erp_demo/backup.php               # 备份数据库 + 源码，保留最近 10 份
 *   php erp_demo/backup.php --keep=5      # 保留最近 5 份
 *   php erp_demo/backup.php --only=db     # 仅数据库
 *   php erp_demo/backup.php --only=code   # 仅源码
 *   php erp_demo/backup.php --list        # 列出已有备份
 *
 * 设计说明：
 *  - 数据库导出采用「纯 PHP 实现」，不依赖 mysqldump（Windows 无该命令也能跑）；
 *  - 源码备份采用「递归目录复制」，不依赖 tar（本机 tar.exe 会被安全策略拦截）；
 *  - 每份备份生成 manifest.json，记录时间、体积、文件数，便于回滚核对。
 */
if (php_sapi_name() !== 'cli') {
    exit('请在命令行执行备份脚本。' . PHP_EOL);
}

$basePath = __DIR__;
$backupDir = $basePath . '/backups';
$stamp = date('Ymd_His');

$optOnly = null; $keep = 10; $list = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--only=')) { $optOnly = substr($arg, 7); }
    if (str_starts_with($arg, '--keep=')) { $keep = (int) substr($arg, 7); }
    if ($arg === '--list') { $list = true; }
}

if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }

/* ---------- 列出备份 ---------- */
if ($list) {
    $files = glob($backupDir . '/*.json') ?: [];
    rsort($files);
    echo "== 已有备份（共 " . count($files) . " 份） ==\n";
    foreach ($files as $f) {
        $info = json_decode(file_get_contents($f), true);
        if (!$info) { continue; }
        printf(
            "%s  代码:%s(%d文件)  数据库:%s(%s)\n",
            $info['stamp'],
            $info['code']['dir'] ?? '(未备份)',
            $info['code']['files'] ?? 0,
            basename($info['db']['file'] ?? '(未备份)'),
            $info['db']['size'] ?? '-'
        );
    }
    exit(0);
}

$config = require $basePath . '/config.php';
$db = $config['db']['connections']['mysql'];

echo "== 开始备份 " . $stamp . " ==\n";

$manifest = [
    'stamp' => $stamp,
    'time'  => date('c'),
    'app'   => $config['app']['name'],
    'db'    => null,
    'code'  => null,
];

/* ---------- 1) 数据库导出 ---------- */
if ($optOnly === null || $optOnly === 'db') {
    echo "[1/2] 导出数据库 `{$db['database']}` ...\n";
    try {
        $pdo = new PDO(
            "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}",
            $db['username'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );

        $sqlFile = $backupDir . '/db_' . $stamp . '.sql';
        $fh = fopen($sqlFile, 'w');
        fwrite($fh, "-- 组梦ERP 迁移样例 数据库备份\n");
        fwrite($fh, "-- 生成时间: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fh, "-- 数据库: {$db['database']}\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $create['Create Table'] ?? null;
            fwrite($fh, "-- ----------------------------\n");
            fwrite($fh, "-- 表结构: $table\n");
            fwrite($fh, "-- ----------------------------\n");
            fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($fh, $createSql . ";\n\n");

            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            fwrite($fh, "-- 表数据: $table (" . count($rows) . " 行)\n");
            foreach ($rows as $row) {
                $cols = array_map(fn($c) => "`$c`", array_keys($row));
                $vals = array_map(function ($v) use ($pdo) {
                    if ($v === null) { return 'NULL'; }
                    return $pdo->quote((string) $v);
                }, array_values($row));
                fwrite($fh, "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        $size = filesize($sqlFile);
        $manifest['db'] = [
            'file'  => $sqlFile,
            'size'  => round($size / 1024, 1) . ' KB',
            'tables'=> count($tables),
        ];
        echo "[OK] 数据库已导出: " . basename($sqlFile) . " (" . $manifest['db']['size'] . ", " . count($tables) . " 张表)\n";
    } catch (Throwable $e) {
        echo "[失败] 数据库导出异常: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/* ---------- 2) 源码备份 ---------- */
if ($optOnly === null || $optOnly === 'code') {
    echo "[2/2] 备份源码目录 ...\n";
    $target = $backupDir . '/code_' . $stamp;
    $sourceDir = $basePath;
    $excludeDirs = ['backups', 'storage', '.git', 'node_modules', 'vendor'];

    $copied = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            function ($current) use ($excludeDirs, $sourceDir) {
                $relative = ltrim(str_replace($sourceDir, '', $current->getPathname()), DIRECTORY_SEPARATOR);
                foreach ($excludeDirs as $ex) {
                    if ($relative === $ex || str_starts_with($relative, $ex . DIRECTORY_SEPARATOR)) {
                        return false;
                    }
                }
                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        $relative = ltrim(str_replace($sourceDir, '', $item->getPathname()), DIRECTORY_SEPARATOR);
        if ($relative === '') { continue; }
        $dest = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($dest)) { mkdir($dest, 0755, true); }
        } else {
            if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0755, true); }
            copy($item->getPathname(), $dest);
            $copied++;
        }
    }

    $manifest['code'] = ['dir' => basename($target), 'files' => $copied];
    echo "[OK] 源码已备份: " . basename($target) . " (" . $copied . " 个文件)\n";
}

/* ---------- 3) 写入清单 + 清理旧备份 ---------- */
file_put_contents(
    $backupDir . '/manifest_' . $stamp . '.json',
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

if ($keep > 0) {
    $all = glob($backupDir . '/manifest_*.json') ?: [];
    rsort($all);
    foreach (array_slice($all, $keep) as $old) {
        $info = json_decode(file_get_contents($old), true);
        if (isset($info['db']['file']) && is_file($info['db']['file'])) { @unlink($info['db']['file']); }
        if (isset($info['code']['dir'])) {
            $dir = $backupDir . '/' . $info['code']['dir'];
            if (is_dir($dir)) { recursiveDelete($dir); }
        }
        @unlink($old);
    }
}

echo "\n备份完成 -> " . $backupDir . "\n";
echo "提示：正式迁移前请确认备份可用，并先执行一次还原演练。\n";
exit(0);

/**
 * 递归删除目录（内部使用）。
 */
function recursiveDelete(string $dir): void
{
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) { recursiveDelete($path); } else { @unlink($path); }
    }
    @rmdir($dir);
}
