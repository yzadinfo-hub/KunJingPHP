<?php
/**
 * 永州堃景科技有限公司 · 企业官网
 * 基于自研框架 KunJingPHP（堃景PHP）构建
 *
 * 本地运行：
 *   php -S 127.0.0.1:8800 kunjingtech/index.php
 *
 * 初始化留言表（可选，联系表单默认回退文件存储）：
 *   php kunjingtech/install.php
 */

require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\App;
use KunJing\Framework\Support\Autoload;

// 注册应用命名空间 Site\ -> kunjingtech/app
Autoload::addNamespace('Site', __DIR__ . '/app');

$app = new App(__DIR__);
$config = require __DIR__ . '/config.php';
$app->config()->load($config);

// 路由
$router = $app->router();
require __DIR__ . '/routes.php';

// 若直接以 WEB 方式访问则运行；被测试包含时不自动运行
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $app->run();
}

return $app;
