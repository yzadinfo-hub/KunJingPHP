<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：一对一关联（净室实现）
 */

namespace KunJing\Framework\Database\Model\Relations;

use KunJing\Framework\Database\Model\Model;

class HasOne extends Relation
{
    public function getResults(Model $parent)
    {
        $row = $this->query()
            ->where($this->foreignKey, '=', $parent->getAttribute($this->localKey))
            ->first();
        return $row === null ? null : $this->related::newFromDb($row);
    }
}
