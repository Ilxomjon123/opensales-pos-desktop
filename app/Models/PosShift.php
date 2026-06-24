<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosShiftStatus;
use Database\Factories\PosShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PosShift extends Model
{
    /** @use HasFactory<PosShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'cashier_user_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_diff',
        'total_sales',
        'total_cash',
        'total_card',
        'total_debt',
        'sales_count',
        'opening_note',
        'closing_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'closing_cash' => 'integer',
            'expected_cash' => 'integer',
            'cash_diff' => 'integer',
            'total_sales' => 'integer',
            'total_cash' => 'integer',
            'total_card' => 'integer',
            'total_debt' => 'integer',
            'sales_count' => 'integer',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === PosShiftStatus::OPEN;
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', PosShiftStatus::OPEN);
    }

    public function scopeClosed(Builder $query): void
    {
        $query->where('status', PosShiftStatus::CLOSED);
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }

    public function scopeForCashier(Builder $query, int $cashierUserId): void
    {
        $query->where('cashier_user_id', $cashierUserId);
    }
}
