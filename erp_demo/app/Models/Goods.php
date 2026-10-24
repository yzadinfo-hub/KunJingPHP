<?php
/**
 * 组梦ERP 迁移样例 — 商品模型（演示 belongsTo）
 */

namespace Erp\Models;

use KunJing\Framework\Database\Model\Model;
use KunJing\Framework\Database\Model\Relations\Relation;

class Goods extends Model
{
    protected static ?string $table = 'goods';
    protected static array $fillable = ['supplier_id', 'name', 'sku', 'price', 'stock'];
    protected static bool $timestamps = true;

    protected function relations(): array
    {
        return [
            'supplier' => $this->belongsTo(Supplier::class, 'supplier_id', 'id'),
        ];
    }
}
