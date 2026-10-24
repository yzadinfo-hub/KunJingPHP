<?php
/**
 * Phase 3 服务组件验证：缓存 / 日志 / 验证器 / 事件 / 会话 / 命令行
 */
require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\Cache\CacheManager;
use KunJing\Framework\Cache\FileStore;
use KunJing\Framework\Cache\ArrayStore;
use KunJing\Framework\Log\Logger;
use KunJing\Framework\Validate\Validator;
use KunJing\Framework\Exception\ValidateException;
use KunJing\Framework\Event\Dispatcher;
use KunJing\Framework\Session\SessionManager;
use KunJing\Framework\Session\Store;
use KunJing\Framework\Console\Application;
use KunJing\Framework\Console\Command;
use KunJing\Framework\Console\Output;
use KunJing\Framework\Database\Manager;
use KunJing\Framework\Database\ConnectionManager;

$GLOBALS['fh'] = fopen(__DIR__ . '/phase3_result.txt', 'w');
$pass = 0; $fail = 0;
function assert_ok(bool $cond, string $label): void {
    global $pass, $fail;
    $line = ($cond ? 'PASS' : 'FAIL') . "  $label\n";
    if ($cond) { $pass++; } else { $fail++; }
    fwrite($GLOBALS['fh'], $line);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing_phase3';
if (!is_dir($tmp)) { mkdir($tmp, 0755, true); }

/* ============ 1. 缓存 ============ */
$cache = new CacheManager([
    'default' => 'file',
    'stores' => [
        'file'  => ['driver' => 'file', 'path' => $tmp . DIRECTORY_SEPARATOR . 'cache'],
        'array' => ['driver' => 'array'],
    ],
]);
// 清空上一次运行残留，保证测试可重复执行
$cache->clear();

$cache->set('name', '堃景', 60);
assert_ok($cache->get('name') === '堃景', 'Cache FileStore set/get');
assert_ok($cache->has('name') === true, 'Cache has');
$cache->delete('name');
assert_ok($cache->has('name') === false, 'Cache delete');

// TTL 过期
$cache->set('short', 'v', 1);
sleep(2);
assert_ok($cache->has('short') === false, 'Cache TTL 过期失效');

// 自增 / 预取这样是 remember
assert_ok($cache->increment('counter') === 1 && $cache->increment('counter', 2) === 3, 'Cache increment');
$calls = 0;
$value = $cache->remember('heavy', 60, function () use (&$calls) { $calls++; return 'computed'; });
$value2 = $cache->remember('heavy', 60, function () use (&$calls) { $calls++; return 'computed'; });
assert_ok($value === 'computed' && $value2 === 'computed' && $calls === 1, 'Cache remember 只算一次');

// 数组驱动
$arr = new ArrayStore();
$arr->set('a', 1);
assert_ok($arr->get('a') === 1 && $arr->get('missing', 'x') === 'x', 'Cache ArrayStore 默认值');
assert_ok($cache->store('array') instanceof ArrayStore, 'Cache 多驱动切换');

/* ============ 2. 日志 ============ */
$logFile = $tmp . DIRECTORY_SEPARATOR . 'app.log';
@unlink($logFile);
$logger = new Logger(['path' => $logFile, 'channel' => 'single', 'level' => 'debug']);
$logger->info('这是一条信息日志');
$logger->error('这是一条错误日志 {code}', ['code' => 500]);
assert_ok(is_file($logFile), 'Logger 写入文件');
$content = file_get_contents($logFile);
assert_ok(str_contains($content, '这是一条信息日志'), 'Logger info 内容正确');
assert_ok(str_contains($content, 'ERROR') && str_contains($content, '500'), 'Logger error + 上下文插值');

// 级别阈值过滤
$logFile2 = $tmp . DIRECTORY_SEPARATOR . 'warn.log';
@unlink($logFile2);
$warnLogger = new Logger(['path' => $logFile2, 'level' => 'warning']);
$warnLogger->debug('不应出现');
$warnLogger->warning('应出现');
$content2 = file_get_contents($logFile2);
assert_ok(!str_contains($content2, '不应出现') && str_contains($content2, '应出现'), 'Logger 级别阈值过滤');

// daily 通道
$dailyLogger = new Logger(['path' => $tmp . DIRECTORY_SEPARATOR . 'daily.log', 'channel' => 'daily']);
$dailyLogger->info('按日切分');
assert_ok(is_file($tmp . DIRECTORY_SEPARATOR . 'daily-' . date('Y-m-d') . '.log'), 'Logger daily 通道按日期分文件');

/* ============ 3. 验证器 ============ */
$v = Validator::make(
    ['name' => '张三', 'email' => 'a@b.com', 'age' => 20],
    ['name' => 'required|min:2|max:10', 'email' => 'required|email', 'age' => 'required|integer|min:18']
);
assert_ok($v->passes() === true, 'Validator 合法数据通过');

$v2 = Validator::make(['email' => 'bad-email'], ['email' => 'required|email']);
assert_ok($v2->fails() === true, 'Validator 非法邮箱拦截');
assert_ok($v2->firstError() !== null && $v2->errors() !== [], 'Validator 错误信息可读');

$v3 = Validator::make([], ['name' => 'required']);
assert_ok($v3->fails() && str_contains($v3->firstError(), '不能为空'), 'Validator required 缺值');

$v4 = Validator::make(['status' => 'x'], ['status' => 'required|in:0,1,2']);
assert_ok($v4->fails(), 'Validator in 规则');

$v5 = Validator::make(['code' => 'ABC123'], ['code' => 'required|regex:/^[A-Z0-9]+$/']);
assert_ok($v5->passes(), 'Validator regex 规则');

$v6 = Validator::make(['pwd' => 'abc123', 'pwd_confirmation' => 'abc124'], ['pwd' => 'required|confirmed']);
assert_ok($v6->fails(), 'Validator confirmed 两次输入不一致');

$v7 = Validator::make(['n' => 5], ['n' => 'required|between:1,10']);
assert_ok($v7->passes(), 'Validator between 规则');

$v8 = Validator::make(['id' => 3], ['id' => 'required|numeric']);
$v8->extend('even', fn($value) => ((int) $value) % 2 === 0);
$v8b = Validator::make(['id' => 3], ['id' => 'required|numeric']);
$v8b->extend('even', fn($value) => is_numeric($value) && ((int) $value) % 2 === 0);
assert_ok($v8b->passes(), 'Validator 基础 numeric');
$v8c = Validator::make(['id' => 99], ['id' => 'even']);
$v8c->extend('even', fn($value) => is_numeric($value) && ((int) $value) % 2 === 0);
assert_ok($v8c->fails(), 'Validator 自定义闭包规则');

// 抛异常版本
$thrown = false;
try {
    Validator::check(['email' => 'x'], ['email' => 'required|email']);
} catch (ValidateException $e) {
    $thrown = $e->getErrors() !== [] && $e->getCode() === 422;
}
assert_ok($thrown, 'Validator::check 失败抛 ValidateException');

/* ============ 4. 验证器 unique（连库） ============ */
$DB = 'kunjing_test';
try {
    $rootPdo = new \PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB}` CHARACTER SET utf8mb4");
    $rootPdo->exec("DROP TABLE IF EXISTS `{$DB}`.`user`");
    $rootPdo->exec("CREATE TABLE `{$DB}`.`user` (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(128)) ENGINE=InnoDB");
    $cm = new ConnectionManager();
    $cm->setConfigs(['default' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $DB, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4']]);
    $dbManager = new Manager($cm);
    Manager::setInstance($dbManager);
    $dbManager->table('user')->insert(['email' => 'dup@a.com']);

    $dup = Validator::make(['email' => 'dup@a.com'], ['email' => 'required|email|unique:user,email']);
    assert_ok($dup->fails(), 'Validator unique 命中已存在值');
    $new = Validator::make(['email' => 'new@a.com'], ['email' => 'required|email|unique:user,email']);
    assert_ok($new->passes(), 'Validator unique 新值通过');
    $rootPdo->exec("DROP DATABASE IF EXISTS `{$DB}`");
} catch (\Throwable $e) {
    assert_ok(false, 'Validator unique 依赖 MySQL：' . $e->getMessage());
}

/* ============ 5. 事件 ============ */
$dispatcher = new Dispatcher();
$order = [];
$dispatcher->listen('user.created', function ($user) use (&$order) { $order[] = 'low'; });
$dispatcher->listen('user.created', function ($user) use (&$order) { $order[] = 'high'; }, 10);
$dispatcher->dispatch('user.created', ['name' => '张三']);
assert_ok($order === ['high', 'low'], 'Event 优先级排序执行');

$homeOnce = 0;
$dispatcher->once('once.event', function () use (&$homeOnce) { $homeOnce++; });
$dispatcher->dispatch('once.event');
$dispatcher->dispatch('once.event');
assert_ok($homeOnce === 1, 'Event once 只触发一次');

$halted = [];
$dispatcher->listen('halt.event', function () use (&$halted) { $halted[] = 1; return false; });
$dispatcher->listen('halt.event', function () use (&$halted) { $halted[] = 2; });
$dispatcher->dispatch('halt.event', null, true);
assert_ok(count($halted) === 1, 'Event halt 中断后续监听器');
assert_ok($dispatcher->hasListeners('user.created') && !$dispatcher->hasListeners('unknown.event'), 'Event hasListeners');

/* ============ 6. 会话 ============ */
$session = new SessionManager(['driver' => 'array', 'files' => $tmp . DIRECTORY_SEPARATOR . 'session']);
$session->set('user_id', 42);
assert_ok($session->get('user_id') === 42, 'Session set/get');
$session->flash('msg', '保存成功');
assert_ok($session->getFlash('msg') === '保存成功', 'Session flash 读取');
assert_ok($session->pull('user_id') === 42 && $session->has('user_id') === false, 'Session pull 取出即删');
$session->set('a', 1); $session->set('b', 2);
$session->flush();
assert_ok($session->all() === [], 'Session flush 清空');

// 持久化到 Store 序列化
$store = new Store('abc', ['x' => 1]);
$payload = $store->toArray();
$restored = Store::fromArray('abc', $payload);
assert_ok($restored->get('x') === 1 && $restored->getId() === 'abc', 'Session Store 序列化往返');

/* ============ 7. 命令行 ============ */
class DemoSumCommand extends Command
{
    protected string $name = 'demo:sum';
    protected string $description = '示例：两数相加';

    protected function handle(): int
    {
        $a = (int) ($this->argument(0) ?? 0);
        $b = (int) ($this->option('b') ?? 0);
        $this->line('RESULT=' . ($a + $b));
        return 0;
    }
}

$app = new Application('测试CLI', '1.0.0');
$app->add(new DemoSumCommand());
assert_ok($app->has('demo:sum'), 'Console 命令注册');
ob_start();
$code = $app->run(['kunjing', 'demo:sum', '5', '--b=7']);
$captured = ob_get_clean();
assert_ok($code === 0 && str_contains($captured, 'RESULT=12'), 'Console 命令执行与参数解析');

ob_start();
$code = $app->run(['kunjing', 'not-exist']);
$captured = ob_get_clean();
assert_ok($code === 1 && str_contains($captured, '未找到命令'), 'Console 未知命令返回非 0');

$summary = "\n=== Phase 3 服务组件: PASS=$pass FAIL=$fail ===\n";
fwrite($GLOBALS['fh'], $summary);
fclose($GLOBALS['fh']);
exit($fail === 0 ? 0 : 1);
