# 组梦ERP 备份与迁移指南（堃景PHP / KunJingPHP）

> 原则：**先备份、能还原、再动手**。任何结构性改造或框架迁移前，必须完成一次「备份 + 还原演练」。

---

## 一、三条铁律

1. **备份先行**：改代码前先备份数据库 + 源码，且备份落在**与源目录不同的磁盘/机器**上。
2. **还原演练**：备份不等于可恢复。必须把备份还原到临时库/临时目录验证一次，再动生产。
3. **可回滚**：迁移采用「新模块并行 + 开关切换」，保留旧代码至少 2 个版本周期，随时可切回。

---

## 二、本机样例应用备份（已内置工具）

```bash
php erp_demo/backup.php              # 数据库 + 源码，默认保留最近 10 份
php erp_demo/backup.php --only=db    # 仅数据库
php erp_demo/backup.php --only=code  # 仅源码
php erp_demo/backup.php --keep=5     # 保留最近 5 份
php erp_demo/backup.php --list       # 查看已有备份
```

- 产物：`erp_demo/backups/db_<时间戳>.sql`、`code_<时间戳>/`、`manifest_<时间戳>.json`
- 数据库导出为**纯 PHP 实现**，不依赖 `mysqldump`；源码备份为**递归复制**，不依赖 `tar`（本机 tar.exe 被安全策略拦截）
- 还原 SQL：`mysql -u root -p kunjing_erp < backups/db_20260924_163131.sql`

---

## 三、生产服务器（组梦ERP）备份

以服务器 `43.139.8.3`（项目 `/www/wwwroot/www.zumengai.cn`）为例，请按实际账号/库名替换。

### 3.1 建立备份目录

```bash
ssh ubuntu@43.139.8.3
sudo mkdir -p /data/backup/zumeng && sudo chown -R $USER /data/backup/zumeng
```

### 3.2 备份数据库

```bash
STAMP=$(date +%F_%H%M%S)
mysqldump -uroot -p \
  --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 zumeng_db > /data/backup/zumeng/db_${STAMP}.sql
gzip /data/backup/zumeng/db_${STAMP}.sql
ls -lh /data/backup/zumeng/
```

- `--single-transaction` 保证 InnoDB 一致性快照，**不锁表**；
- 大数据库可加 `--where` 分批导出，或按表分别导出。

### 3.3 备份源码

```bash
STAMP=$(date +%F_%H%M%S)
cd /www/wwwroot
tar -czf /data/backup/zumeng/code_${STAMP}.tar.gz \
    --exclude='www.zumengai.cn/runtime/cache/*' \
    --exclude='www.zumengai.cn/.git/*' \
    www.zumengai.cn
```

### 3.4 校验与异地留存

```bash
# 体积与完整性
ls -lh /data/backup/zumeng/ && md5sum db_*.sql.gz code_*.tar.gz > /data/backup/zumeng/CHECKSUM.txt

# 还原演练（到本地临时库，验证备份真的可用）
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS zumeng_restore_test"
zcat db_${STAMP}.sql.gz | mysql -uroot -p zumeng_restore_test
mysql -uroot -p -e "SELECT COUNT(*) FROM zumeng_restore_test.<主表名>;"

# 异地留存
rsync -avz /data/backup/zumeng/ user@backup-host:/backup/zumeng/
```

### 3.5 定期自动备份（crontab）

```bash
0 3 * * * /bin/bash -c 'STAMP=$(date +\%F_\%H\%M\%S); mysqldump -uroot -p<密码> --single-transaction zumeng_db | gzip > /data/backup/zumeng/db_${STAMP}.sql.gz; find /data/backup/zumeng -name "db_*.sql.gz" -mtime +14 -delete'
```

---

## 四、把一个 ERP 模块迁到 KunJingPHP（以「供应商管理」为例）

| 步骤 | 动作 | 验收 |
|------|------|------|
| 1 | 选定小模块（表单 + 列表 + 增删改查，依赖越少越好） | 模块边界清晰 |
| 2 | **备份数据库 + 源码，并做还原演练** | 备份可恢复 ✅ |
| 3 | 建 KunJingPHP 应用骨架（参考 `erp_demo/`） | 能起服务 |
| 4 | 迁移表结构 → 建 `Erp\Models\*` 模型（`$table`/`$fillable`/`relations()`） | ORM 读写通过 |
| 5 | 迁控制器与路由（含中间件鉴权） | 路由可达 |
| 6 | 迁视图（模板引擎 `{$var}` / `{foreach}` / `{extend}`） | 页面渲染一致 |
| 7 | 数据校验：新旧两套按主键比对条数 + 抽样 50 条字段级比对 | 零差异 |
| 8 | 灰度：新模块挂子路径并行运行，观察日志与性能 | 无异常 |
| 9 | 切换流量 → 保留旧代码 → 观察 1~2 个版本周期 | 可回滚 |

### 迁移对照（ThinkPHP 8 → 堃景PHP）

| 能力 | TP8 形态（仅参考） | 堃景PHP 形态 |
|------|------------------|-------------|
| 查询 | `Db::name('supplier')->where(...)->select()` | `Supplier::query()->where(...)->get()` |
| 模型 | `think\Model` | `KunJing\Framework\Database\Model\Model` |
| 门面 | `think\facade\Cache` 等 | `KunJing\Framework\Facade\Cache` 等 |
| 验证 | `think\Validate` | `KunJing\Framework\Validate\Validator` |
| 模板 | ThinkTemplate | `KunJing\Framework\View\Engine`（语法自研） |
| 命令 | `php think` | `php kunjing` |

---

## 五、回滚方案

1. **代码回滚**：解压 `code_<时间戳>.tar.gz` 覆盖原目录，清 runtime 缓存；
2. **数据回滚**：用备份 SQL 还原到指定库（先备份当前库，避免二次事故）；
3. **双开关**：迁移期保留旧入口，出问题直接把流量切回旧路径，做到分钟级回滚。

---

## 六、迁移前检查清单

- [ ] 数据库备份完成且**还原演练成功**
- [ ] 源码备份完成（含 `.env`、配置文件）
- [ ] 备份已异地留存
- [ ] 明确模块边界与依赖（不要一次迁整个 ERP）
- [ ] 新旧数据比对脚本就绪
- [ ] 回滚方案已演练、负责人与联系方式确认
- [ ] 低峰期执行，变更后观察日志（业务日志 + PHP 错误日志）
