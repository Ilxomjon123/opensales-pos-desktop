<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'phone', 'password', 'role', 'dealer_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'security_locked_until' => 'datetime',
        ];
    }

    public function isSecurityLocked(): bool
    {
        return $this->security_locked_until !== null
            && $this->security_locked_until->isFuture();
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function courierCashPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'deliveryman_id');
    }

    public function courierSettlements(): HasMany
    {
        return $this->hasMany(CourierSettlement::class, 'deliveryman_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isDealer(): bool
    {
        return $this->role === UserRole::DEALER;
    }

    /**
     * Diller tashkilotining rahbari (dealer roli — UI'da "Owner" deb ataladi).
     */
    public function isOwner(): bool
    {
        return $this->role === UserRole::DEALER;
    }

    public function isWarehouse(): bool
    {
        return $this->role === UserRole::WAREHOUSE;
    }

    public function isDeliveryman(): bool
    {
        return $this->role === UserRole::DELIVERYMAN;
    }

    public function isCashier(): bool
    {
        return $this->role === UserRole::CASHIER;
    }

    /**
     * Diller tashkilotidagi har qanday xodim (owner, skladchi, yetkazib beruvchi, kassir).
     */
    public function isDealerStaff(): bool
    {
        return in_array($this->role, UserRole::dealerStaff(), true);
    }

    public function canManageDealer(): bool
    {
        return $this->isDealerStaff();
    }

    /**
     * Foydalanuvchi roliga mos default sahifa route nomi.
     * Ruxsat berilmagan sahifaga kirishga urinib, redirect qilinganda ishlatiladi.
     */
    public function defaultRouteName(): string
    {
        return match ($this->role) {
            UserRole::SUPER_ADMIN => 'admin.dealers.index',
            UserRole::DEALER => 'dealer.pos.index',
            UserRole::WAREHOUSE => 'dealer.orders.index',
            UserRole::DELIVERYMAN => 'dealer.routes.today',
            UserRole::CASHIER => 'dealer.pos.index',
            default => 'dashboard',
        };
    }

    /**
     * Owner va skladchi mahsulot va kategoriyalar ustida to'liq CRUD qila oladi.
     */
    public function canManageInventory(): bool
    {
        return $this->isDealer() || $this->isWarehouse();
    }

    /**
     * POS terminalida sotuv qila oladigan rollar — owner va kassir.
     */
    public function canRunPos(): bool
    {
        return $this->isDealer() || $this->isCashier();
    }

    /**
     * POS modulini boshqaradigan rollar (smena hisobotlari, mijoz CRUD) — faqat owner.
     */
    public function canManagePos(): bool
    {
        return $this->isDealer();
    }

    public function scopeSuperAdmins(Builder $query): void
    {
        $query->where('role', UserRole::SUPER_ADMIN);
    }

    public function scopeDealers(Builder $query): void
    {
        $query->where('role', UserRole::DEALER);
    }

    public function scopeDeliverymenFor(Builder $query, int $dealerId): void
    {
        $query->where('role', UserRole::DELIVERYMAN)->where('dealer_id', $dealerId);
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }

    public function scopeWithRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }
}
