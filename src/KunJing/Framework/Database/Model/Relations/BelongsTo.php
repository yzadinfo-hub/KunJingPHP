<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：反向一对一/多对一关联（净室实现）
 */

namespace KunJing\Framework\Database\Model\Relations;

use KunJing\Framework\Database\Model\Model;

class BelongsTo extends Relation
{
    public function getResults(Model $parent)
    {
        $fkValue = $parent->getAttribute($this->foreignKey);
        if ($fkValue === null) {
            return null;
        }
        $row = $this->query()
            ->where($this->localKey, '=', $fkValue)
            ->first();
        return $row === null ? null : $this->related::newFromDb($row);
    }
}
