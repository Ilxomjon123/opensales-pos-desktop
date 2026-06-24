<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\OrderChannel;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\SaleChannel;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'member_id',
        'dealer_id',
        'currency',
        'channel',
        'buyer_dealer_id',
        'number',
        'month_number',
        'deliveryman_id',
        'sale_channel',
        'shift_id',
        'cashier_user_id',
        'assigned_at',
        'status',
        'total',
        'paid_amount',
        'paid_cash',
        'paid_card',
        'debt_amount',
        'payment_status',
        'receipt_number',
        'fiscal_data',
        'discount',
        'delivered_total',
        'balance_before',
        'balance_after',
        'assembling_at',
        'delivering_at',
        'delivered_at',
        'received_at',
        'received_by_member_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'note',
        'platform_fee_rate',
        'dealer_notification_message_id',
        'last_notified_total',
    ];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'status' => OrderStatus::class,
            'sale_channel' => SaleChannel::class,
            'payment_status' => OrderPaymentStatus::class,
            'fiscal_data' => 'array',
            'channel' => OrderChannel::class,
            'number' => 'integer',
            'month_number' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'paid_cash' => 'integer',
            'paid_card' => 'integer',
            'debt_amount' => 'integer',
            'discount' => 'integer',
            'delivered_total' => 'integer',
            'last_notified_total' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'assigned_at' => 'datetime',
            'assembling_at' => 'datetime',
            'delivering_at' => 'datetime',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'platform_fee_rate' => 'decimal:2',
        ];
    }

    /**
     * Mijozga/dillerga ko'rsatiladigan buyurtma raqami — har oy 1 dan boshlanadi.
     * Eski (backfilldan oldingi) buyurtmalar uchun global `number` ga qaytadi.
     */
    public function displayNumber(): int
    {
        return (int) ($this->month_number ?? $this->number);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ShopMember::class, 'member_id');
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    /**
     * Marketplace kanalida xaridor diller (bot kanalida null).
     */
    public function buyerDealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'buyer_dealer_id');
    }

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deliveryman_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Joriy item'larning subtotal yig'indisi.
     * Snapshot `total` ustuni eskirib qolishi mumkin (item qty/price keyin
     * o'zgargan bo'lsa), shu metod doim live qiymat qaytaradi.
     * `items` relation oldindan yuklangan bo'lishi kerak.
     */
    public function liveItemsTotal(): int
    {
        return (int) $this->items->sum(fn (OrderItem $item): int => $item->subtotal());
    }

    /**
     * Sklad tayyorlagan miqdor asosida jami summasi
     * (picked_qty/picked_pack_qty bo'yicha).
     * `items` relation oldindan yuklangan bo'lishi kerak.
     */
    public function preparedTotal(): int
    {
        return (int) $this->items->sum(fn (OrderItem $item): int => $item->preparedSubtotal());
    }

    /**
     * Status'ga qarab "jami" sifatida ko'rsatiladigan summa.
     *  - DELIVERED/RECEIVED: delivered_total (fallback: live items)
     *  - ASSEMBLING/DELIVERING: preparedTotal()
     *  - PENDING/CANCELLED: liveItemsTotal()
     * `items` relation oldindan yuklangan bo'lishi kerak.
     */
    public function displayTotal(): int
    {
        return match ($this->status) {
            OrderStatus::DELIVERED, OrderStatus::RECEIVED => (int) ($this->delivered_total ?? $this->liveItemsTotal()),
            OrderStatus::ASSEMBLING, OrderStatus::DELIVERING => $this->preparedTotal(),
            default => $this->liveItemsTotal(),
        };
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class)->orderBy('id');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', OrderStatus::PENDING);
    }

    public function scopeWithStatus(Builder $query, OrderStatus $status): void
    {
        $query->where('status', $status);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', [OrderStatus::RECEIVED, OrderStatus::CANCELLED]);
    }

    /**
     * Muvaffaqiyatli yetkazilgan buyurtmalar (statistika va revenue uchun) —
     * delivered (mijoz hali tasdiqlamagan) + received (mijoz tasdiqlagan).
     */
    public function scopeFulfilled(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::DELIVERED, OrderStatus::RECEIVED]);
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }

    public function scopeForShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }

    /**
     * Faqat bot (shop) kanalidagi buyurtmalar. Mavjud moliya/komissiya/hisobot
     * query'lari shu scope bilan himoyalanadi — marketplace sizib kira olmaydi.
     */
    public function scopeBot(Builder $query): void
    {
        $query->where('channel', OrderChannel::BOT->value);
    }

    public function scopeMarketplace(Builder $query): void
    {
        $query->where('channel', OrderChannel::MARKETPLACE->value);
    }

    /**
     * Shop savdosi (bot + qo'lda), Birja emas. Mavjud moliya/komissiya/hisobot
     * query'lari shu scope bilan himoyalanadi — marketplace sizib kira olmaydi,
     * lekin qo'lda yaratilgan shop buyurtmalari hisobga olinadi.
     */
    public function scopeShopChannel(Builder $query): void
    {
        $query->where('channel', '!=', OrderChannel::MARKETPLACE->value);
    }

    /**
     * Marketplace kanalida xaridor diller bo'yicha filtr (xaridor o'z xaridlarini ko'radi).
     */
    public function scopeForBuyerDealer(Builder $query, int $dealerId): void
    {
        $query->where('buyer_dealer_id', $dealerId);
    }

    public function scopeForDeliveryman(Builder $query, int $userId): void
    {
        $query->where('deliveryman_id', $userId);
    }

    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNull('deliveryman_id');
    }

    public function scopeOnChannel(Builder $query, SaleChannel $channel): void
    {
        $query->where('sale_channel', $channel);
    }

    public function scopeFromPos(Builder $query): void
    {
        $query->where('sale_channel', SaleChannel::POS);
    }

    public function scopeFromTelegram(Builder $query): void
    {
        $query->where('sale_channel', SaleChannel::TELEGRAM);
    }

    public function scopeForShift(Builder $query, int $shiftId): void
    {
        $query->where('shift_id', $shiftId);
    }

    /**
     * Buyurtmaga qo'shimcha boolean ustun qo'shadi: has_pending_return — sklad
     * qabul qilmagan vozvrat (carry) bormi yo'qmi. Mantiq DELIVERED/RECEIVED
     * statusida ma'noli, status tekshiruvi resursda qo'shiladi.
     */
    public function scopeWithPendingReturn(Builder $query): void
    {
        $query->addSelect(['has_pending_return' => OrderItem::query()
            ->selectRaw('1')
            ->whereColumn('order_items.order_id', 'orders.id')
            ->where(function (Builder $q): void {
                $q->whereRaw('(COALESCE(picked_qty, 0) - COALESCE(delivered_qty, 0) - COALESCE(returned_qty, 0)) > 0')
                    ->orWhereRaw('(COALESCE(picked_pack_qty, 0) - COALESCE(delivered_pack_qty, 0) - COALESCE(returned_pack_qty, 0)) > 0');
            })
            ->limit(1),
        ]);
    }

    /**
     * Predicate: faqat sklad qabul qilmagan vozvrati bor buyurtmalar.
     */
    public function scopeHasPendingReturn(Builder $query): void
    {
        $query->whereExists(function ($q): void {
            $q->select(DB::raw('1'))
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->where(function ($qq): void {
                    $qq->whereRaw('(COALESCE(picked_qty, 0) - COALESCE(delivered_qty, 0) - COALESCE(returned_qty, 0)) > 0')
                        ->orWhereRaw('(COALESCE(picked_pack_qty, 0) - COALESCE(delivered_pack_qty, 0) - COALESCE(returned_pack_qty, 0)) > 0');
                });
        });
    }
}
