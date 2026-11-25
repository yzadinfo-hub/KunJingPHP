<?php
/**
 * Phase 7 工程化补测：为 3 处已修复改动补齐回归用例
 *   1) Builder::setTablePrefix / wrapTable —— 全局表前缀自动注入、防双重前缀
 *   2) App::tryServeStaticFile —— public/ 静态文件直出 + 目录穿越防护（含 # 分隔符 bug 回归）
 *   3) Request::tryParseJsonBody —— application/json body 解析与合并
 *
 * 本测试全部离线可跑（不依赖 MySQL），因为 Connection 用无构造函数实例、静态服务用临时目录。
 * 运行：php tests/phase7.php
 */

require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\App;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Database\Connection;
use KunJing\Framework\Database\Query\Builder;
use KunJing\Framework\Http\Request;

$resultFile = __DIR__ . '/phase7_result.txt';
$fh = fopen($resultFile, 'w');
$pass = 0;
$fail = 0;

function assert_ok(bool $cond, string $label): void
{
    global $pass, $fail, $fh;
    if ($cond) { $pass++; fwrite($fh, "PASS  $label\n"); }
    else { $fail++; fwrite($fh, "FAIL  $label\n"); }
}

function note(string $text): void
{
    global $fh;
    fwrite($fh, "      · $text\n");
}

/* ============================================================
 * 1) 表前缀：Builder::setTablePrefix / wrapTable
 * ============================================================ */
note('=== 表前缀测试 ===');

// 用一个「未初始化」的 Connection 实例：toSql() 不触发任何 DB 查询
$refConn = new ReflectionClass(Connection::class);
$conn = $refConn->newInstanceWithoutConstructor();

try {
    Builder::setTablePrefix('kj_');
    $sql = (new Builder($conn))->table('goods')->toSql();
    assert_ok(str_contains($sql, '`kj_goods`'), '裸表名自动加前缀：FROM `kj_goods`');
    note("SQL=$sql");

    // 已含前缀不重复加
    $sql2 = (new Builder($conn))->table('kj_goods')->toSql();
    assert_ok(str_contains($sql2, '`kj_goods`') && !str_contains($sql2, 'kj_kj_'), '已含前缀不重复加（防双重前缀）');

    // join 表也应用前缀
    $sql3 = (new Builder($conn))->table('goods')->join('category', 'goods.cid', '=', 'category.id')->toSql();
    assert_ok(str_contains($sql3, 'JOIN `kj_category`'), 'JOIN 表同样应用前缀');

    // 别名场景
    $sql4 = (new Builder($conn))->table('goods', 'g')->toSql();
    assert_ok(str_contains($sql4, '`kj_goods` AS `g`'), '带别名时前缀正确');

    // 恢复无前缀
    Builder::setTablePrefix('');
    $sql5 = (new Builder($conn))->table('goods')->toSql();
    assert_ok(str_contains($sql5, '`goods`') && !str_contains($sql5, 'kj_'), '前缀置空后裸表名不加前缀');
} finally {
    Builder::setTablePrefix(''); // 确保不污染其他测试
}

/* ============================================================
 * 2) 静态文件直出：App::tryServeStaticFile
 * ============================================================ */
note('=== 静态文件直出测试 ===');

$tmpPublic = sys_get_temp_dir() . '/kj_static_' . bin2hex(random_bytes(4));
mkdir($tmpPublic . '/css', 0777, true);
mkdir($tmpPublic . '/js', 0777, true);
file_put_contents($tmpPublic . '/css/app.css', 'body{color:#123456}');
file_put_contents($tmpPublic . '/js/main.js', 'console.log("ok");');
file_put_contents($tmpPublic . '/secret.txt', 'SHOULD_NOT_LEAK');

$basePath = dirname(__DIR__);
$app = new App($basePath);
$app->config()->set('app.public_path', $tmpPublic);

$refApp = new ReflectionClass(App::class);
$method = $refApp->getMethod('tryServeStaticFile');
$method->setAccessible(true);

function callServe(App $app, ReflectionMethod $m, string $path, string $httpMethod = 'GET'): array
{
    $req = new Request([], [], [
        'REQUEST_URI'    => $path,
        'REQUEST_METHOD' => $httpMethod,
        'HTTP_HOST'      => 'localhost',
    ]);
    ob_start();
    $ret = $m->invoke($app, $req);
    $out = ob_get_clean();
    return [$ret, $out];
}

try {
    [$ret, $out] = callServe($app, $method, '/css/app.css');
    assert_ok($ret === true && str_contains($out, 'color:#123456'), '存在静态文件 → 200 直出内容');

    [$ret, $out] = callServe($app, $method, '/js/main.js');
    assert_ok($ret === true && str_contains($out, 'console.log'), 'js 文件直出');

    [$ret, $out] = callServe($app, $method, '/css/missing.css');
    assert_ok($ret === false && $out === '', '不存在文件 → false（走 404）');

    // 目录穿越（关键安全项）：public 根下的 secret.txt 可读，但 ../ 穿越必须拒绝
    [$ret, $out] = callServe($app, $method, '/../secret.txt');
    assert_ok($ret === false && !str_contains($out, 'SHOULD_NOT_LEAK'), '目录穿越 ../ 被拒绝');

    [$ret, $out] = callServe($app, $method, '/css/../secret.txt');
    assert_ok($ret === false && !str_contains($out, 'SHOULD_NOT_LEAK'), '路径内 .. 被拒绝');

    // 特殊字符（正则 bug 回归）：带 % 或非白名单字符应拒绝而非报错
    [$ret, $out] = callServe($app, $method, '/css/a%20b.css');
    assert_ok($ret === false, '非白名单字符路径被拒绝（# 分隔符 bug 回归）');

    // POST 不应触发静态服务
    [$ret, $out] = callServe($app, $method, '/css/app.css', 'POST');
    assert_ok($ret === false, 'POST 方法不直出静态文件');

    // HEAD 应直出但 body 保持（Content-Length 头已设，CLI 下不校验 header）
    [$ret, $out] = callServe($app, $method, '/css/app.css', 'HEAD');
    assert_ok($ret === true && str_contains($out, 'color:#123456'), 'HEAD 方法同样命中');

    // 无 public_path 配置且 basePath 下无 public 目录 → false（不误伤）
    $app2 = new App($basePath);
    $app2->config()->set('app.public_path', '/nonexistent/path/xyz');
    [$ret, $out] = callServe($app2, $method, '/css/app.css');
    assert_ok($ret === false, '无有效 public 目录 → false');
} finally {
    // 清理临时目录
    @unlink($tmpPublic . '/css/app.css');
    @unlink($tmpPublic . '/js/main.js');
    @unlink($tmpPublic . '/secret.txt');
    @rmdir($tmpPublic . '/css');
    @rmdir($tmpPublic . '/js');
    @rmdir($tmpPublic);
}

/* ============================================================
 * 3) JSON body 解析：Request::tryParseJsonBody
 * ============================================================ */
note('=== JSON body 解析测试 ===');

$refReq = new ReflectionClass(Request::class);
$parseJson = $refReq->getMethod('tryParseJsonBody');
$parseJson->setAccessible(true);

function parseJson(string $method, ?string $raw, string $contentType): ?array
{
    global $parseJson;
    $_SERVER['CONTENT_TYPE'] = $contentType;
    return $parseJson->invoke(null, $raw, $method);
}

try {
    $r = parseJson('POST', '{"name":"张三","age":18}', 'application/json');
    assert_ok(is_array($r) && $r['name'] === '张三' && $r['age'] === 18, 'JSON 对象解析为关联数组');

    $r = parseJson('POST', '[1,2,3]', 'application/json');
    assert_ok(is_array($r) && count($r) === 3, 'JSON 数组解析');

    $r = parseJson('POST', '{"name":"x"}', 'text/plain');
    assert_ok(is_array($r) && $r['name'] === 'x', 'body 以 { 开头即使非 JSON 头也解析');

    $r = parseJson('POST', 'name=foo&age=1', 'application/x-www-form-urlencoded');
    assert_ok($r === null, '表单 body 不按 JSON 解析');

    $r = parseJson('POST', 'not-a-json {', 'application/json');
    assert_ok($r === null, '非法 JSON 返回 null（不抛异常）');

    $r = parseJson('POST', null, 'application/json');
    assert_ok($r === null, '空 body 返回 null');

    $r = parseJson('POST', '   ', 'application/json');
    assert_ok($r === null, '纯空白 body 返回 null');

    $r = parseJson('GET', '{"x":1}', 'application/json');
    assert_ok(is_array($r) && $r['x'] === 1, 'GET 带 JSON body 也能解析（由调用方控制方法白名单）');

    // 验证 capture() 在 JSON 场景下 $_POST 与解析结果合并：
    // CLI 无法写 php://input，直接验证构造器输入优先级（post 覆盖 get）
    $req = new Request(['a' => 'from_get', 'b' => 'get_only'], ['a' => 'from_post'], [], '{"c":3}');
    assert_ok($req->input('a') === 'from_post' && $req->input('b') === 'get_only', 'post 参数优先于 get');
    assert_ok($req->json()['c'] === 3, 'rawBody 可经 json() 解析');
} finally {
    unset($_SERVER['CONTENT_TYPE']);
}

/* ---------- 汇总 ---------- */
$summary = "\nPASS=$pass FAIL=$fail\n";
file_put_contents($resultFile, $summary, FILE_APPEND);
fclose($fh);

echo $summary;
echo ($fail === 0 ? "Phase 7 全部通过 ✅\n" : "Phase 7 存在失败 ❌\n");
exit($fail === 0 ? 0 : 1);
