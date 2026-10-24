<?php
/**
 * Phase 4 模板引擎验证：变量/转义/if/foreach/include/继承/缓存/门面
 */
require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\View\Engine;
use KunJing\Framework\App;
use KunJing\Framework\Facade\View;

$GLOBALS['fh'] = fopen(__DIR__ . '/phase4_result.txt', 'w');
$pass = 0; $fail = 0;
function assert_ok(bool $cond, string $label): void {
    global $pass, $fail;
    $line = ($cond ? 'PASS' : 'FAIL') . "  $label\n";
    if ($cond) { $pass++; } else { $fail++; }
    fwrite($GLOBALS['fh'], $line);
}

$viewPath = __DIR__ . DIRECTORY_SEPARATOR . 'views';
$cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing_phase4';
if (!is_dir($cachePath)) { mkdir($cachePath, 0755, true); }
$engine = new Engine(['view_path' => $viewPath, 'cache_path' => $cachePath]);
$engine->clearCache();

/* ---- 1. 变量 / 转义 / 点号 / foreach 带键 ---- */
class DemoObj
{
    public function __construct(protected array $attrs = []) {}
    public function getAttribute(string $key) { return $this->attrs[$key] ?? null; }
}
$out = $engine->render('vars', [
    'name'   => '堃景',
    'danger' => '<script>alert(1)</script>',
    'user'   => ['name' => '张三', 'profile' => ['age' => 30]],
    'obj'    => new DemoObj(['title' => '模型属性']),
    'map'    => ['a' => 1, 'b' => 2],
]);
assert_ok(str_contains($out, '变量：堃景'), 'View 变量输出');
assert_ok(str_contains($out, '&lt;script&gt;'), 'View 默认 HTML 转义(XSS 防护)');
assert_ok(str_contains($out, '<script>alert(1)</script>'), 'View |raw 原样输出');
assert_ok(str_contains($out, '张三') && str_contains($out, '30'), 'View 点号访问多维数组');
assert_ok(str_contains($out, '模型属性'), 'View 点号访问对象 getAttribute');
assert_ok(str_contains($out, '<dt>a</dt><dd>1</dd>'), 'View foreach 带键遍历');
assert_ok(!str_contains($out, '{$'), 'View 模板标签全部编译无残留');

/* ---- 2. 模板继承 + include + if/elseif/else ---- */
$out2 = $engine->render('home', [
    'message' => '你好',
    'score'   => 95,
    'list'    => ['第一项', '第二项'],
    'site'    => '公共站名',
]);
assert_ok(str_contains($out2, '<title>首页</title>'), 'View 子模板覆盖父区块 title');
assert_ok(str_contains($out2, '自定义页脚'), 'View 子模板覆盖 footer 区块');
assert_ok(str_contains($out2, '消息：你好'), 'View 区块内变量渲染');
assert_ok(str_contains($out2, '优秀') && !str_contains($out2, '不及格'), 'View if/elseif 分支');
assert_ok(str_contains($out2, '<li>第一项</li>') && str_contains($out2, '<li>第二项</li>'), 'View foreach 列表');
assert_ok(str_contains($out2, '公共头部：公共站名'), 'View include 引入子视图');

// 分支切换
$out3 = $engine->render('home', ['message' => 'x', 'score' => 40, 'list' => [], 'site' => 's']);
assert_ok(str_contains($out3, '不及格'), 'View else 分支生效');

// 继承默认值（未覆盖的区块使用父模板内容）
assert_ok(str_contains($out2, 'END') === false, 'View 父区块被覆盖后不再输出默认内容');

/* ---- 3. 编译缓存 ---- */
$compiledFiles = glob($cachePath . DIRECTORY_SEPARATOR . '*.php') ?: [];
assert_ok(count($compiledFiles) >= 2, 'View 生成编译缓存文件');
$mtimeBefore = filemtime($compiledFiles[0]);
$engine->render('vars', ['name' => '再次', 'danger' => 'x', 'user' => [], 'obj' => new DemoObj([]), 'map' => []]);
$mtimeAfter = filemtime($compiledFiles[0]);
assert_ok($mtimeBefore === $mtimeAfter, 'View 二次渲染命中缓存不重复编译');

/* ---- 4. 源模板改动后自动重编译 ---- */
$tmpTemplate = $viewPath . DIRECTORY_SEPARATOR . 'hotreload.html';
file_put_contents($tmpTemplate, '版本1');
$o1 = $engine->render('hotreload');
clearstatcache();
file_put_contents($tmpTemplate, '版本2');
touch($tmpTemplate, time() + 5);
$o2 = $engine->render('hotreload');
assert_ok($o1 === '版本1' && $o2 === '版本2', 'View 源文件变更后自动重编译');
@unlink($tmpTemplate);

/* ---- 5. App 容器绑定 + View 门面 ---- */
$app = new App(dirname(__DIR__));
$app->config()->set('view', ['view_path' => $viewPath, 'cache_path' => $cachePath]);
$viaFacade = View::render('vars', ['name' => '门面', 'danger' => 'd', 'user' => [], 'obj' => new DemoObj([]), 'map' => []]);
assert_ok(str_contains($viaFacade, '门面'), 'View 门面经容器解析可用');
assert_ok(View::exists('vars') && !View::exists('nope-nope'), 'View exists 判断模板存在');

$summary = "\n=== Phase 4 模板引擎: PASS=$pass FAIL=$fail ===\n";
fwrite($GLOBALS['fh'], $summary);
fclose($GLOBALS['fh']);
exit($fail === 0 ? 0 : 1);
