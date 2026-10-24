<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：一对多关联（净室实现）
 */

namespace KunJing\Framework\Database\Model\Relations;

use KunJing\Framework\Database\Model\Model;

class HasMany extends Relation
{
    public function getResults(Model $parent): array
    {
        $rows = $this->query()
            ->where($this->foreignKey, '=', $parent->getAttribute($this->localKey))
            ->get();
        return $this->hydrate($rows);
    }
}
