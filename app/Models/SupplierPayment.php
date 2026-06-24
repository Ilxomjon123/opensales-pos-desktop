<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierPayment extends Model
{
    /** @use HasFactory<SupplierPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'dealer_id',
        'transaction_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
