<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dillerning tizim (platforma) uchun to'lagan summalari.
 */
final class PlatformPayment extends Model
{
    protected $fillable = [
        'dealer_id',
        'currency',
        'amount',
        'discount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'integer',
            'discount' => 'integer',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}
