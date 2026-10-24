# 堃景PHP / KunJingPHP 开发手册 · Phase 6 生产化能力

> 适用版本：`kunjing` 控制台 `1.1.0` ｜ 框架内核 `KunJing\Framework\*` ｜ 编写日期：2026-09-25
> 本文档面向团队内部开发者，覆盖 Phase 6（生产化能力补齐）的全部模块：**注解路由 / 路由缓存 / 资源路由 / 命名路由 / 文件上传 / 表单请求 / 分页器 / CORS·CSRF·Throttle 中间件 / 数据迁移 / 数据填充 / 代码生成器**。

---

## 0. 一句话定位

Phase 6 让框架从「能跑通 Demo」升级到「能写业务」：你可以用 **注解声明路由**、用 **`make:*` 生成代码**、用 **迁移脚本管理表结构**、用 **FormRequest 做对象化校验**、用 **内置中间件挡 CSRF / 跨域 / 限流**。全部零外部依赖，纯 PHP 自研。

> 法律边界重申：以下所有 API 均为堃景PHP 自有设计，命名、类名、方法签名与任何第三方框架无关，仅保证「能力等价」。

---

## 1. 注解路由（Attributes）

用 PHP 8 原生 Attribute 在控制器上直接声明路由，免去集中式 `routes.php` 维护。

### 1.1 可用注解

| 注解 | 说明 | 位置 |
|------|------|------|
| `#[Route('METHOD', '/uri', ...)]` | 任意方法 / 指定 uri | 类 / 方法 |
| `#[Get('/uri')]` `#[Post(...)]` `#[Put(...)]` `#[Patch(...)]` `#[Delete(...)]` `#[Any('/uri')]` | 固定 HTTP 方法的快捷注解 | 方法 |
| `#[Prefix('/api/v1')]` | 类级路径前缀 | 类 |
| `#[Middleware([...])]` | 类级 / 方法级中间件 | 类 / 方法 |
| `#[Where(['id' => '[0-9]+'])]` | 参数正则约束 | 类 / 方法 |

### 1.2 控制器示例

```php
<?php
namespace App\Controllers;

use KunJing\Framework\Routing\Attributes\Get;
use KunJing\Framework\Routing\Attributes\Post;
use KunJing\Framework\Routing\Attributes\Prefix;
use KunJing\Framework\Routing\Attributes\Middleware;
use KunJing\Framework\Routing\Attributes\Where;
use App\Middleware\AuthMiddleware;

#[Prefix('/api/v1')]
#[Middleware([AuthMiddleware::class])]
class UserController
{
    #[Get('/users')]
    public function index()
    {
        return Response::json(['code' => 0, 'data' => []]);
    }

    #[Post('/users')]
    public function store()
    {
        return Response::json(['code' => 0, 'message' => 'created']);
    }

    #[Get('/users/{id}')]
    #[Where(['id' => '[0-9]+'])]
    public function show($id)
    {
        return Response::json(['code' => 0, 'id' => $id]);
    }

    #[Route('DELETE', '/users/{id}', name: 'user.destroy')]
    public function destroy($id)
    {
        return Response::json(['code' => 0, 'id' => $id]);
    }
}
```

### 1.3 注册方式

- 单个类：`$app->router()->attributes([UserController::class]);`
- 整个目录（自动扫描）：`$app->router()->attributeDirectory($dir, $namespace);`

> 规则：方法名缺省 URI 时自动按「小驼峰转中划线」推导，如 `userList()` → `/user-list`；`index()` → `/`。

---

## 2. 路由缓存（生产加速）

路由正则匹配在路由多时有开销，生产环境可将路由导出为 PHP 数组缓存。

### 2.1 手动在代码中开启

```php
$router->cacheFile($basePath . '/runtime/route_cache.php');
$router->boot(function (Router $r) {
    // 仅在缓存不存在 / refresh=true 时执行
    $r->attributes([UserController::class]);
    $r->get('/home', [HomeController::class, 'index']);
});
```

- 首次调用：执行闭包并 `dumpCache()` 写盘。
- 后续调用：直接 `loadFromCache()`，跳过注解扫描与闭包。
- `$router->boot($cb, true)` 可强制刷新。

### 2.2 命令行

```bash
php kunjing route:cache     # 扫描 routes.php，生成 runtime/route_cache.php
php kunjing route:clear     # 删除缓存文件
php kunjing route:list      # 列出全部路由（排查用）
```

---

## 3. 资源路由（RESTful）

一行生成 7 条标准 REST 路由（index/create/store/show/edit/update/destroy），并自动约束 `id` 为数字、自动命名。

```php
$router->resource('goods', \App\Controllers\GoodsController::class);
// 等价于：
// GET    /goods            -> index   名称 goods.index
// GET    /goods/create     -> create  名称 goods.create
// POST   /goods            -> store   名称 goods.store
// GET    /goods/{id}       -> show    名称 goods.show
// GET    /goods/{id}/edit  -> edit    名称 goods.edit
// PUT    /goods/{id}       -> update  名称 goods.update
// DELETE /goods/{id}       -> destroy 名称 goods.destroy
```

---

## 4. 命名路由与 URL 生成

```php
$router->get('/goods/{id}', [...])->name('goods.show');

// 生成 URL（自动替换 {id}，拼接保留的查询参数）
$url = $router->url('goods.show', ['id' => 5, 'ref' => 'home']);
// 结果：/goods/5?ref=home

// 绝对地址
$abs = $router->url('goods.show', ['id' => 5], true);
// 结果：http://host/goods/5
```

---

## 5. 请求对象增强（Request）

`Http\Request` 在 Phase 6 新增以下能力：

| 方法 | 说明 |
|------|------|
| `ip()` | 客户端 IP（含代理头 `X-Forwarded-For` 兜底） |
| `userAgent()` | `User-Agent` |
| `bearerToken()` | 解析 `Authorization: Bearer xxx` |
| `wantsJson()` | 依据 `Accept: application/json` 判断 |
| `isMethod('POST')` / `isGet()` / `isPost()` | 方法判断 |
| `file('avatar')` | 返回 `UploadedFile`（多文件上传返回数组） |
| `hasFile('avatar')` | 是否上传了该字段 |
| `json()` | 解析 `php://input` 的 JSON 体 |

```php
$req = new Request($_GET, $_POST, $_SERVER, php://input, $_FILES);
if ($req->isPost() && $req->wantsJson()) {
    $token = $req->bearerToken();
    $file  = $req->file('avatar');
}
```

---

## 6. 文件上传（UploadedFile）

```php
$file = $request->file('avatar');
if ($file !== null && $file->isValid()) {
    // 保存到指定目录，文件名可自定义
    $saved = $file->store($basePath . '/public/uploads', 'avatar_' . time() . '.png');
    echo $file->getStoredPath();   // 完整保存路径
}
```

- 校验：`isValid()`（检查 `error` 码）、`getErrorMessage()`、`getClientOriginalName()`、`getSize()`、`getMimeType()`。
- 保存：`store(string $dir, ?string $name = null, bool $overwrite = false)` 返回目标路径。

---

## 7. 表单请求（FormRequest）

把校验逻辑沉淀为独立的请求对象，控制器方法声明类型即可自动校验。

```php
<?php
namespace App\Requests;

use KunJing\Framework\Validate\FormRequest;

class SupplierRequest extends FormRequest
{
    // 鉴权：返回 false 抛 403
    public function authorize(): bool
    {
        return $this->input('token') !== 'deny';
    }

    // 校验规则
    public function rules(): array
    {
        return [
            'name'  => 'required|min:2|max:60',
            'phone' => 'required|regex:/^1[3-9]\d{9}$/',
        ];
    }

    // 字段中文别名（错误提示里的 :attribute 会被替换）
    public function attributes(): array
    {
        return ['name' => '供应商名称', 'phone' => '联系电话'];
    }

    // 自定义提示（键支持 "字段.规则" 或 "字段"）
    public function messages(): array
    {
        return ['phone.required' => ':attribute不能为空，请填写后再提交'];
    }
}
```

控制器里直接依赖注入：

```php
public function store(SupplierRequest $request)
{
    $data = $request->validated();   // 仅含 rules 中声明的字段
    // ... 写库
}
```

行为约定：

- 校验失败：框架抛出 `ValidateException`（HTTP 422），响应体为 `{"code":422,"message":"...","errors":{"字段":"提示"}}`。
- 鉴权失败：抛出 `HttpException(403)`。
- `validated()` 只返回通过校验的字段集合，天然防越权字段写入。

---

## 8. 分页器（Paginator）

`Model::query()->paginate(15, $page)` 已返回 `['data'=>..., 'total'=>..., 'per_page'=>..., 'current_page'=>...]`。用 `Paginator` 渲染：

```php
$result = Goods::query()->where('status', 1)->paginate(15, $page);
$pager  = Paginator::make($result, '/goods');

echo $pager->render();      // 完整分页条（带页码 + 上下页 + 总数）
echo $pager->simple();      // 极简（仅上下页 + 当前/总页）
echo Paginator::styles();   // 内置 CSS，可内联到 <style>
```

常用访问器：`total()` / `currentPage()` / `lastPage()` / `items()` / `hasMorePages()` / `onFirstPage()` / `previousUrl()` / `nextUrl()` / `toArray()`。

> 自定义页码参数名：`$pager->setPageName('p')`；追加查询串：`$pager->appends(['kw' => $kw])`。

---

## 9. 内置中间件

### 9.1 CORS（跨域）

```php
$router->group(['middleware' => [CorsMiddleware::class]], function ($r) {
    $r->get('/api/status', [...]);
});
```

配置（`config/cors.php` 的 `cors` 段 或 构造传入 `$config`）：

```php
'cors' => [
    'allow_origin'      => '*',
    'allow_methods'     => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
    'allow_headers'     => 'Content-Type,Authorization,X-Requested-With,X-Csrf-Token',
    'allow_credentials' => false,
    'max_age'           => 86400,
];
```

预检 `OPTIONS` 请求直接返回 `204`，不进入业务。

### 9.2 CSRF（防跨站请求伪造）

```php
$router->post('/supplier', [...])->middleware(CsrfMiddleware::class);
```

- 模板里插入隐藏域：`CsrfMiddleware::field()`（或 `{$csrf_field|raw}`）。
- AJAX 请求头带 `X-Csrf-Token`。
- 支持跳过路径（构造传 `except`，支持 `*` 通配）。
- 仅对 `POST/PUT/PATCH/DELETE` 校验，`GET/HEAD/OPTIONS` 跳过。
- 校验失败抛 `403`。

### 9.3 Throttle（访问限流）

```php
// 实例方式：显式阈值（1 分钟 10 次）
$route->middleware(new ThrottleMiddleware(10, 1));

// 类方式：取默认配置（config/throttle.php 的 throttle.limit / throttle.minutes）
$router->group(['middleware' => [ThrottleMiddleware::class]], ...);
```

超限返回 `429`，JSON 模式下响应头带 `Retry-After`。默认阈值 `60 次/分钟`，可 `ThrottleMiddleware::configure($limit, $minutes)` 改。

---

## 10. 数据迁移（Migrations）

用 PHP 脚本管理表结构演进，避免手工 `ALTER TABLE`。

### 10.1 迁移文件结构

命名约定：`{YYYY}_{MM}_{DD}_{HHMMSS}_{动作}_{表}_table.php`，类名由文件名推导（去时间戳，转大驼峰），如
`2026_01_01_000000_create_supplier_table.php` → 类 `CreateSupplierTable`。

```php
<?php
use KunJing\Framework\Database\Migrations\Migration;
use KunJing\Framework\Database\Schema\Blueprint;

class CreateSupplierTable extends Migration
{
    public function up(): void
    {
        $this->create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->comment('供应商名称');
            $table->string('phone', 30)->nullable();
            $table->decimal('credit', 10, 2)->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
            $table->index('phone');
            $table->unique('name');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('supplier');
    }
}
```

### 10.2 Blueprint 字段类型

`id()` / `increments()` / `string(len)` / `char()` / `text()` / `mediumText()` / `longText()` /
`integer()` / `bigInteger()` / `smallInteger()` / `tinyInteger()` / `decimal(p,s)` / `float()` /
`double()` / `boolean()` / `date()` / `dateTime()` / `time()` / `timestamp()` / `json()`。

字段修饰链（返回 `Column`）：`->nullable()` `->default($v)` `->comment('...')` `->unsigned()` `->autoIncrement()` `->primary()` `->unique()` `->index()`。

表级：`engine('InnoDB')` `charset(...)`；`timestamps()` 生成 `created_at/updated_at`；`softDeletes()` 生成 `delete_time`。
索引：`index($cols)` `unique($cols)` `primary($cols)`。

### 10.3 迁移执行（命令行）

```bash
php kunjing migrate              # 执行所有待迁移（按文件名升序，记录批次）
php kunjing migrate:rollback     # 回滚最近一批
php kunjing migrate:status       # 查看每个迁移是否已执行
php kunjing db:seed              # 执行数据填充（见第 11 节）
```

> 执行器在数据库建 `migrations` 表记录已跑文件与批次；已执行的不会重复跑。

---

## 11. 数据填充（Seeder）

```php
<?php
use KunJing\Framework\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $this->insert('supplier', [
            ['name' => '示例供应商', 'phone' => '13800138000', 'status' => 1],
        ]);
        // 调用其他 Seeder：$this->call([OtherSeeder::class]);
        // 清空重置：$this->truncate('supplier');
    }
}
```

执行：`php kunjing db:seed`（或代码里 `new Migrator(...)->...` 后调用）。

---

## 12. 代码生成器（make:*）

> 约定：生成的文件默认落在 `app/Controllers`、`app/Models`、`app/Middleware`、`app/Requests`、`database/migrations`、`database/seeders`。可在 `config/app.php` 调整这些路径，或命令用 `--force` 覆盖已存在文件。

| 命令 | 说明 |
|------|------|
| `php kunjing make:controller Foo` | 生成控制器（默认含 `index`） |
| `php kunjing make:controller Foo --resource` | 生成 7 个 REST 方法骨架 |
| `php kunjing make:model Foo --table=foo` | 生成模型（指定表名，否则蛇形推导） |
| `php kunjing make:middleware Auth` | 生成中间件骨架 |
| `php kunjing make:request StoreSupplier` | 生成表单请求骨架 |
| `php kunjing make:migration create_bar_table` | 生成迁移文件（带入表名占位） |
| `php kunjing make:seeder Supplier` | 生成填充器骨架 |

### 12.1 自定义命令

在项目根建 `console_commands.php` 返回闭包即可注册自有命令：

```php
<?php
use KunJing\Framework\Console\Application;

return function (Application $app) {
    $app->add(new \App\Commands\SendReportCommand());
};
```

---

## 13. 控制台内核（Kernel）

`KunJing\Framework\Console\Kernel` 统一加载项目配置（`config/*`）、路由（`routes.php`）、数据库，并注册全部内置命令。入口脚本 `kunjing` 直接 `new Kernel()->handle($argv)`。

- 版本：`Kernel::VERSION`（当前 `1.1.0`）。
- 配置优先级：`config/` 目录分文件 > 根 `config.php`。
- 数据库：读取 `db.connections` 与 `db.default`，供 `migrate`/`db:seed` 使用。

---

## 14. 端到端校验清单（Phase 6）

本手册所述能力均有自动化测试覆盖（`tests/phase6.php`，**105 条断言全部通过**）：

1. 注解路由注册 / 类级 `#[Prefix]` `#[Middleware]` / 参数提取 / `#[Where]` 约束 / 命名路由 + `url()` 生成；
2. 路由缓存写盘 / 加载 / 刷新 / 清理；
3. 资源路由 7 条生成与匹配；
4. Request 增强（ip / userAgent / bearerToken / wantsJson / isPost / json / file / hasFile）；
5. UploadedFile 保存与错误提示；
6. FormRequest 校验 / 422 / 字段别名 `:attribute` 替换 / authorize 403；
7. App 集成（FormRequest 注入 + 路由参数注入 + 422 JSON）；
8. Paginator 统计 / 区间 / 翻页状态 / `render()` / 单页不渲染；
9. CORS 预检 204 + 响应头 / CSRF 令牌生成与校验 / Throttle 限流 429；
10. Blueprint 生成建表 SQL / Migrator 迁移·状态·无重复·回滚·记录清理；
11. 全部 `make:*` 代码生成器语法正确；
12. Kernel 注册命令、`route:list` / `route:cache` / `route:clear`。

---

## 15. 迁移到本框架的约定（必读）

- **模型关联**必须在模型里重写 `relations()` 返回 `['关系名' => $this->hasMany(...)]`，框架才知晓关联关系；控制器用 `with(['x'])` 预加载。
- **模型从数据库水合走 `newFromDb()`**（绕过 `$fillable`）；`$fillable` 只约束外部批量赋值，否则 `find()` 出来的模型会丢掉 id/时间戳。
- **模板默认转义**：`{$a.b}`、`{if $a.b}` 均编译为安全取值；`{$x|raw}` 才原样输出（防 XSS）。
- **ORM 全参数化绑定**，用户输入不拼接进 SQL。
- **生产环境务必 `route:cache`** 提升路由匹配性能。

---

> 维护者提示：框架为纯 PHP 自研，升级时请先跑 `php tests/run_all.php`（约 119 条断言）+ `php tests/phase6.php`（105 条）确认无回归，再发布到组梦ERP / 洛水2026 等下游项目。
