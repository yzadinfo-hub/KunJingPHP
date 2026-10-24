<?php
/**
 * 永州堃景科技 · 企业官网 — 路由定义
 */

use KunJing\Framework\Http\Response;
use Site\Controllers\SiteController;

$router->get('/', [SiteController::class, 'home']);        // 首页
$router->get('/about', [SiteController::class, 'about']);       // 关于我们
$router->get('/services', [SiteController::class, 'services']); // 主营业务
$router->get('/products', [SiteController::class, 'products']); // 产品源码
$router->get('/cases', [SiteController::class, 'cases']);       // 案例展示
$router->get('/contact', [SiteController::class, 'contact']);   // 联系我们（表单页）
$router->post('/contact', [SiteController::class, 'contactPost']); // 提交留言
