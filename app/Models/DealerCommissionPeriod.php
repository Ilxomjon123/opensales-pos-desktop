<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DealerCommissionPeriod extends Model
{
    protected $fillable = [
        'dealer_id',
        'commission_type',
        'fixed_commission_amount',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'fixed_commission_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ends_at');
    }
}
