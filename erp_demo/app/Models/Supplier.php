<?php
/**
 * 组梦ERP 迁移样例 — 供应商模型
 */

namespace Erp\Models;

use KunJing\Framework\Database\Model\Model;
use KunJing\Framework\Database\Model\Relations\Relation;

class Supplier extends Model
{
    protected static ?string $table = 'supplier';
    protected static array $fillable = ['name', 'contact', 'phone', 'address', 'status'];
    protected static bool $timestamps = true;

    protected function relations(): array
    {
        return [
            'goods' => $this->hasMany(Goods::class, 'supplier_id', 'id'),
        ];
    }

    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    public function isActive(): bool
    {
        return (int) $this->getAttribute('status') === self::STATUS_ACTIVE;
    }
}
