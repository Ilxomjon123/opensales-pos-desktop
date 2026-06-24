<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ShopType;
use App\Observers\ShopObserver;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ShopObserver::class)]
final class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    protected $fillable = [
        'dealer_id',
        'parent_shop_id',
        'directory_id',
        'deliveryman_id',
        'type',
        'name',
        'legal_name',
        'phone',
        'address',
        'landmark',
        'region',
        'district',
        'country_id',
        'region_id',
        'district_id',
        'inn',
        'contact_person',
        'photo',
        'latitude',
        'longitude',
        'map_provider',
        'balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShopType::class,
            'balance' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function regionRef(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function districtRef(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function directory(): BelongsTo
    {
        return $this->belongsTo(DirectoryShop::class, 'directory_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_shop_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(self::class, 'parent_shop_id');
    }

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deliveryman_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ShopMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(ShopInvite::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Yetkazib berish kutilayotgan buyurtmalar — saldo hali bu summalarni
     * ko'rsatmaydi, lekin `withSum('pendingOrders as pending_total', 'total')`
     * orqali ro'yxatlarga qo'shib chiqarish mumkin.
     */
    public function pendingOrders(): HasMany
    {
        return $this->orders()->whereIn('status', [
            OrderStatus::PENDING,
            OrderStatus::ASSEMBLING,
            OrderStatus::DELIVERING,
        ]);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ShopFavorite::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ShopVisit::class);
    }

    public function hasMembers(): bool
    {
        return $this->members()->exists();
    }

    /**
     * Faolsiz mijozlar: birorta ham faol (botni bloklamagan) vakili yo'q —
     * bu "vakil yo'q" va "barcha vakil bloklagan" holatlarini qamraydi —
     * YOKI oxirgi $days kun ichida buyurtma bermagan (umuman bermaganlar ham).
     * Oxirgi $days kun ichida vizit qilingan mijoz faolsiz hisoblanmaydi —
     * vizit faollikni tiklaydi.
     */
    public function scopeInactiveCustomer(Builder $query, int $days): void
    {
        $threshold = now()->subDays($days);

        $query
            ->whereDoesntHave('visits', function (Builder $v) use ($threshold): void {
                $v->where('visited_at', '>=', $threshold);
            })
            ->where(function (Builder $q) use ($threshold): void {
                $q->whereDoesntHave('members', function (Builder $m): void {
                    $m->whereNull('blocked_at');
                })->orWhereDoesntHave('orders', function (Builder $o) use ($threshold): void {
                    $o->where('created_at', '>=', $threshold);
                });
            });
    }

    /**
     * Faol mijozlar — scopeInactiveCustomer ning teskarisi (De Morgan):
     * oxirgi $days kun ichida vizit qilingan, YOKI faol vakili bor va shu
     * oynada buyurtma bergan.
     */
    public function scopeActiveCustomer(Builder $query, int $days): void
    {
        $threshold = now()->subDays($days);

        $query->where(function (Builder $q) use ($threshold): void {
            $q->whereHas('visits', function (Builder $v) use ($threshold): void {
                $v->where('visited_at', '>=', $threshold);
            })->orWhere(function (Builder $q2) use ($threshold): void {
                $q2->whereHas('members', function (Builder $m): void {
                    $m->whereNull('blocked_at');
                })->whereHas('orders', function (Builder $o) use ($threshold): void {
                    $o->where('created_at', '>=', $threshold);
                });
            });
        });
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }

    public function scopeForDeliveryman(Builder $query, int $deliverymanId): void
    {
        $query->where('deliveryman_id', $deliverymanId);
    }

    public function scopeOfType(Builder $query, ShopType $type): void
    {
        $query->where('type', $type);
    }

    public function scopeTelegram(Builder $query): void
    {
        $query->where('type', ShopType::TELEGRAM);
    }

    public function scopePosCustomers(Builder $query): void
    {
        $query->whereIn('type', [ShopType::INDIVIDUAL, ShopType::WALK_IN]);
    }

    public function isPosCustomer(): bool
    {
        return $this->type instanceof ShopType && $this->type->isPos();
    }

    public function isWalkIn(): bool
    {
        return $this->type === ShopType::WALK_IN;
    }

    /**
     * Har bir do'kon uchun yetkazib berish zonasiga mosligini `zone_covered`
     * ustuniga yozadi (1 = mos, null = mos emas). ShopResource shu asosida
     * `outside_zone` flagini hisoblaydi. Diller zona belgilamagan bo'lsa
     * controller bu scopeni qo'llamaydi — barcha do'konlar "ichkarida".
     */
    public function scopeWithZoneCoverage(Builder $query): void
    {
        $query->addSelect(['zone_covered' => DealerDeliveryZone::query()
            ->selectRaw('1')
            ->whereColumn('dealer_delivery_zones.dealer_id', 'shops.dealer_id')
            ->whereColumn('dealer_delivery_zones.region', 'shops.region')
            ->where(fn (Builder $z) => $z
                ->whereNull('dealer_delivery_zones.district')
                ->orWhereColumn('dealer_delivery_zones.district', 'shops.district'))
            ->limit(1),
        ]);
    }

    /**
     * Faqat zonadan tashqaridagi do'konlar: hududi ma'lum, lekin diller
     * zonasiga mos kelmaydi.
     */
    public function scopeOutsideDeliveryZone(Builder $query): void
    {
        $query->whereNotNull('region')->whereNotExists(
            DealerDeliveryZone::query()
                ->whereColumn('dealer_delivery_zones.dealer_id', 'shops.dealer_id')
                ->whereColumn('dealer_delivery_zones.region', 'shops.region')
                ->where(fn (Builder $z) => $z
                    ->whereNull('dealer_delivery_zones.district')
                    ->orWhereColumn('dealer_delivery_zones.district', 'shops.district'))
                ->getQuery()
        );
    }

    public function isMainBranch(): bool
    {
        return $this->parent_shop_id === null;
    }

    /**
     * Bosh filial uchun: o'zining + barcha filiallari saldolari yig'indisi.
     * Filial yoki yakka shop uchun: o'zining saldosi.
     */
    public function totalBalanceWithBranches(): int
    {
        $own = (int) $this->balance;

        if (! $this->isMainBranch()) {
            return $own;
        }

        $branchesSum = (int) $this->branches()->sum('balance');

        return $own + $branchesSum;
    }
}
