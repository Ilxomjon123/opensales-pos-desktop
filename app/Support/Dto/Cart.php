<?php

declare(strict_types=1);

namespace App\Support\Dto;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<string, CartItem>
 */
final class Cart implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<string, CartItem> keyed by "{productId}:{productTypeId|0}" */
    private array $items;

    /** @param iterable<CartItem> $items */
    public function __construct(iterable $items = [])
    {
        $this->items = [];
        foreach ($items as $item) {
            $this->items[$item->key()] = $item;
        }
    }

    public function add(CartItem $item): self
    {
        $existing = $this->items[$item->key()] ?? null;

        if ($existing !== null) {
            $mergedPackQty = $existing->packQty !== null && $item->packQty !== null
                ? $existing->packQty + $item->packQty
                : null;

            $merged = $item->withQty($existing->qty + $item->qty, $mergedPackQty);
        } else {
            $merged = $item;
        }

        $clone = clone $this;
        $clone->items[$item->key()] = $merged;

        return $clone;
    }

    public function remove(int $productId, ?int $productTypeId = null): self
    {
        $clone = clone $this;
        unset($clone->items[CartItem::makeKey($productId, $productTypeId)]);

        return $clone;
    }

    public function setQty(int $productId, float $qty, ?int $packQty = null, ?int $productTypeId = null): self
    {
        $key = CartItem::makeKey($productId, $productTypeId);
        $existing = $this->items[$key] ?? null;

        if ($existing === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->items[$key] = $existing->withQty($qty, $packQty);

        return $clone;
    }

    public function has(int $productId, ?int $productTypeId = null): bool
    {
        return isset($this->items[CartItem::makeKey($productId, $productTypeId)]);
    }

    public function get(int $productId, ?int $productTypeId = null): ?CartItem
    {
        return $this->items[CartItem::makeKey($productId, $productTypeId)] ?? null;
    }

    /** @return array<int, CartItem> */
    public function items(): array
    {
        return array_values($this->items);
    }

    /** @return array<int, int> unique product ids */
    public function productIds(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            $ids[$item->productId] = true;
        }

        return array_keys($ids);
    }

    /** @return array<int, int> unique product type ids */
    public function productTypeIds(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            if ($item->productTypeId !== null) {
                $ids[$item->productTypeId] = true;
            }
        }

        return array_keys($ids);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (CartItem $i): int => $i->subtotal(), $this->items));
    }

    public function totalQty(): float
    {
        return array_sum(array_map(static fn (CartItem $i): float => $i->qty, $this->items));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        yield from array_values($this->items);
    }

    public function jsonSerialize(): array
    {
        return array_values(array_map(static fn (CartItem $i): array => $i->toArray(), $this->items));
    }

    /** @param iterable<array> $raw */
    public static function fromRaw(iterable $raw): self
    {
        $items = [];
        foreach ($raw as $row) {
            $items[] = CartItem::fromArray($row);
        }

        return new self($items);
    }
}
