<?php
/**
 * Phase 5 实战迁移验证：组梦ERP「供应商/商品」小模块端到端
 * 覆盖：路由+中间件鉴权+Session登录+ORM CRUD+关联+分页+缓存+验证器+日志+模板视图
 */
require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\Database\Manager;
use Erp\Models\Supplier;
use Erp\Models\Goods;

$GLOBALS['fh'] = fopen(__DIR__ . '/phase5_result.txt', 'w');
$pass = 0; $fail = 0;
function assert_ok(bool $cond, string $label): void {
    global $pass, $fail;
    $line = ($cond ? 'PASS' : 'FAIL') . "  $label\n";
    if ($cond) { $pass++; } else { $fail++; }
    fwrite($GLOBALS['fh'], $line);
}

// ---- 启动 ERP 应用（index.php 被包含时不会自动 run） ----
$app = require __DIR__ . '/../erp_demo/index.php';
$app->container()->make(Manager::class); // 确保 ORM 管理器已绑定
assert_ok($app instanceof KunJing\Framework\App, 'ERP 应用引导成功');

// ---- HTTP 请求模拟器 ----
function request(string $method, string $uri, array $get = [], array $post = [], string &$header = ''): string
{
    global $app;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI']    = $uri;
    $_SERVER['HTTP_HOST']      = '127.0.0.1:8800';
    $_GET  = $get;
    $_POST = $post;

    ob_start();
    $app->run();
    return (string) ob_get_clean();
}

/* ============ 1. 鉴权拦截 ============ */
$out = request('GET', '/supplier');
assert_ok(trim($out) === '', '未登录访问供应商列表被中间件拦截(重定向)');

$out = request('GET', '/login');
assert_ok(str_contains($out, '系统登录') && str_contains($out, 'admin'), '登录页渲染');

/* ============ 2. 错误密码 → 闪存提示 ============ */
request('POST', '/login', [], ['username' => 'admin', 'password' => 'wrongpwd']);
$out = request('GET', '/login');
assert_ok(str_contains($out, '用户名或密码不正确'), '登录失败 → Session 闪存错误提示');

/* ============ 3. 正确登录 ============ */
request('POST', '/login', [], ['username' => 'admin', 'password' => '123456']);
$out = request('GET', '/supplier');
assert_ok(str_contains($out, '供应商管理'), '登录后可见供应商列表页');
assert_ok(str_contains($out, '永州电子科技有限公司'), '列表展示数据库真实数据');
assert_ok(
    str_contains($out, '商品数') && str_contains($out, '联系电话') && str_contains($out, '操作'),
    '模板表格列结构正确'
);

/* ============ 4. 搜索 + 分页 ============ */
$out = request('GET', '/supplier', ['keyword' => '永州']);
assert_ok(str_contains($out, '永州电子科技有限公司') && !str_contains($out, '深圳前沿五金制品厂'), '按关键词搜索过滤');

/* ============ 5. 新增：验证拦截 ============ */
$out = request('POST', '/supplier/store', [], [
    'name' => 'A',               // 太短
    'contact' => '', 'phone' => 'abc', 'address' => '', 'status' => '1',
]);
assert_ok(str_contains($out, '供应商名称至少 2 个字'), '新增：名称过短被拦截');
assert_ok(str_contains($out, '联系电话格式不正确'), '新增：电话格式被拦截');

$out = request('POST', '/supplier/store', [], [
    'name' => '永州电子科技有限公司', // 已存在
    'contact' => '陈工', 'phone' => '13800001111', 'address' => '', 'status' => '1',
]);
assert_ok(str_contains($out, '该供应商名称已存在'), '新增：唯一性校验(unique 规则连库)');

/* ============ 6. 新增成功 ============ */
request('POST', '/supplier/store', [], [
    'name' => '测试供应商-堃景',
    'contact' => '张三', 'phone' => '0731-1234567', 'address' => '测试地址', 'status' => '1',
]);
$out = request('GET', '/supplier');
assert_ok(str_contains($out, '测试供应商-堃景'), '新增供应商成功并出现在列表');

$new = Supplier::query()->where('name', '测试供应商-堃景')->first();
assert_ok($new !== null && $new->getKey() > 0, '新记录已落库');
$newId = $new->getKey();

/* ============ 7. 编辑回填 + 更新 ============ */
$out = request('GET', "/supplier/edit/{$newId}");
assert_ok(str_contains($out, '编辑供应商') && str_contains($out, '测试供应商-堃景'), '编辑页数据回填');

request('POST', "/supplier/update/{$newId}", [], [
    'name' => '测试供应商-已改名',
    'contact' => '李四', 'phone' => '0731-7654321', 'address' => '新地址', 'status' => '0',
]);
$out = request('GET', '/supplier');
assert_ok(str_contains($out, '测试供应商-已改名'), '更新供应商成功');
assert_ok(str_contains($out, '停用'), '状态字段更新为停用呈现');

/* ============ 8. 商品模块 + belongsTo 反向关联 ============ */
request('POST', '/goods/store', [], [
    'supplier_id' => (string) $newId,
    'name' => '测试商品A', 'sku' => 'SKU-TEST-001', 'price' => '9.90', 'stock' => '100',
]);
$out = request('GET', '/goods');
assert_ok(str_contains($out, '测试商品A') && str_contains($out, 'SKU-TEST-001'), '商品新增成功');
assert_ok(str_contains($out, '测试供应商-已改名'), '商品列表展示所属供应商(belongsTo 关联)');

/* ============ 9. SKU 唯一校验 ============ */
request('POST', '/goods/store', [], [
    'supplier_id' => (string) $newId,
    'name' => '重复SKU商品', 'sku' => 'SKU-TEST-001', 'price' => '1', 'stock' => '1',
]);
$out = request('GET', '/goods');
assert_ok(str_contains($out, '该 SKU 已存在'), 'SKU 重复被拦截');

/* ============ 10. JSON API ============ */
$json = request('GET', '/api/suppliers');
$data = json_decode($json, true);
assert_ok(is_array($data) && count($data) >= 4, 'JSON API 返回供应商数组');
assert_ok(isset($data[0]['name']) && $data[0]['name'] !== '', 'JSON API 字段结构正确');

/* ============ 11. 缓存 & 日志 ============ */
$cached = KunJing\Framework\Facade\Cache::get('supplier_total');
assert_ok(is_int($cached) || is_numeric($cached), 'Cache 缓存供应商总数可用');

$logFile = dirname(__DIR__) . '/erp_demo/storage/logs/erp-' . date('Y-m-d') . '.log';
assert_ok(is_file($logFile), 'Logger 写入日常日志文件');
assert_ok(str_contains(file_get_contents($logFile), '查看供应商列表'), '日志记录业务操作');

/* ============ 12. 事务删除（供应商+其下商品） ============ */
request('GET', "/supplier/delete/{$newId}");
$stillExists = Supplier::find($newId);
assert_ok($stillExists === null, '事务删除供应商成功');
$goodsLeft = Goods::query()->where('supplier_id', $newId)->count();
assert_ok($goodsLeft === 0, '级联删除其下商品(事务保证一致)');

$out = request('GET', '/supplier');
assert_ok(!str_contains($out, '测试供应商-已改名'), '删除后列表不再包含该记录');

/* ============ 13. 退出登录 ============ */
request('GET', '/logout');
$out = request('GET', '/supplier');
assert_ok(trim($out) === '', '退出登录后再次被鉴权拦截');

$summary = "\n=== Phase 5 ERP 小模块迁移: PASS=$pass FAIL=$fail ===\n";
fwrite($GLOBALS['fh'], $summary);
fclose($GLOBALS['fh']);
exit($fail === 0 ? 0 : 1);
