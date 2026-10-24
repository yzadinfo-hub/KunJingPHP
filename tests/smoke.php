<?php
/**
 * KunJingPHP 冒烟测试（无需 PHPUnit，php tests/smoke.php 即可运行）
 * 验证 Phase 0 内核 + Phase 1 请求链路的核心能力。
 */

require __DIR__ . '/../bootstrap.php';

use KunJing\Framework\Container\Container;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Routing\Router;
use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Http\Middleware\Pipeline;
use KunJing\Framework\Support\Collection;
use KunJing\Framework\App;

$pass = 0;
$fail = 0;

function assert_ok(bool $cond, string $label): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS  $label\n";
    } else {
        $fail++;
        echo "FAIL  $label\n";
    }
}

/* ---------- Phase 0：内核 ---------- */

// 配置
$cfg = new Repository();
$cfg->set('app.debug', true);
$cfg->set('db.connections.mysql.host', '127.0.0.1');
assert_ok($cfg->get('app.debug') === true, 'Config 点号写入/读取');
assert_ok($cfg->get('db.connections.mysql.host') === '127.0.0.1', 'Config 多级点号路径');
assert_ok($cfg->has('app.debug') === true, 'Config has()');

// 容器依赖注入
class SmokeDep
{
    public string $v = 'dep';
}
class SmokeNeedsDep
{
    public SmokeDep $dep;
    public function __construct(SmokeDep $dep)
    {
        $this->dep = $dep;
    }
}
$container = Container::getInstance();
$obj = $container->make(SmokeNeedsDep::class);
assert_ok(isset($obj->dep) && $obj->dep instanceof SmokeDep, 'Container 反射构造注入');

$container->singleton('smoke.shared', fn() => new SmokeDep());
assert_ok($container->make('smoke.shared') === $container->make('smoke.shared'), 'Container 单例共享');

$injected = $container->call(function (SmokeDep $dep) {
    return $dep->v;
});
assert_ok($injected === 'dep', 'Container call 自动注入参数');

// 集合
$col = new Collection([1, 2, 3, 4]);
assert_ok($col->count() === 4, 'Collection count');
assert_ok(array_values($col->filter(fn($x) => $x % 2 === 0)->all()) === [2, 4], 'Collection filter');
assert_ok($col->map(fn($x) => $x * 2)->first() === 2, 'Collection map/first');

/* ---------- Phase 1：请求链路 ---------- */

// 路由匹配 + 参数提取
$router = new Router();
$router->get('/user/{id}', fn() => 'u')->where(['id' => '\d+']);
$reqList = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/user/42']);
$route = $router->dispatch($reqList);
assert_ok($route !== null, 'Router 匹配参数路由');
assert_ok($reqList->param('id') === '42', 'Router 提取路由参数');
assert_ok($route->matches('GET', '/user/abc', $reqList) === false, 'Router 正则约束生效');

// 中间件洋葱管道
$pipeline = new Pipeline();
$order = $pipeline->send('req')->through([
    fn($p, $next) => 'M1:' . $next($p),
    fn($p, $next) => 'M2:' . $next($p),
])->then(fn($p) => 'CORE');
assert_ok($order === 'M1:M2:CORE', 'Pipeline 洋葱顺序');

$short = (new Pipeline())->send('req')->through([
    fn($p, $next) => 'BLOCK',
])->then(fn($p) => 'CORE');
assert_ok($short === 'BLOCK', 'Pipeline 短路返回');

// App 引导 + 容器 call 注入 Request
$app = new App();
$app->router()->get('/t', fn(Request $r) => Response::json(['ok' => true]));
$tReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/t']);
$tRoute = $app->router()->dispatch($tReq);
assert_ok($tRoute !== null, 'App 路由分发');

$resp = $app->container()->call(function (Request $r) {
    return Response::json(['method' => $r->method()]);
});
assert_ok($resp instanceof Response, 'Container call 注入 Request 并返回 Response');

/* ---------- 汇总 ---------- */

echo "\n===================================\n";
echo "RESULT: {$pass} passed, {$fail} failed\n";
echo "===================================\n";

exit($fail === 0 ? 0 : 1);
