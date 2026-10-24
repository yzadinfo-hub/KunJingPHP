<?php
/**
 * KunJingPHP demo 路由定义
 * 该文件在 demo/index.php 内被 require，可直接使用已定义的 $router 变量。
 */

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use App\Controllers\DemoController;

// 1) 闭包路由
$router->get('/', function () {
    return '<h1>KunJingPHP 启动成功</h1><p>堃景框架 Phase 1 验证通过。</p>';
});

// 2) 参数路由 + 依赖注入 Request
$router->get('/hello/{name}', function (Request $request) {
    $name = $request->param('name', 'world');
    return Response::json(['hello' => $name, 'framework' => 'KunJingPHP']);
});

// 3) 正则约束参数路由
$router->get('/user/{id}', function (Request $request) {
    return Response::json(['user_id' => (int) $request->param('id')]);
})->where(['id' => '\d+']);

// 4) 中间件（鉴权）示例
$router->get('/secure', function () {
    return 'secure ok';
})->middleware(\App\Middleware\AuthMiddleware::class);

// 5) 控制器路由（带命名空间）
$router->get('/demo', [DemoController::class, 'index']);

// 6) 路由分组示例
$router->group(['prefix' => 'api', 'middleware' => []], function () use ($router) {
    $router->get('/status', function () {
        return Response::json(['status' => 'ok', 'time' => date('c')]);
    });
});
