<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BotVisibility;
use App\Enums\CommissionType;
use App\Enums\Currency;
use App\Enums\OrderChannel;
use App\Enums\UserRole;
use Database\Factories\DealerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class Dealer extends Model
{
    /** @use HasFactory<DealerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'currency',
        'country_id',
        'bot_token',
        'bot_username',
        'bot_display_name',
        'bot_short_description',
        'bot_description',
        'contact_phone',
        'warehouse_latitude',
        'warehouse_longitude',
        'warehouse_address',
        'warehouse_map_provider',
        'telegram_chat_id',
        'owner_link_token',
        'onboarding_completed_at',
        'is_active',
        'is_self_registered',
        'trial_ends_at',
        'visibility',
        'webhook_set_at',
        'webhook_checked_at',
        'webhook_pending_updates',
        'webhook_last_error_message',
        'webhook_last_error_at',
        'webhook_url',
        'platform_fee_rate',
        'commission_type',
        'fixed_commission_amount',
        'min_order_amount',
        'show_out_of_stock',
        'notify_on_price_change',
        'notify_on_new_product',
        'sells_on_marketplace',
        'marketplace_commission_type',
        'marketplace_platform_fee_rate',
        'marketplace_fixed_commission_amount',
        'marketplace_min_order_amount',
    ];

    protected $hidden = ['bot_token'];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'country_id' => 'integer',
            'is_active' => 'boolean',
            'is_self_registered' => 'boolean',
            'trial_ends_at' => 'datetime',
            'visibility' => BotVisibility::class,
            'telegram_chat_id' => 'integer',
            'onboarding_completed_at' => 'datetime',
            'warehouse_latitude' => 'float',
            'warehouse_longitude' => 'float',
            'webhook_set_at' => 'datetime',
            'webhook_checked_at' => 'datetime',
            'webhook_last_error_at' => 'datetime',
            'webhook_pending_updates' => 'integer',
            'platform_fee_rate' => 'decimal:2',
            'commission_type' => CommissionType::class,
            'fixed_commission_amount' => 'integer',
            'min_order_amount' => 'integer',
            'show_out_of_stock' => 'boolean',
            'notify_on_price_change' => 'boolean',
            'notify_on_new_product' => 'boolean',
            'sells_on_marketplace' => 'boolean',
            'marketplace_commission_type' => CommissionType::class,
            'marketplace_platform_fee_rate' => 'decimal:2',
            'marketplace_fixed_commission_amount' => 'integer',
            'marketplace_min_order_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::created(function (self $dealer): void {
            $dealer->commissionPeriods()->create([
                'commission_type' => $dealer->commission_type ?? CommissionType::TURNOVER_PERCENTAGE,
                'fixed_commission_amount' => $dealer->fixed_commission_amount,
                'starts_at' => $dealer->created_at ?? now(),
                'ends_at' => null,
            ]);
        });
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DealerDeliveryZone::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Marketplace orqali bu diller SOTGAN buyurtmalar (sotuvchi sifatida).
     */
    public function marketplaceSales(): HasMany
    {
        return $this->hasMany(Order::class)->where('channel', OrderChannel::MARKETPLACE->value);
    }

    /**
     * Marketplace orqali bu diller SOTIB OLGAN buyurtmalar (xaridor sifatida).
     */
    public function marketplacePurchases(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_dealer_id');
    }

    public function marketplaceBalances(): HasMany
    {
        return $this->hasMany(MarketplaceBalance::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function platformPayments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Diller tashkilotining rahbari (dealer roli — UI'da "Owner").
     * Login ma'lumotlari shu foydalanuvchida saqlanadi, xodimlarda emas.
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', UserRole::DEALER);
    }

    public function commissionPeriods(): HasMany
    {
        return $this->hasMany(DealerCommissionPeriod::class);
    }

    public function currentCommissionPeriod(): HasOne
    {
        return $this->hasOne(DealerCommissionPeriod::class)
            ->whereNull('ends_at')
            ->latestOfMany('starts_at');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function effectiveBotDisplayName(): string
    {
        return $this->bot_display_name !== null && $this->bot_display_name !== ''
            ? $this->bot_display_name
            : (string) $this->name;
    }

    public function effectiveBotShortDescription(): string
    {
        return $this->bot_short_description !== null && $this->bot_short_description !== ''
            ? $this->bot_short_description
            : (string) __('bot.profile_default.short', ['name' => $this->name]);
    }

    public function effectiveBotDescription(): string
    {
        return $this->bot_description !== null && $this->bot_description !== ''
            ? $this->bot_description
            : (string) __('bot.profile_default.description');
    }

    public function isPublic(): bool
    {
        return ($this->visibility ?? BotVisibility::PRIVATE) === BotVisibility::PUBLIC;
    }

    public function isMarketplaceSeller(): bool
    {
        return (bool) $this->sells_on_marketplace;
    }

    public function hasBot(): bool
    {
        return $this->bot_token !== null && $this->bot_token !== '';
    }

    /**
     * Yangi diller hali onboarding'ni tugatmagan — dashboard'da checklist ko'rsatamiz.
     */
    public function needsOnboarding(): bool
    {
        return $this->onboarding_completed_at === null;
    }

    /**
     * Diller hali bepul sinov muddatida.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Sinov muddati tugagan (lekin trial belgilangan edi).
     */
    public function trialExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * Sinovgacha qolgan kunlar (yaxlitlangan, manfiy bo'lmaydi).
     */
    public function trialDaysLeft(): int
    {
        if ($this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInDays($this->trial_ends_at, false)));
    }

    /**
     * Bildirishnoma chatini ulash uchun bir martalik /start kodi.
     * Prefiks bilan shop-invite token'laridan farqlanadi.
     */
    public function ensureOwnerLinkToken(): string
    {
        if ($this->owner_link_token === null) {
            $this->forceFill(['owner_link_token' => 'own_'.Str::random(28)])->save();
        }

        return (string) $this->owner_link_token;
    }
}
