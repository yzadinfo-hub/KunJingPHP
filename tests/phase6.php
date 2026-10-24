<?php
/**
 * Phase 6 生产化能力测试
 * 覆盖：注解路由 / 路由缓存 / 资源路由 / 命名路由 / 上传 / FormRequest /
 *      分页器 / CORS·CSRF·限流中间件 / 迁移与回滚 / 代码生成器
 *
 * 运行：php tests/phase6.php
 */

require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\App;
use KunJing\Framework\Cache\CacheManager;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Console\Application;
use KunJing\Framework\Console\Commands\MakeControllerCommand;
use KunJing\Framework\Console\Commands\MakeMiddlewareCommand;
use KunJing\Framework\Console\Commands\MakeMigrationCommand;
use KunJing\Framework\Console\Commands\MakeModelCommand;
use KunJing\Framework\Console\Commands\MakeRequestCommand;
use KunJing\Framework\Console\Commands\MakeSeederCommand;
use KunJing\Framework\Console\Commands\MigrateCommand;
use KunJing\Framework\Console\Commands\MigrateRollbackCommand;
use KunJing\Framework\Console\Commands\MigrateStatusCommand;
use KunJing\Framework\Console\Commands\RouteCacheCommand;
use KunJing\Framework\Console\Commands\RouteClearCommand;
use KunJing\Framework\Console\Commands\RouteListCommand;
use KunJing\Framework\Console\Input;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Console\Output;
use KunJing\Framework\Database\ConnectionManager;
use KunJing\Framework\Database\Manager as DbManager;
use KunJing\Framework\Database\Migrations\Migrator;
use KunJing\Framework\Database\Schema\Blueprint;
use KunJing\Framework\Exception\HttpException;
use KunJing\Framework\Exception\ValidateException;
use KunJing\Framework\Facade\Session;
use KunJing\Framework\Http\Middleware\CorsMiddleware;
use KunJing\Framework\Http\Middleware\CsrfMiddleware;
use KunJing\Framework\Http\Middleware\ThrottleMiddleware;
use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Http\UploadedFile;
use KunJing\Framework\Routing\Router;
use KunJing\Framework\Support\Paginator;
use KunJing\Framework\Validate\FormRequest;

require_once __DIR__ . '/fixtures/Phase6AuthMiddleware.php';
require_once __DIR__ . '/fixtures/Phase6AttrController.php';
require_once __DIR__ . '/fixtures/Phase6Request.php';
require_once __DIR__ . '/fixtures/Phase6Controller.php';

$resultFile = __DIR__ . '/phase6_result.txt';
$fh = fopen($resultFile, 'w');
$pass = 0;
$fail = 0;

function assert_ok(bool $cond, string $label): void
{
    global $pass, $fail, $fh;
    if ($cond) {
        $pass++;
        fwrite($fh, "PASS  $label\n");
    } else {
        $fail++;
        fwrite($fh, "FAIL  $label\n");
    }
}

function note(string $text): void
{
    global $fh;
    fwrite($fh, "      · $text\n");
}

/* ---------- 环境准备 ---------- */

$basePath = dirname(__DIR__);
$app = new App($basePath);
$app->config()->set('app.debug', true);

$tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kj_p6_' . getmypid();
if (!is_dir($tmpRoot)) {
    @mkdir($tmpRoot, 0777, true);
}

/* =========================================================
   1. 注解路由
   ========================================================= */
fwrite($fh, "--- 1. 注解路由 ---\n");

$router = new Router();
$router->registerController(\P6\AttrController::class);
$routes = $router->getRoutes();
assert_ok(count($routes) === 4, '注解路由注册数量=4');

$uris = array_map(fn($r) => $r->getMethod() . ' ' . $r->getUri(), $routes);
assert_ok(in_array('GET /api/v1/users', $uris, true), '类级 #[Prefix] 生效');
assert_ok(in_array('POST /api/v1/users', $uris, true), '#[Post] 注册成功');
assert_ok(in_array('GET /api/v1/users/{id}', $uris, true), '参数路由注册成功');
assert_ok(in_array('DELETE /api/v1/users/{id}', $uris, true), "#[Route] 指定方法生效");

$hit = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/users']));
assert_ok($hit !== null, 'GET /api/v1/users 可匹配');

$hitShow = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/users/42']));
assert_ok($hitShow !== null && ($hitShow->getParams()['id'] ?? null) === '42', '参数提取 id=42');

$hitBad = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/users/abc']));
assert_ok($hitBad === null, '#[Where] 正则约束拦截非数字');

// 类级中间件继承
assert_ok($hit !== null && in_array(\P6\AuthMiddleware::class, $hit->getMiddleware(), true), '类级 #[Middleware] 继承到方法');

// 命名路由与 URL 生成
$destroy = $router->getByName('p6.user.destroy');
assert_ok($destroy !== null, '命名路由查找成功');
assert_ok($router->url('p6.user.destroy', ['id' => 7]) === '/api/v1/users/7', '命名路由生成 URL');

/* =========================================================
   2. 路由缓存
   ========================================================= */
fwrite($fh, "--- 2. 路由缓存 ---\n");

$cacheFile = $tmpRoot . '/routes.php';
$router->cacheFile($cacheFile);
assert_ok($router->dumpCache(), '路由缓存写入成功');
assert_ok(is_file($cacheFile), '缓存文件已生成');

$router2 = new Router();
$router2->cacheFile($cacheFile);
assert_ok($router2->loadFromCache(), '路由缓存加载成功');
assert_ok(count($router2->getRoutes()) === 4, '缓存后路由数量一致');

$hit2 = $router2->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/users']));
assert_ok($hit2 !== null, '缓存路由可正常匹配');
assert_ok($router2->getByName('p6.user.destroy') !== null, '缓存保留路由名称');

// boot()：有缓存时不再执行注册回调
$registerCalled = false;
$router2->boot(function () use (&$registerCalled) {
    $registerCalled = true;
});
assert_ok(!$registerCalled, 'boot() 命中缓存时不重复注册');
$router2->boot(function () use (&$registerCalled) {
    $registerCalled = true;
}, true);
assert_ok($registerCalled, 'boot(refresh) 强制重新注册');

assert_ok($router2->clearCache(), '路由缓存清理成功');
assert_ok(!is_file($cacheFile), '缓存文件已删除');

/* =========================================================
   3. 资源路由
   ========================================================= */
fwrite($fh, "--- 3. 资源路由 ---\n");

$router3 = new Router();
$resourceRoutes = $router3->resource('/goods', \P6\SupplierController::class);
assert_ok(count($resourceRoutes) === 7, 'resource() 生成 7 条路由');
assert_ok($router3->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/goods'])) !== null, 'GET /goods → index');
assert_ok($router3->dispatch(new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/goods'])) !== null, 'POST /goods → store');
assert_ok($router3->dispatch(new Request([], [], ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/goods/5'])) !== null, 'PUT /goods/5 → update');
assert_ok($router3->dispatch(new Request([], [], ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/goods/5'])) !== null, 'DELETE /goods/5 → destroy');
assert_ok($router3->url('goods.show', ['id' => 9]) === '/goods/9', '资源路由命名 URL');

/* =========================================================
   4. 请求增强与文件上传
   ========================================================= */
fwrite($fh, "--- 4. 请求与上传 ---\n");

$req = new Request(
    ['page' => '2'],
    ['name' => '堃景'],
    ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/upload?debug=1', 'REMOTE_ADDR' => '10.1.2.3',
     'HTTP_USER_AGENT' => 'KunJing-Test', 'HTTP_AUTHORIZATION' => 'Bearer abc123',
     'HTTP_ACCEPT' => 'application/json'],
    '{"a":1}'
);
assert_ok($req->ip() === '10.1.2.3', 'Request::ip()');
assert_ok($req->userAgent() === 'KunJing-Test', 'Request::userAgent()');
assert_ok($req->bearerToken() === 'abc123', 'Request::bearerToken()');
assert_ok($req->wantsJson(), 'Request::wantsJson() 识别 Accept');
assert_ok($req->isPost() && !$req->isGet(), 'Request::isPost()');
assert_ok($req->json()['a'] === 1, 'Request::json()');

$tmpUpload = $tmpRoot . '/src.txt';
file_put_contents($tmpUpload, 'hello');
$uploadReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/upload'], null, [
    'avatar' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $tmpUpload, 'error' => 0, 'size' => 5],
    'imgs'   => [
        'name'     => ['x.png', 'y.png'],
        'type'     => ['image/png', 'image/png'],
        'tmp_name' => [$tmpUpload, $tmpUpload],
        'error'    => [0, 0],
        'size'     => [5, 5],
    ],
]);
$file = $uploadReq->file('avatar');
assert_ok($file instanceof UploadedFile, 'Request::file() 返回 UploadedFile');
assert_ok($file->getClientOriginalName() === 'a.txt', '原始文件名正确');
assert_ok($uploadReq->hasFile('avatar'), 'hasFile() 为 true');
assert_ok($uploadReq->file('nope') === null, '未上传字段返回 null');

$multi = $uploadReq->file('imgs');
assert_ok(is_array($multi) && count($multi) === 2, '多文件解析为数组');

$target = $file->store($tmpRoot . '/uploads', 'saved.txt', false);
assert_ok(is_file($target), 'UploadedFile::store() 保存成功');
assert_ok(file_get_contents($target) === 'hello', '保存内容一致');
assert_ok($file->getStoredPath() === $target, '记录保存路径');

$badFile = new UploadedFile(['name' => 'x', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]);
assert_ok(!$badFile->isValid() && $badFile->getErrorMessage() === '没有文件被上传', '上传错误提示');

/* =========================================================
   5. FormRequest 表单校验
   ========================================================= */
fwrite($fh, "--- 5. FormRequest ---\n");

$okReq = new Request([], ['name' => '堃景科技', 'phone' => '13800138000'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/s']);
$form = \P6\SupplierRequest::createFrom($okReq);
assert_ok($form instanceof FormRequest, 'FormRequest 构造成功');
assert_ok($form->validated()['name'] === '堃景科技', 'validated() 返回通过校验的数据');
assert_ok(!isset($form->validated()['token']), 'validated() 仅含规则字段');

$badReq = new Request([], ['name' => 'x', 'phone' => ''], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/s']);
$thrown = null;
try {
    \P6\SupplierRequest::createFrom($badReq);
} catch (ValidateException $e) {
    $thrown = $e;
}
assert_ok($thrown !== null, '校验失败抛 ValidateException');
assert_ok($thrown !== null && $thrown->getCode() === 422, '异常状态码 422');
assert_ok($thrown !== null && isset($thrown->getErrors()['phone']), 'errors 包含具体字段');
assert_ok(
    $thrown !== null && str_contains($thrown->getErrors()['phone'] ?? '', '联系电话不能为空'),
    '字段别名 :attribute 替换生效'
);
if ($thrown !== null) {
    note('提示信息：' . $thrown->getMessage());
}

$denyReq = new Request([], ['name' => '堃景科技', 'phone' => '13800138000', 'token' => 'deny'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/s']);
$denied = null;
try {
    \P6\SupplierRequest::createFrom($denyReq);
} catch (HttpException $e) {
    $denied = $e;
}
assert_ok($denied !== null && $denied->getStatusCode() === 403, 'authorize() 失败抛 403');

/* =========================================================
   6. App 集成：FormRequest 注入 + 路由参数注入 + 422 JSON
   ========================================================= */
fwrite($fh, "--- 6. App 集成 ---\n");

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/p6/supplier';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_GET = [];
$_POST = ['name' => 'x', 'phone' => ''];

$app->router()->post('/p6/supplier', [\P6\SupplierController::class, 'store']);
$app->router()->get('/p6/supplier/{id}', [\P6\SupplierController::class, 'show']);
$app->router()->get('/p6/supplier/{id}', [\P6\SupplierController::class, 'show']); // 重复注册不影响首条命中

ob_start();
$app->run();
$out = (string) ob_get_clean();
assert_ok(str_contains($out, '"code":422') || str_contains($out, '"code": 422'), '校验失败返回 422 JSON');
assert_ok(str_contains($out, 'errors'), '422 JSON 包含 errors 明细');
note('响应片段：' . substr($out, 0, 90));

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/p6/supplier/88';
$_GET = [];
$_POST = [];
ob_start();
$app->run();
$out2 = (string) ob_get_clean();
assert_ok(str_contains($out2, '"id":"88"') || str_contains($out2, '"id": "88"'), '路由参数注入控制器方法');
note('参数注入响应：' . trim($out2));

/* =========================================================
   7. 分页器
   ========================================================= */
fwrite($fh, "--- 7. 分页器 ---\n");

$result = ['total' => 96, 'per_page' => 10, 'current_page' => 5, 'last_page' => 10, 'data' => range(1, 10)];
$pager = Paginator::make($result, '/supplier', ['keyword' => '堃景']);
assert_ok($pager->total() === 96 && $pager->lastPage() === 10, '总数与末页计算');
assert_ok($pager->firstItem() === 41 && $pager->lastItem() === 50, '当前页条目区间');
assert_ok($pager->hasMorePages() && !$pager->onFirstPage(), '翻页状态判定');
assert_ok(str_contains($pager->url(6), 'keyword=') && str_contains($pager->url(6), 'page=6'), 'url() 拼接查询串');
assert_ok(str_contains($pager->url(6), '/supplier'), 'url() 保留路径');

$html = $pager->render();
assert_ok(str_contains($html, 'kj-pagination'), 'render() 输出分页容器');
assert_ok(str_contains($html, '>5<') && str_contains($html, 'kj-active'), '当前页高亮');
assert_ok(str_contains($html, '共 96 条'), '总数文案');
assert_ok(str_contains($pager->previousUrl(), 'page=4'), '上一页链接');
assert_ok(str_contains((string) $pager->nextUrl(), 'page=6'), '下一页链接');

$toArray = $pager->toArray();
assert_ok($toArray['total'] === 96 && $toArray['current_page'] === 5 && count($toArray['data']) === 10, 'toArray() 结构');
assert_ok((new Paginator([], 5, 10, 1))->render() === '', '单页时不渲染分页条');

/* =========================================================
   8. 内置中间件
   ========================================================= */
fwrite($fh, "--- 8. 内置中间件 ---\n");

$cors = new CorsMiddleware(['allow_origin' => '*']);
$preflight = new Request([], [], ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/api/x']);
$resp = $cors->handle($preflight, fn($r) => Response::html('ok'));
assert_ok($resp instanceof Response && $resp->getStatus() === 204, 'CORS 预检返回 204');
assert_ok(($resp->getHeaders()['Access-Control-Allow-Origin'] ?? '') === '*', 'CORS 响应头已附加');

$normalReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/x']);
$resp = $cors->handle($normalReq, fn($r) => Response::html('ok'));
assert_ok($resp->getContent() === 'ok' && isset($resp->getHeaders()['Access-Control-Allow-Methods']), 'CORS 正常请求透传并加头');

// CSRF
Session::start();
$token = CsrfMiddleware::token();
assert_ok($token !== '' && strlen($token) === 32, 'CSRF 令牌生成');

$postOk = new Request([], ['_token' => $token], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/save']);
$passed = $corsPass = null;
try {
    $csrf = new CsrfMiddleware();
    $res = $csrf->handle($postOk, fn($r) => Response::html('saved'));
    $passed = $res instanceof Response ? $res->getContent() : null;
} catch (HttpException $e) {
    $passed = null;
}
assert_ok($passed === 'saved', 'CSRF 令牌正确时放行');

$postBad = new Request([], ['_token' => 'wrong'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/save']);
$caught = null;
try {
    (new CsrfMiddleware())->handle($postBad, fn($r) => Response::html('saved'));
} catch (HttpException $e) {
    $caught = $e;
}
assert_ok($caught !== null && $caught->getStatusCode() === 403, 'CSRF 令牌错误时拦截 403');

$getReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/save']);
assert_ok(((new CsrfMiddleware())->handle($getReq, fn($r) => Response::html('ok')))->getContent() === 'ok', 'GET 请求不做 CSRF 校验');
assert_ok(str_contains(CsrfMiddleware::field(), 'name="_token"'), 'CSRF 隐藏域 HTML');

// 限流
$cache = new CacheManager(['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]);
\KunJing\Framework\Container\Container::getInstance()->instance(CacheManager::class, $cache);

$throttleReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/limited', 'REMOTE_ADDR' => '203.0.113.' . rand(1, 250)]);
$throttle = new ThrottleMiddleware(2, 1);
$r1 = $throttle->handle($throttleReq, fn($r) => Response::html('ok'));
$r2 = $throttle->handle($throttleReq, fn($r) => Response::html('ok'));
$r3 = $throttle->handle($throttleReq, fn($r) => Response::html('ok'));
assert_ok($r1->getStatus() === 200 && $r2->getStatus() === 200, '限流内前两次放行');
assert_ok($r3 instanceof Response && $r3->getStatus() === 429, '超出阈值返回 429');

/* =========================================================
   9. 迁移与回滚（MySQL 实库）
   ========================================================= */
fwrite($fh, "--- 9. 迁移与回滚 ---\n");

$blueprint = new Blueprint('demo_t');
$blueprint->id();
$blueprint->string('title', 100)->comment('标题');
$blueprint->integer('sort', true)->default(0);
$blueprint->timestamps();
$sql = $blueprint->toCreateSql();
assert_ok(str_starts_with($sql, 'CREATE TABLE `demo_t`'), 'Blueprint 生成建表 SQL');
assert_ok(str_contains($sql, 'AUTO_INCREMENT') && str_contains($sql, 'UNSIGNED'), '主键自增与无符号');
assert_ok(str_contains($sql, "COMMENT '标题'"), '字段注释');
assert_ok(str_contains($sql, 'PRIMARY KEY (`id`)'), '主键索引');
assert_ok($blueprint->toCreateSql() === $sql, '重复编译结果一致（索引不叠加）');

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$dbName = 'kunjing_p6_test';
$pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
$pdo->exec("CREATE DATABASE `$dbName` DEFAULT CHARSET utf8mb4");

$connections = new ConnectionManager();
$connections->setConfigs([
    'default' => [
        'driver'   => 'mysql',
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => $dbName,
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
]);
$connection = $connections->connection('default');

$migrationPath = __DIR__ . '/fixtures/migrations';
$migrator = new Migrator($connection, $migrationPath);

assert_ok($migrator->pending($migrationPath) !== [], '识别待执行迁移');
$ran = $migrator->run($migrationPath);
assert_ok(count($ran) === 1, '执行迁移成功');
assert_ok($connection->select("SHOW TABLES LIKE 'p6_demo'") !== [], '迁移建表生效');

$status = $migrator->status($migrationPath);
assert_ok($status[0]['ran'] === true && $status[0]['batch'] === 1, '迁移状态记录批次');
assert_ok($migrator->pending($migrationPath) === [], '无重复执行');

$rolled = $migrator->rollback(1, $migrationPath);
assert_ok(count($rolled) === 1, '回滚迁移成功');
assert_ok($connection->select("SHOW TABLES LIKE 'p6_demo'") === [], '回滚后表已删除');
assert_ok($migrator->getRan() === [], '回滚后记录已清除');

$pdo->exec("DROP DATABASE IF EXISTS `$dbName`");

/* =========================================================
   10. 代码生成器与命令行内核
   ========================================================= */
fwrite($fh, "--- 10. 代码生成器 ---\n");

$genRoot = $tmpRoot . '/gen';
@mkdir($genRoot, 0777, true);
$genConfig = new Repository();
$genConfig->set('app', [
    'namespace' => 'Gen',
    'paths'     => [
        'controllers' => $genRoot . '/app/Controllers',
        'models'      => $genRoot . '/app/Models',
        'middleware'  => $genRoot . '/app/Middleware',
        'requests'    => $genRoot . '/app/Requests',
        'migrations'  => $genRoot . '/database/migrations',
        'seeders'     => $genRoot . '/database/seeders',
    ],
]);
$quiet = new Output(true);

$modelCmd = new MakeModelCommand($genRoot, $genConfig);
$modelCmd->run(new Input(['kunjing', 'make:model', 'Supplier', '--table=supplier']), $quiet);
$modelFile = $genRoot . '/app/Models/Supplier.php';
assert_ok(is_file($modelFile), 'make:model 生成模型文件');
assert_ok(str_contains((string) file_get_contents($modelFile), 'class Supplier extends Model'), '模型类骨架正确');
assert_ok(str_contains((string) file_get_contents($modelFile), "'supplier'"), '模型表名按 --table 写入');

$ctrlCmd = new MakeControllerCommand($genRoot, $genConfig);
$ctrlCmd->run(new Input(['kunjing', 'make:controller', 'Supplier', '--resource']), $quiet);
$ctrlFile = $genRoot . '/app/Controllers/SupplierController.php';
assert_ok(is_file($ctrlFile), 'make:controller 生成控制器');
$ctrlContent = (string) file_get_contents($ctrlFile);
assert_ok(str_contains($ctrlContent, 'public function index') && str_contains($ctrlContent, 'public function destroy'), '--resource 生成 CRUD 骨架');

$mwCmd = new MakeMiddlewareCommand($genRoot, $genConfig);
$mwCmd->run(new Input(['kunjing', 'make:middleware', 'Auth']), $quiet);
assert_ok(is_file($genRoot . '/app/Middleware/AuthMiddleware.php'), 'make:middleware 生成中间件');

$reqCmd = new MakeRequestCommand($genRoot, $genConfig);
$reqCmd->run(new Input(['kunjing', 'make:request', 'Supplier']), $quiet);
assert_ok(is_file($genRoot . '/app/Requests/SupplierRequest.php'), 'make:request 生成表单请求');

$migCmd = new MakeMigrationCommand($genRoot, $genConfig);
$migCmd->run(new Input(['kunjing', 'make:migration', 'create_order_table', '--table=order']), $quiet);
$created = glob($genRoot . '/database/migrations/*_create_order_table.php') ?: [];
assert_ok($created !== [], 'make:migration 生成迁移文件');
assert_ok($created !== [] && str_contains((string) file_get_contents($created[0]), "create('order'"), '迁移模板带入表名');

$seedCmd = new MakeSeederCommand($genRoot, $genConfig);
$seedCmd->run(new Input(['kunjing', 'make:seeder', 'Order']), $quiet);
assert_ok(is_file($genRoot . '/database/seeders/OrderSeeder.php'), 'make:seeder 生成填充器');

// 生成的代码必须语法正确
$genFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($genRoot, FilesystemIterator::SKIP_DOTS));
$genBad = 0;
$genCount = 0;
foreach ($genFiles as $gf) {
    if ($gf->isFile() && $gf->getExtension() === 'php') {
        $genCount++;
        $out = shell_exec('php -l ' . escapeshellarg($gf->getPathname()) . ' 2>&1');
        if (stripos((string) $out, 'No syntax errors') === false) {
            $genBad++;
        }
    }
}
assert_ok($genBad === 0 && $genCount >= 6, "生成代码语法正确（{$genCount} 个文件）");

/* ---------- 命令行内核 ---------- */
$kernel = new Kernel($genRoot);
assert_ok($kernel->basePath() === $genRoot, 'Kernel 绑定项目根目录');
$consoleApp = new Application('test', '1.0');
$kernel->registerCommands($consoleApp);
assert_ok(count($consoleApp->getCommands()) >= 15, 'Kernel 注册全部内置命令');
assert_ok($consoleApp->has('migrate') && $consoleApp->has('route:list') && $consoleApp->has('make:model'), '关键命令已注册');

/* ---------- 路由命令 ---------- */
$routeConfig = new Repository();
$routeConfig->set('route', ['cache_file' => $genRoot . '/runtime/routes.php']);
$routeKernel = new Kernel($genRoot);
$routeKernel->config()->set('route', ['cache_file' => $genRoot . '/runtime/routes.php']);
$routeKernel->router()->get('/hello', function () {
    return 'hello';
});

$listCmd = new RouteListCommand($genRoot, $routeConfig, $routeKernel);
ob_start();
$code = $listCmd->run(new Input(['kunjing', 'route:list']), new Output());
$listOut = (string) ob_get_clean();
assert_ok($code === 0 && str_contains($listOut, '/hello'), 'route:list 输出路由表');

$cacheCmd = new RouteCacheCommand($genRoot, $routeConfig, $routeKernel);
ob_start();
$cacheCmd->run(new Input(['kunjing', 'route:cache']), new Output());
ob_end_clean();
assert_ok(is_file($genRoot . '/runtime/routes.php'), 'route:cache 生成缓存文件');

$clearCmd = new RouteClearCommand($genRoot, $routeConfig, $routeKernel);
ob_start();
$clearCmd->run(new Input(['kunjing', 'route:clear']), new Output());
ob_end_clean();
assert_ok(!is_file($genRoot . '/runtime/routes.php'), 'route:clear 删除缓存文件');

/* =========================================================
   收尾
   ========================================================= */
// 清理临时目录
$cleanup = function (string $dir) {
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
};
$cleanup($tmpRoot);

fwrite($fh, "\n=== Phase 6: PASS=$pass FAIL=$fail ===\n");
fclose($fh);

echo "PASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
