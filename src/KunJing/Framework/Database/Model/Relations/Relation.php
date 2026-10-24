<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：模型关联基类（净室实现）
 */

namespace KunJing\Framework\Database\Model\Relations;

use KunJing\Framework\Database\Model\Model;

abstract class Relation
{
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $localKey
    ) {
    }

    abstract public function getResults(Model $parent);

    protected function relatedTable(): string
    {
        return (new $this->related())->getTable();
    }

    protected function relatedConnectionName(): string
    {
        return (new $this->related())->connectionName();
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,Model>
     */
    protected function hydrate(array $rows): array
    {
        return array_map(fn($row) => $this->related::newFromDb($row), $rows);
    }

    protected function query()
    {
        return \KunJing\Framework\Database\Manager::instance()->table($this->relatedTable());
    }
}
