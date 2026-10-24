<?php
/**
 * KunJingPHP (堃景) — 手动引导脚本
 * 在不执行 composer install 的情况下，也能运行 demo 与测试。
 * 正式项目请改用 composer 的 PSR-4 自动加载。
 */

require __DIR__ . '/src/KunJing/Framework/Support/Autoload.php';

\KunJing\Framework\Support\Autoload::addNamespace('KunJing\\Framework', __DIR__ . '/src/KunJing/Framework');
if (is_dir(__DIR__ . '/demo')) {
    \KunJing\Framework\Support\Autoload::addNamespace('App', __DIR__ . '/demo');
}
\KunJing\Framework\Support\Autoload::register();
