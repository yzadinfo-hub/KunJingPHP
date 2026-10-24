<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：模型基类（净室实现，能力对标 ThinkPHP Model，ActiveRecord 风格）
 *
 * 能力：表名/主键推断、字段白名单、CRUD、时间戳、关联预加载、软删除(可选 trait)。
 * 所有查询经查询构造器参数化绑定，杜绝注入。
 */

namespace KunJing\Framework\Database\Model;

use KunJing\Framework\Database\Manager;
use KunJing\Framework\Database\Query\Builder;
use KunJing\Framework\Database\Query\Expression;
use KunJing\Framework\Exception\DbException;
use KunJing\Framework\Database\Model\Relations\Relation;
use KunJing\Framework\Database\Model\Relations\HasOne;
use KunJing\Framework\Database\Model\Relations\HasMany;
use KunJing\Framework\Database\Model\Relations\BelongsTo;
use KunJing\Framework\Database\Model\Relations\BelongsToMany;

abstract class Model
{
    /** 表名（子类需定义，缺省按短类名复数推断） */
    protected static ?string $table = null;
    protected static string $primaryKey = 'id';
    /** @var array<int,string> 可批量赋值的字段白名单 */
    protected static array $fillable = [];
    protected static bool $timestamps = true;
    protected static string $connection = 'default';

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
        if ($this->getAttribute(static::$primaryKey) !== null) {
            $this->exists = true;
        }
    }

    /**
     * 由数据库行直接构造：绕过 fillable 白名单（白名单只约束外部批量赋值）。
     * @return static
     */
    public static function newFromDb(array $row)
    {
        $model = new static();
        $model->setRawAttributes($row);
        return $model;
    }

    /**
     * 原样写入全部属性并同步「原始值」，标记为已存在。
     */
    public function setRawAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        $this->syncOriginal();
        if (($attributes[static::$primaryKey] ?? null) !== null) {
            $this->exists = true;
        }
        return $this;
    }

    /* ---------- 元信息 ---------- */

    public static function tableName(): string
    {
        if (static::$table !== null) {
            return static::$table;
        }
        $short = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short)) . 's';
    }

    public static function connectionName(): string
    {
        return static::$connection;
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    public function getTable(): string
    {
        return static::tableName();
    }

    /* ---------- 查询入口 ---------- */

    public static function newQuery(): Builder
    {
        return Manager::instance()
            ->table(static::tableName())
            ->setModel(static::class);
    }

    public static function query(): Builder
    {
        return static::newQuery();
    }

    public static function all(): array
    {
        return static::newQuery()->get();
    }

    /**
     * @return static|null
     */
    public static function find($id)
    {
        return static::newQuery()->where(static::$primaryKey, '=', $id)->first();
    }

    /**
     * @return static
     * @throws DbException
     */
    public static function findOrFail($id)
    {
        $model = static::find($id);
        if ($model === null) {
            throw new DbException('记录不存在：' . static::class . '#' . $id);
        }
        return $model;
    }

    /**
     * 静态批量创建并写库。
     * @return static
     */
    public static function create(array $attributes)
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();
        return $model;
    }

    /**
     * @param array<int,mixed> $ids
     */
    public static function destroy(array $ids): int
    {
        return static::newQuery()->whereIn(static::$primaryKey, $ids)->delete();
    }

    public static function __callStatic(string $method, array $args)
    {
        return static::newQuery()->$method(...$args);
    }

    /* ---------- 属性 ---------- */

    public function fill(array $data): static
    {
        $fillable = static::$fillable;
        foreach ($data as $key => $value) {
            if (empty($fillable) || in_array($key, $fillable, true)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getKey()
    {
        return $this->getAttribute(static::$primaryKey);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function __get(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        $rels = $this->relations();
        if (isset($rels[$key])) {
            $this->relations[$key] = $rels[$key]->getResults($this);
            return $this->relations[$key];
        }
        return null;
    }

    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    /* ---------- 关联声明 ---------- */

    /**
     * 子类重写，返回 [关系名 => 关系对象]。
     * @return array<string,Relation>
     */
    protected function relations(): array
    {
        return [];
    }

    public function hasOne(string $related, string $foreignKey = '', string $localKey = ''): Relation
    {
        $fk = $foreignKey === '' ? static::tableName() . '_id' : $foreignKey;
        $lk = $localKey === '' ? static::$primaryKey : $localKey;
        return new HasOne($this, $related, $fk, $lk);
    }

    public function hasMany(string $related, string $foreignKey = '', string $localKey = ''): Relation
    {
        $fk = $foreignKey === '' ? static::tableName() . '_id' : $foreignKey;
        $lk = $localKey === '' ? static::$primaryKey : $localKey;
        return new HasMany($this, $related, $fk, $lk);
    }

    public function belongsTo(string $related, string $foreignKey = '', string $localKey = ''): Relation
    {
        $fk = $foreignKey === '' ? (new $related())->getTable() . '_id' : $foreignKey;
        $lk = $localKey === '' ? (new $related())->getPrimaryKey() : $localKey;
        return new BelongsTo($this, $related, $fk, $lk);
    }

    public function belongsToMany(
        string $related,
        string $pivotTable = '',
        string $foreignPivotKey = '',
        string $relatedPivotKey = ''
    ): Relation {
        $pivot = $pivotTable === '' ? static::tableName() . '_' . (new $related())->getTable() : $pivotTable;
        $fpk = $foreignPivotKey === '' ? static::tableName() . '_id' : $foreignPivotKey;
        $rpk = $relatedPivotKey === '' ? (new $related())->getTable() . '_id' : $relatedPivotKey;
        return new BelongsToMany($this, $related, $pivot, $fpk, $rpk, static::$primaryKey);
    }

    /**
     * 预加载关联（供 Builder 调用）。
     * @param array<int,Model> $models
     * @param array<int,string> $names
     */
    public static function eagerLoad(array $models, array $names): void
    {
        foreach ($names as $name) {
            foreach ($models as $model) {
                if ($model instanceof Model && !array_key_exists($name, $model->relations)) {
                    $rels = $model->relations();
                    if (isset($rels[$name])) {
                        $model->relations[$name] = $rels[$name]->getResults($model);
                    }
                }
            }
        }
    }

    /* ---------- 持久化 ---------- */

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    public function save(): bool
    {
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $this->setAttribute('created_at', $now);
            }
            $this->setAttribute('updated_at', $now);
        }
        $data = $this->getDirty();
        $query = static::newQuery();
        if ($this->exists) {
            $query->where(static::$primaryKey, '=', $this->getKey())->update($data);
        } else {
            $id = $query->insertGetId($data);
            $this->setAttribute(static::$primaryKey, $id);
            $this->exists = true;
        }
        $this->syncOriginal();
        return true;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(array $data): bool
    {
        $this->fill($data);
        return $this->save();
    }

    public function delete(): int
    {
        if (!$this->exists) {
            return 0;
        }
        return static::newQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete();
    }

    /* ---------- 序列化 ---------- */

    public function toArray(): array
    {
        $arr = $this->attributes;
        foreach ($this->relations as $key => $value) {
            if ($value instanceof Model) {
                $arr[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $arr[$key] = array_map(
                    fn($m) => $m instanceof Model ? $m->toArray() : $m,
                    $value
                );
            }
        }
        return $arr;
    }
}
