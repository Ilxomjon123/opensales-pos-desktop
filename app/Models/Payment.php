<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'dealer_id',
        'order_id',
        'shift_id',
        'currency',
        'amount',
        'type',
        'method',
        'cardholder_name',
        'deliveryman_id',
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

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deliveryman_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    public function scopeCredits(Builder $query): void
    {
        $query->where('type', PaymentType::CREDIT);
    }

    public function scopeDebits(Builder $query): void
    {
        $query->where('type', PaymentType::DEBIT);
    }

    public function scopeCourierCash(Builder $query): void
    {
        $query->where('method', PaymentMethod::CASH)
            ->where('type', PaymentType::CREDIT)
            ->whereNotNull('deliveryman_id');
    }

    public function scopeForShift(Builder $query, int $shiftId): void
    {
        $query->where('shift_id', $shiftId);
    }
}
