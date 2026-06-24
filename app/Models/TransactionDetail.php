<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnDisposition;
use Database\Factories\TransactionDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransactionDetail extends Model
{
    /** @use HasFactory<TransactionDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_type_id',
        'order_item_id',
        'product_name',
        'product_type_name',
        'qty',
        'pack_qty',
        'unit_cost',
        'pack_unit_cost',
        'stock_before',
        'stock_after',
        'disposition',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'pack_qty' => 'integer',
            'unit_cost' => 'float',
            'pack_unit_cost' => 'float',
            'stock_before' => 'float',
            'stock_after' => 'float',
            'disposition' => ReturnDisposition::class,
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
