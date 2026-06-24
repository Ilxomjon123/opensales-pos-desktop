<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShopMember extends Model
{
    /** @use HasFactory<ShopMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'telegram_id',
        'customer_id',
        'name',
        'username',
        'locale',
        'is_active',
        'joined_at',
        'last_seen_at',
        'blocked_at',
        'app_linked_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'customer_id' => 'integer',
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'blocked_at' => 'datetime',
            'app_linked_at' => 'datetime',
        ];
    }

    /**
     * Savat egasi kaliti: bot bilan ulangan bo'lsa telegram_id (bot↔mobil
     * umumiy savat), aks holda mobil-only vakil uchun manfiy member id.
     * Telegram id lar doim musbat — to'qnashuv bo'lmaydi.
     */
    public function cartOwnerKey(): int
    {
        return $this->telegram_id !== null && $this->telegram_id > 0
            ? (int) $this->telegram_id
            : -$this->id;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'member_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeBlocked(Builder $query): void
    {
        $query->whereNotNull('blocked_at');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function scopeForTelegram(Builder $query, int $telegramId): void
    {
        $query->where('telegram_id', $telegramId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where('customer_id', $customerId);
    }
}
