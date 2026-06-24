<?php

declare(strict_types=1);

namespace App\Support\Dto;

use JsonSerializable;

final readonly class CartItem implements JsonSerializable
{
    public function __construct(
        public int $productId,
        public string $productName,
        public float $price,
        public float $qty,
        public string $unit = 'dona',
        public float $packSize = 1.0,
        public ?int $packQty = null,
        public bool $bulkOnly = false,
        public ?int $productTypeId = null,
        public ?string $productTypeName = null,
        public ?string $productTypeCode = null,
        public ?float $packPrice = null,
    ) {}

    public function key(): string
    {
        return $this->productId.':'.($this->productTypeId ?? 0);
    }

    public static function makeKey(int $productId, ?int $productTypeId): string
    {
        return $productId.':'.($productTypeId ?? 0);
    }

    public function subtotal(): int
    {
        $packs = max(0, $this->packQty ?? 0);

        if ($packs > 0 && $this->packPrice !== null && $this->packSize > 1) {
            $loose = max(0.0, $this->qty - $packs * $this->packSize);

            return (int) round($packs * $this->packPrice + $loose * $this->price);
        }

        return (int) round($this->price * $this->qty);
    }

    /**
     * Bloklarga sig'magan qo'shimcha "loose" miqdor.
     * qty = packQty * packSize + looseQty().
     */
    public function looseQty(): float
    {
        $packQty = $this->packQty ?? 0;

        return max(0.0, $this->qty - $packQty * $this->packSize);
    }

    public function withQty(float $qty, ?int $packQty = null): self
    {
        return new self(
            productId: $this->productId,
            productName: $this->productName,
            price: $this->price,
            qty: $qty,
            unit: $this->unit,
            packSize: $this->packSize,
            packQty: $packQty,
            bulkOnly: $this->bulkOnly,
            productTypeId: $this->productTypeId,
            productTypeName: $this->productTypeName,
            productTypeCode: $this->productTypeCode,
            packPrice: $this->packPrice,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            productName: (string) $data['product_name'],
            price: (float) $data['price'],
            qty: (float) $data['qty'],
            unit: (string) ($data['unit'] ?? 'dona'),
            packSize: (float) ($data['pack_size'] ?? 1),
            packQty: isset($data['pack_qty']) ? (int) $data['pack_qty'] : null,
            bulkOnly: (bool) ($data['bulk_only'] ?? false),
            productTypeId: isset($data['product_type_id']) ? (int) $data['product_type_id'] : null,
            productTypeName: isset($data['product_type_name']) ? (string) $data['product_type_name'] : null,
            productTypeCode: isset($data['product_type_code']) ? (string) $data['product_type_code'] : null,
            packPrice: isset($data['pack_price']) && $data['pack_price'] !== null ? (float) $data['pack_price'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'price' => $this->price,
            'pack_price' => $this->packPrice,
            'qty' => $this->qty,
            'unit' => $this->unit,
            'pack_size' => $this->packSize,
            'pack_qty' => $this->packQty,
            'bulk_only' => $this->bulkOnly,
            'product_type_id' => $this->productTypeId,
            'product_type_name' => $this->productTypeName,
            'product_type_code' => $this->productTypeCode,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
