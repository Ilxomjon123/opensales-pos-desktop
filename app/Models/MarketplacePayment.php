<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Database\Factories\MarketplacePaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dillerlararo ledger yozuvi (har moliyaviy harakat).
 *   type=DEBIT  — buyurtma yetkazildi, buyer qarzi ortdi
 *   type=CREDIT — buyer to'lov qildi, qarz kamaydi
 */
final class MarketplacePayment extends Model
{
    /** @use HasFactory<MarketplacePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'seller_dealer_id',
        'buyer_dealer_id',
        'order_id',
        'currency',
        'amount',
        'type',
        'method',
        'cardholder_name',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'integer',
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'seller_dealer_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'buyer_dealer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeCredits(Builder $query): void
    {
        $query->where('type', PaymentType::CREDIT);
    }

    public function scopeDebits(Builder $query): void
    {
        $query->where('type', PaymentType::DEBIT);
    }
}
