<?php
/**
 * 组梦ERP 迁移样例 — 安装/初始化脚本
 * 用法：php erp_demo/install.php
 */
if (php_sapi_name() !== 'cli') {
    exit('请在命令行执行：php erp_demo/install.php' . PHP_EOL);
}

$config = require __DIR__ . '/config.php';
$db = $config['db']['connections']['mysql'];

echo "== 组梦ERP（堃景框架迁移样例）安装 ==\n";

try {
    // 1) 建库
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}",
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] 数据库 `{$db['database']}` 已就绪\n";

    $pdo->exec("USE `{$db['database']}`");

    // 2) 建表
    $pdo->exec("DROP TABLE IF EXISTS `goods`");
    $pdo->exec("DROP TABLE IF EXISTS `supplier`");
    $pdo->exec("CREATE TABLE `supplier` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL COMMENT '供应商名称',
        `contact` VARCHAR(50) DEFAULT NULL COMMENT '联系人',
        `phone` VARCHAR(30) DEFAULT NULL COMMENT '联系电话',
        `address` VARCHAR(200) DEFAULT NULL COMMENT '地址',
        `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='供应商表'");

    $pdo->exec("CREATE TABLE `goods` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `supplier_id` INT NOT NULL COMMENT '所属供应商',
        `name` VARCHAR(120) NOT NULL COMMENT '商品名称',
        `sku` VARCHAR(60) NOT NULL COMMENT 'SKU编码',
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '价格',
        `stock` INT NOT NULL DEFAULT 0 COMMENT '库存',
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        INDEX `idx_supplier` (`supplier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表'");
    echo "[OK] 数据表 supplier / goods 已创建\n";

    // 3) 种子数据
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "INSERT INTO `supplier` (`name`,`contact`,`phone`,`address`,`status`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?)"
    );
    $suppliers = [
        ['永州电子科技有限公司', '陈工', '13800001111', '湖南永州冷水滩区', 1],
        ['长沙达丰供应链公司',   '李经理', '0731-8899001', '湖南长沙雨花区', 1],
        ['深圳前沿五金制品厂',   '王厂长', '0755-22334455', '广东深圳宝安区', 1],
        ['衡阳云仓物流有限公司', '赵主管', '0734-6668888', '湖南衡阳蒸湘区', 0],
    ];
    foreach ($suppliers as $s) {
        $stmt->execute([...$s, $now, $now]);
    }
    echo "[OK] 已写入 " . count($suppliers) . " 条供应商示例数据\n";

    $stmt = $pdo->prepare(
        "INSERT INTO `goods` (`supplier_id`,`name`,`sku`,`price`,`stock`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?)"
    );
    $goods = [
        [1, 'Type-C 数据线 1m',  'SKU-1001', 19.90, 500],
        [1, '65W 氮化镓充电器',  'SKU-1002', 89.00, 220],
        [2, 'A4 复印纸 70g',     'SKU-2001', 22.50, 1200],
        [3, '不锈钢六角螺丝 M6', 'SKU-3001', 0.85,  8000],
    ];
    foreach ($goods as $g) {
        $stmt->execute([...$g, $now, $now]);
    }
    echo "[OK] 已写入 " . count($goods) . " 条商品示例数据\n";

    // 4) 目录准备
    foreach ([__DIR__ . '/storage/cache', __DIR__ . '/storage/logs', __DIR__ . '/storage/session', __DIR__ . '/storage/views', __DIR__ . '/backups'] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    echo "[OK] storage / backups 目录已准备\n";

    echo "\n安装完成！启动方式：\n";
    echo "  php -S 127.0.0.1:8800 -t erp_demo\n";
    echo "  浏览器访问 http://127.0.0.1:8800  (账号 admin / 123456)\n";
    exit(0);
} catch (Throwable $e) {
    echo "[失败] " . $e->getMessage() . "\n";
    exit(1);
}
