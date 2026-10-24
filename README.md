# 堃景PHP / KunJingPHP

> 净室自研 PHP 框架 —— 能力对标 ThinkPHP 8.1，**代码与命名全部自主实现**，MIT 协议，零外部依赖。
> 用途：团队今后所有自研项目（洛水2026、组梦ERP 等）的底座框架。

- 中文名：**堃景PHP**　英文名：**KunJingPHP**
- 命名空间：`KunJing\Framework\*`　包名：`kunjing/kunjingphp`　CLI：`php kunjing`
- 运行要求：PHP ≥ 8.1（本机验证环境 PHP 8.3.33 + MySQL 8.x）

> 法律边界：不复制任何第三方框架源码，名称、命名空间、实现均为自有。仅保证「能力等价」，不保证与任何框架调用方式一致。

---

## 一、当前进度

| 阶段 | 内容 | 状态 | 测试 |
|------|------|------|------|
| Phase 0 | 内核：PSR-4 自动加载 / IoC 容器 DI / 配置 / 异常 / 集合 | ✅ | 16/16 |
| Phase 1 | 请求链路：Request / Response / 路由 / 中间件洋葱管道 / 门面 / App | ✅ | 端到端通过 |
| Phase 2 | 数据层 ORM：连接管理 / 查询构造器 / 模型 / 关联 / 事务 / 分页 | ✅ | 17/17 |
| Phase 3 | 服务组件：缓存 / 日志 / 验证器 / 事件 / 会话 / 命令行 | ✅ | 38/38 |
| Phase 4 | 视图：自研模板引擎（继承 / 区块 / 编译缓存 / 默认转义） | ✅ | 20/20 |
| Phase 5 | 实战：组梦ERP「供应商+商品」小模块闭环迁移 | ✅ | 28/28 |
| Phase 6 | 生产化能力：注解路由 / 路由缓存 / 资源路由 / 命名路由 / 文件上传 / FormRequest / 分页器 / CORS·CSRF·Throttle / 迁移 / 填充 / make:* 生成器 | ✅ | 105/105 |

全量回归：`php tests/run_all.php` + `php tests/phase6.php` → **全部通过**（约 224 条断言）。

---

## 二、目录结构

```
kunjingphp/
├── src/KunJing/Framework/
│   ├── Container/         IoC 容器与依赖注入
│   ├── Config/            点号路径配置仓库
│   ├── Routing/           路由（配置注册 + 分组 + 参数 + 正则约束 + **注解属性** + 缓存 + 资源/命名路由）
│   │   └── Attributes/    Route/Get/Post/Put/Delete/Patch/Any/Prefix/Middleware/Where
│   ├── Http/              Request / Response / 中间件管道
│   │   ├── Middleware/    CorsMiddleware / CsrfMiddleware / ThrottleMiddleware / Pipeline
│   │   └── UploadedFile.php  文件上传封装
│   ├── Database/
│   │   ├── Connector/     MySQL / SQLite / PgSQL 连接器
│   │   ├── Query/         查询构造器 + 原生表达式
│   │   ├── Model/         模型基类 + 关联(HasOne/HasMany/BelongsTo/BelongsToMany)
│   │   ├── Schema/        Blueprint / Column 建表 DSL
│   │   └── Migrations/    Migration 基类 + Migrator 执行器
│   ├── Cache/             Array / File / Redis 多驱动 + remember
│   ├── Log/               单文件 / 按日通道 + 级别阈值
│   ├── Validate/          规则体系（含 unique 连库、自定义闭包）+ **FormRequest**
│   ├── Event/             事件调度（优先级 / once / halt）
│   ├── Session/           自有会话（File / Array）+ 闪存
│   ├── View/              模板引擎（编译为 PHP 缓存）
│   ├── Support/           集合 / 分页器 Paginator / 自动加载
│   ├── Console/           命令行（Kernel / Input / Output / Command / Application）
│   │   └── Commands/      route:* / make:* / migrate / db:seed 等内置命令
│   ├── Database/          Seeder.php 数据填充基类
│   └── Facade/            Db / Cache / Log / Validate / Event / Session / View
├── erp_demo/              组梦ERP 小模块迁移样例（含备份工具）
├── demo/                  Phase 1 最小示例
├── docs/                  《堃景PHP 开发手册 · Phase 6 生产化能力》
└── tests/                 smoke / e2e / phase2~6 / run_all
```

---

## 三、快速开始

### 1) 跑通 ERP 样例（推荐，最能体现全栈能力）

```bash
php erp_demo/install.php              # 建库 kunjing_erp + 建表 + 示例数据
php -S 127.0.0.1:8800 -t erp_demo     # 起服务
# 浏览器打开 http://127.0.0.1:8800   账号 admin / 123456
```

### 2) 最小示例

```bash
php -S 127.0.0.1:8801 -t demo
# 首页 /hello/{name} /user/{id} /secure(需头 X-Token: secret) /demo /api/status
```

### 3) 命令行

```bash
php kunjing version      # 版本
php kunjing env:check    # 环境自检（数据库/缓存/配置）

# 代码生成
php kunjing make:controller Supplier --resource   # 生成 REST 控制器
php kunjing make:model Supplier --table=supplier  # 生成模型
php kunjing make:migration create_supplier_table  # 生成迁移
php kunjing make:request StoreSupplier            # 生成表单请求
php kunjing make:middleware Auth                  # 生成中间件
php kunjing make:seeder Supplier                  # 生成填充器

# 数据库与路由
php kunjing migrate          # 执行迁移
php kunjing migrate:rollback # 回滚最近一批
php kunjing migrate:status   # 迁移状态
php kunjing db:seed          # 数据填充
php kunjing route:list       # 列出路由
php kunjing route:cache      # 生成路由缓存（生产加速）
php kunjing route:clear      # 清理路由缓存
```

### 4) 全量回归

```bash
php tests/run_all.php       # Phase 0~5 回归（约 119 断言）
php tests/phase6.php        # Phase 6 专项（105 断言）
```

---

## 四、核心用法速查

```php
// —— ORM ——
use Erp\Models\Supplier;
$list = Supplier::query()->where('status', 1)->orderBy('id','desc')->paginate(10, 1);
$one  = Supplier::find(3);
$one->update(['contact' => '李四']);
$one->goods;                            // hasMany 关联
Supplier::with(['goods'])->get();       // 预加载，避免 N+1

Manager::instance()->transaction(fn() => Supplier::create(['name'=>'A','status'=>1]));

// —— 门面 ——
Cache::remember('supplier_total', 30, fn() => Supplier::query()->count());
Log::info('新增供应商', ['id' => 1]);
Session::flash('msg', '保存成功');
Event::listen('order.paid', fn($order) => Log::info('paid'));
View::render('supplier.index', ['list' => $list]);

// —— 验证器 ——
Validator::check($data, [
    'name'  => 'required|min:2|max:50|unique:supplier,name',
    'phone' => 'regex:/^[0-9+\-()]{6,25}$/',
]);  // 失败抛 ValidateException，getErrors() 取字段级错误
```

模板语法（默认 HTML 转义，防 XSS）：

```html
{extend 'layout'}
{block title}供应商管理{/block}
{block content}
  {if $msg}<div class="alert">{$msg}</div>{/if}
  {foreach $list as $s}
    <tr><td>{$s.name}</td><td>{if $s.status == 1}启用{else}停用{/if}</td></tr>
  {/foreach}
{/block}
```

---

## 四·五、Phase 6 生产化能力速查

> 完整文档见 **`docs/堃景PHP开发手册-Phase6.md`**。

```php
// —— 注解路由（PHP 8 原生 Attribute）——
#[Prefix('/api/v1')]
#[Middleware([AuthMiddleware::class])]
class UserController {
    #[Get('/users/{id}')]
    #[Where(['id' => '[0-9]+'])]
    public function show($id) { /* ... */ }
}
$app->router()->attributes([UserController::class]);

// —— 命名路由 + URL 生成 ——
$router->get('/goods/{id}', [...])->name('goods.show');
$url = $router->url('goods.show', ['id' => 5]);   // /goods/5

// —— 表单请求（控制器方法声明类型即自动校验）——
public function store(SupplierRequest $request) {
    $data = $request->validated();                // 仅含 rules 字段
}
// 校验失败框架自动返回 422 {code,message,errors:{字段:提示}}
// 自定义别名：SupplierRequest::attributes() 返回 ['phone'=>'联系电话']
// 自定义提示：messages() 返回 ['phone.required'=>':attribute不能为空…']

// —— 文件上传 ——
$file = $request->file('avatar');
$saved = $file->store($basePath.'/public/uploads', 'avatar.png');

// —— 分页器 ——
$pager = Paginator::make(Goods::query()->paginate(15, $page));
echo $pager->render();   // 自带样式，Paginator::styles() 取 CSS

// —— 内置中间件 ——
$router->post('/x', [...])->middleware(CsrfMiddleware::class);     // 防 CSRF
$router->group(['middleware'=>[CorsMiddleware::class]], ...);      // 跨域
$route->middleware(new ThrottleMiddleware(10, 1));                 // 1 分钟 10 次，超额 429

// —— 迁移 / 填充 ——
// class CreateSupplierTable extends Migration { up(){ $this->create('supplier', fn(Blueprint $t)=>[
//     $t->id(); $t->string('name',120); $t->timestamps(); ]); } down(){ $this->dropIfExists('supplier'); } }
// 命令行：php kunjing migrate / migrate:rollback / db:seed

// —— 路由缓存（生产加速）——
$router->cacheFile($base.'/runtime/route_cache.php');
$router->boot(fn($r) => $r->attributes([UserController::class]));  // 首次写缓存，之后直接读
```

---

## 五、安全约定（验收项）

1. **ORM 全部参数化绑定**，用户输入不拼接进 SQL；
2. **模板默认转义**输出，`|raw` 才原样输出；
3. 模型 `$fillable` 白名单约束批量赋值（数据库读取不受限）；
4. 会话为自有实现，登录态存服务端文件，不信任客户端数据。

---

## 六、备份（迁移/改造前必做）

样例应用自带备份工具，不依赖 `mysqldump`/`tar`（本机均无环境）：

```bash
php erp_demo/backup.php              # 数据库 + 源码，保留最近 10 份
php erp_demo/backup.php --only=db    # 仅数据库
php erp_demo/backup.php --list       # 查看已有备份
```

生产环境（组梦ERP 服务器）的完整备份与迁移流程见：**`erp_demo/BACKUP.md`**。
