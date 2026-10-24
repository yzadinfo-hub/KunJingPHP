<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：集合（净室实现，实现常用集合操作接口）
 */

namespace KunJing\Framework\Support;

class Collection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /** @var array<int|string, mixed> */
    protected array $items;

    /** @param array<int|string, mixed> $items */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function map(callable $fn): self
    {
        return new self(array_map($fn, $this->items));
    }

    public function filter(callable $fn): self
    {
        return new self(array_filter($this->items, $fn));
    }

    public function each(callable $fn): self
    {
        foreach ($this->items as &$value) {
            $fn($value);
        }
        return $this;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function first()
    {
        return $this->items[array_key_first($this->items)] ?? null;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }
}
