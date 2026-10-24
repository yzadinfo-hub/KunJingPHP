<?php
/**
 * Phase 2 ORM 验证（本地 MySQL，与组梦ERP 同构）
 * 自动建库/建表，测试结束后清空，不残留。
 */
require_once __DIR__ . '/../bootstrap.php';

use KunJing\Framework\Database\Manager;
use KunJing\Framework\Database\ConnectionManager;
use KunJing\Framework\Database\Model\Model as BaseModel;
use KunJing\Framework\Database\Model\Relation;

$DB = 'kunjing_test';
$root = new \PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
]);
$root->exec("CREATE DATABASE IF NOT EXISTS `{$DB}` CHARACTER SET utf8mb4");

$cm = new ConnectionManager();
$cm->setConfigs(['default' => [
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => $DB,
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
]]);
$manager = new Manager($cm);
Manager::setInstance($manager);
$conn = $manager->connection();

// ---- 模型定义 ----
class User extends BaseModel
{
    protected static ?string $table = 'user';
    protected static array $fillable = ['name', 'email'];
    protected function relations(): array
    {
        return [
            'posts'   => $this->hasMany(Post::class, 'user_id'),
            'profile' => $this->hasOne(Profile::class, 'user_id'),
            'roles'   => $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id'),
        ];
    }
}
class Post extends BaseModel
{
    protected static ?string $table = 'post';
    protected static array $fillable = ['user_id', 'title'];
    protected function relations(): array
    {
        return ['author' => $this->belongsTo(User::class, 'user_id')];
    }
}
class Profile extends BaseModel
{
    protected static ?string $table = 'profile';
    protected static array $fillable = ['user_id', 'bio'];
    protected function relations(): array
    {
        return ['user' => $this->belongsTo(User::class, 'user_id')];
    }
}
class Role extends BaseModel
{
    protected static ?string $table = 'role';
    protected static array $fillable = ['name'];
}

// ---- 建表 ----
$conn->execute("DROP TABLE IF EXISTS `role_user`");
$conn->execute("DROP TABLE IF EXISTS `role`");
$conn->execute("DROP TABLE IF EXISTS `profile`");
$conn->execute("DROP TABLE IF EXISTS `post`");
$conn->execute("DROP TABLE IF EXISTS `user`");
$conn->execute("CREATE TABLE `user` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64), email VARCHAR(128), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB");
$conn->execute("CREATE TABLE `post` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, title VARCHAR(128), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB");
$conn->execute("CREATE TABLE `profile` (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bio VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB");
$conn->execute("CREATE TABLE `role` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB");
$conn->execute("CREATE TABLE `role_user` (user_id INT, role_id INT) ENGINE=InnoDB");

$pass = 0; $fail = 0;
$GLOBALS['fh'] = fopen(__DIR__ . '/phase2_result.txt', 'w');
function assert_ok(bool $cond, string $label): void {
    global $pass, $fail;
    $line = ($cond ? 'PASS' : 'FAIL') . "  $label\n";
    if ($cond) { $pass++; } else { $fail++; }
    echo $line;
    if (isset($GLOBALS['fh'])) { fwrite($GLOBALS['fh'], $line); }
}

// ---- 写入 ----
$u1 = User::create(['name' => '张三', 'email' => 'z@a.com']);
$u2 = User::create(['name' => '李四', 'email' => 'l@a.com']);
assert_ok($u1->exists() && $u1->getKey() === 1, 'Model::create 自增主键');
assert_ok($u1->created_at !== null && $u1->updated_at !== null, '时间戳自动填充');

// ---- 查询构造器 ----
$found = User::find(1);
assert_ok($found !== null && $found->name === '张三', 'Model::find');
$byEmail = User::query()->where('email', 'l@a.com')->first();
assert_ok($byEmail !== null && $byEmail->name === '李四', 'Builder::where->first');
$count = User::query()->where('id', '>', 0)->count();
assert_ok($count === 2, 'Builder::count');
$names = User::query()->whereIn('id', [1, 2])->column('name');
assert_ok(count($names) === 2, 'Builder::column + whereIn');

// ---- 更新 / 删除 ----
$u1->update(['name' => '张三丰']);
assert_ok(User::find(1)->name === '张三丰', 'Model::update');
$del = $u1->delete();
assert_ok($del === 1 && User::find(1) === null, 'Model::delete');

// ---- 关联 ----
$p1 = Post::create(['user_id' => 2, 'title' => '文章A']);
$p2 = Post::create(['user_id' => 2, 'title' => '文章B']);
$user2 = User::find(2);
$posts = $user2->posts;
assert_ok(count($posts) === 2 && $posts[0] instanceof Post, 'hasMany 关联');
$owner = $p1->author;
assert_ok($owner !== null && $owner->name === '李四', 'belongsTo 关联');

// ---- hasOne + 预加载 ----
Profile::create(['user_id' => 2, 'bio' => '简介']);
$withProfile = User::with(['profile'])->where('id', 2)->first();
assert_ok($withProfile->profile !== null && $withProfile->profile->bio === '简介', 'hasOne + with 预加载');
$withPosts = User::with(['posts'])->get();
assert_ok(count($withPosts) >= 1, 'with 预加载集合');

// ---- belongsToMany ----
$r1 = Role::create(['name' => '管理员']);
$r2 = Role::create(['name' => '编辑']);
$conn->execute("INSERT INTO `role_user` (user_id, role_id) VALUES (2, {$r1->getKey()})");
$conn->execute("INSERT INTO `role_user` (user_id, role_id) VALUES (2, {$r2->getKey()})");
$userRoles = $user2->roles;
assert_ok(count($userRoles) === 2, 'belongsToMany 关联');

// ---- 分页 ----
Post::create(['user_id' => 2, 'title' => '文章C']);
$page = Post::query()->where('user_id', 2)->paginate(2, 1);
assert_ok($page['total'] === 3 && count($page['data']) === 2 && $page['last_page'] === 2, 'Builder::paginate');

// ---- 事务 ----
try {
    $manager->transaction(function () use ($conn) {
        $conn->execute("INSERT INTO `user` (name,email) VALUES ('事务A','a@t.com')");
        throw new \RuntimeException('rollback');
    });
} catch (\Throwable $e) {
}
assert_ok(User::query()->where('email', 'a@t.com')->count() === 0, '事务回滚');
$manager->transaction(function () use ($conn) {
    $conn->execute("INSERT INTO `user` (name,email) VALUES ('事务B','b@t.com')");
});
assert_ok(User::query()->where('email', 'b@t.com')->count() === 1, '事务提交');

// ---- 原始查询 ----
// 此时 user 表剩余：李四(id2) + 事务B，共 2 条（张三已删除、事务A已回滚）
$row = $conn->query("SELECT COUNT(*) AS c FROM `user`")[0];
assert_ok((int) $row['c'] === 2, 'Connection::query 原生查询');

// ---- 清理 ----
$root->exec("DROP DATABASE IF EXISTS `{$DB}`");

$summary = "\n=== Phase 2 ORM: PASS=$pass FAIL=$fail ===\n";
echo $summary;
if (isset($GLOBALS['fh'])) { fwrite($GLOBALS['fh'], $summary); fclose($GLOBALS['fh']); }
exit($fail === 0 ? 0 : 1);
