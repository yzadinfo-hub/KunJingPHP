<?php
/**
 * 组梦ERP（小模块迁移样例）— 路由定义
 */

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use Erp\Controllers\AuthController;
use Erp\Controllers\SupplierController;
use Erp\Controllers\GoodsController;
use Erp\Middleware\AuthMiddleware;

// ---- 首页 ----
$router->get('/', function () {
    return Response::redirect('/supplier');
});

// ---- 登录 / 登出（无需鉴权） ----
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// ---- 供应商模块（需登录） ----
$router->group(['prefix' => 'supplier', 'middleware' => AuthMiddleware::class], function () use ($router) {
    $router->get('', [SupplierController::class, 'index']);
    $router->get('/create', [SupplierController::class, 'create']);
    $router->post('/store', [SupplierController::class, 'store']);
    $router->get('/edit/{id}', [SupplierController::class, 'edit'])->where(['id' => '\d+']);
    $router->post('/update/{id}', [SupplierController::class, 'update'])->where(['id' => '\d+']);
    $router->get('/delete/{id}', [SupplierController::class, 'destroy'])->where(['id' => '\d+']);
});

// ---- 商品模块（需登录） ----
$router->group(['prefix' => 'goods', 'middleware' => AuthMiddleware::class], function () use ($router) {
    $router->get('', [GoodsController::class, 'index']);
    $router->post('/store', [GoodsController::class, 'store']);
    $router->get('/delete/{id}', [GoodsController::class, 'destroy'])->where(['id' => '\d+']);
});

// ---- JSON API（无需登录，便于外部对接） ----
$router->get('/api/suppliers', function (Request $request) {
    $list = \Erp\Models\Supplier::query()
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();
    return Response::json(array_map(fn($s) => $s->toArray(), $list));
});
