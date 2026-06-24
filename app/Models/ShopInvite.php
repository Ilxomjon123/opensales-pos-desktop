<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopInviteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ShopInvite extends Model
{
    /** @use HasFactory<ShopInviteFactory> */
    use HasFactory;

    public const DEFAULT_TTL_HOURS = 24;

    protected $fillable = [
        'shop_id',
        'created_by',
        'token',
        'expires_at',
        'used_at',
        'used_by_telegram_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'used_by_telegram_id' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public static function generateToken(): string
    {
        return 'inv_'.Str::random(24);
    }
}
