<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：多对多关联（净室实现，经中间表）
 */

namespace KunJing\Framework\Database\Model\Relations;

use KunJing\Framework\Database\Model\Model;

class BelongsToMany extends Relation
{
    public function __construct(
        Model $parent,
        string $related,
        protected string $pivotTable,
        protected string $foreignPivotKey,
        protected string $relatedPivotKey,
        string $localKey = 'id'
    ) {
        parent::__construct($parent, $related, $foreignPivotKey, $localKey);
    }

    public function getResults(Model $parent): array
    {
        $rows = $this->query()
            ->join($this->pivotTable, $this->relatedTable() . '.' . $this->localKey, '=', $this->pivotTable . '.' . $this->relatedPivotKey)
            ->where($this->pivotTable . '.' . $this->foreignPivotKey, '=', $parent->getAttribute('id'))
            ->select($this->relatedTable() . '.*')
            ->get();
        return $this->hydrate($rows);
    }
}
