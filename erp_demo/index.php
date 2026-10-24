<?php
/**
 * 组梦ERP（小模块迁移样例）— 入口文件
 * 访问：php -S 127.0.0.1:8800 -t erp_demo
 */

require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\App;
use KunJing\Framework\Support\Autoload;

// 注册应用命名空间 Erp\ -> erp_demo/app
Autoload::addNamespace('Erp', __DIR__ . '/app');

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
