<?php
/**
 * KunJingPHP demo 入口
 * 运行：php -S 127.0.0.1:8000 demo/index.php
 */

require __DIR__ . '/../bootstrap.php';

use KunJing\Framework\App;

$app = new App(__DIR__);
$app->config()->set('app.debug', true);

$router = $app->router();
require __DIR__ . '/routes.php';

$app->run();
