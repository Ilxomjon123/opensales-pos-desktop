<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductUnit;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_type_id',
        'product_name',
        'product_type_name',
        'product_type_code',
        'price',
        'pack_price',
        'unit_cost',
        'pack_unit_cost',
        'qty',
        'delivered_qty',
        'delivered_pack_qty',
        'picked_qty',
        'picked_pack_qty',
        'returned_qty',
        'returned_pack_qty',
        'unit',
        'pack_size',
        'pack_qty',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'pack_price' => 'float',
            'unit_cost' => 'float',
            'pack_unit_cost' => 'float',
            'qty' => 'float',
            'delivered_qty' => 'float',
            'delivered_pack_qty' => 'integer',
            'picked_qty' => 'float',
            'picked_pack_qty' => 'integer',
            'returned_qty' => 'float',
            'returned_pack_qty' => 'integer',
            'unit' => ProductUnit::class,
            'pack_size' => 'float',
            'pack_qty' => 'integer',
        ];
    }

    public function deliveredSubtotal(): int
    {
        return $this->lineTotal((float) $this->delivered_qty, $this->delivered_pack_qty !== null ? (int) $this->delivered_pack_qty : null);
    }

    /**
     * Sklad tayyorlagan miqdor asosida hisoblangan subtotal.
     * ASSEMBLING/DELIVERING statuslarida "jami" shu summalardan hisoblanadi.
     */
    public function preparedSubtotal(): int
    {
        return $this->lineTotal(
            (float) ($this->picked_qty ?? 0),
            $this->picked_pack_qty !== null ? (int) $this->picked_pack_qty : null,
        );
    }

    /**
     * Yetkazib beruvchida hozir bo'lgan miqdor:
     * picked - delivered - returned.
     * Buyurtma DELIVERING bo'lsa delivered=0, picked'ning hammasi carry'da.
     * DELIVERED bo'lsa picked > delivered + returned bo'lsa qoldiq carry'da.
     */
    public function carryQty(): float
    {
        $picked = (float) ($this->picked_qty ?? 0);
        $delivered = (float) ($this->delivered_qty ?? 0);
        $returned = (float) ($this->returned_qty ?? 0);

        return max(0.0, $picked - $delivered - $returned);
    }

    public function carryPackQty(): int
    {
        $picked = (int) ($this->picked_pack_qty ?? 0);
        $delivered = (int) ($this->delivered_pack_qty ?? 0);
        $returned = (int) ($this->returned_pack_qty ?? 0);

        return max(0, $picked - $delivered - $returned);
    }

    /**
     * Sklad qabul qilinmagan qaytariq miqdori
     * (faqat DELIVERED/RECEIVED statusda mantiqan ahamiyatli).
     */
    public function pendingReturnQty(): float
    {
        return $this->carryQty();
    }

    public function pendingReturnPackQty(): int
    {
        return $this->carryPackQty();
    }

    public function carrySubtotal(): int
    {
        return $this->lineTotal($this->carryQty(), $this->carryPackQty() > 0 ? $this->carryPackQty() : null);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function displayName(): string
    {
        return $this->product_type_name
            ? "{$this->product_name} — {$this->product_type_name}"
            : (string) $this->product_name;
    }

    public function subtotal(): int
    {
        return $this->lineTotal((float) $this->qty, $this->pack_qty !== null ? (int) $this->pack_qty : null);
    }

    /**
     * Line total: agar blok hisoblangan bo'lsa va pack_price snapshot mavjud bo'lsa —
     * blok narxidan to'g'ridan-to'g'ri yig'amiz (lossless), qolgan loose qty per-unit narxda.
     * Aks holda: qty × per-unit narx.
     */
    private function lineTotal(float $qty, ?int $packQty): int
    {
        $packs = max(0, $packQty ?? 0);
        $packSize = max(1.0, (float) $this->pack_size);
        $price = (float) $this->price;

        if ($packs > 0 && $this->pack_price !== null && $packSize > 1) {
            $loose = max(0.0, $qty - $packs * $packSize);

            return (int) round($packs * (float) $this->pack_price + $loose * $price);
        }

        return (int) round($qty * $price);
    }

    /**
     * Bloklarga sig'magan qo'shimcha "loose" miqdor.
     * qty = pack_qty * pack_size + looseQty().
     */
    public function looseQty(): float
    {
        $packSize = max(1.0, (float) $this->pack_size);
        $packQty = max(0, (int) $this->pack_qty);

        return max(0.0, (float) $this->qty - $packQty * $packSize);
    }

    /**
     * Yetkazilgan loose miqdor:
     * delivered_qty = delivered_pack_qty * pack_size + deliveredLooseQty().
     */
    public function deliveredLooseQty(): float
    {
        $packSize = max(1.0, (float) $this->pack_size);
        $deliveredPackQty = max(0, (int) $this->delivered_pack_qty);

        return max(0.0, (float) $this->delivered_qty - $deliveredPackQty * $packSize);
    }
}
