<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MarketplaceBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dillerlararo (marketplace) saldo, `dealer_id` nuqtai nazaridan:
 *   musbat = hamkor menga qarzdor (haqdorlik)
 *   manfiy = men hamkorga qarzdorman (qarzdorlik)
 */
final class MarketplaceBalance extends Model
{
    /** @use HasFactory<MarketplaceBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'partner_dealer_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'partner_dealer_id');
    }
}
