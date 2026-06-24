<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMessage;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // can_* abilitlari faqat panel foydalanuvchilari (User) uchun. Mobil
        // ilovada $request->user() — Customer, OrderPolicy esa User kutadi.
        $user = $request->user();
        $isStaff = $user instanceof User;

        return [
            'id' => $this->id,
            'number' => $this->displayNumber(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'currency' => $this->currency?->value,
            'currency_symbol' => $this->currency?->symbol(),
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'discount' => (int) ($this->discount ?? 0),
            'delivered_total' => $this->delivered_total,
            // Sklad tayyorlagan miqdor bo'yicha jami (ASSEMBLING/DELIVERING'da
            // "jami" sifatida ishlatiladi). Items relation yuklangan bo'lsa.
            'prepared_total' => $this->whenLoaded('items', fn () => $this->preparedTotal()),
            // Status'ga qarab tanlangan "ko'rsatiladigan jami" — desktop,
            // mobile va mini-app shu maydonni ishlatadi.
            'display_total' => $this->whenLoaded('items', fn () => $this->displayTotal()),
            'balance_before' => $this->balance_before !== null ? (int) $this->balance_before : null,
            'balance_after' => $this->balance_after !== null ? (int) $this->balance_after : null,
            'note' => $this->note,

            // Lifecycle timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assembling_at' => $this->assembling_at?->toIso8601String(),
            'delivering_at' => $this->delivering_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            // Yetkazib beruvchi biriktirish
            'deliveryman_id' => $this->deliveryman_id,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'can_self_assign' => $isStaff && $user->can('selfAssign', $this->resource),
            'can_release_self' => $isStaff && $user->can('releaseSelf', $this->resource),
            'can_cancel' => $isStaff && $user->can('cancel', $this->resource),
            'can_dispatch' => $isStaff && $user->can('dispatch', $this->resource),
            'can_accept_return' => $isStaff && $user->can('acceptReturn', $this->resource),
            'can_edit' => $isStaff && $user->can('edit', $this->resource),
            'can_edit_picked' => $isStaff && $user->can('editPicked', $this->resource),
            // Carry faqat dostavkachi qo'lida (DELIVERING+) hisoblanadi.
            // ASSEMBLING'da picked_qty yozilgan bo'lsa ham, tovar hali skladda.
            'has_carry' => $this->whenLoaded('items', fn () => $this->hasCarryStatus() && $this->items->sum(fn ($i) => $i->carryQty()) > 0),
            'carry_total' => $this->whenLoaded('items', fn () => $this->hasCarryStatus() ? (int) $this->items->sum(fn ($i) => $i->carrySubtotal()) : 0),
            'has_pending_return' => $this->resolvePendingReturn(),
            'deliveryman' => $this->whenLoaded('deliveryman', fn () => $this->deliveryman ? [
                'id' => $this->deliveryman->id,
                'name' => $this->deliveryman->name,
                'phone' => $this->deliveryman->phone,
            ] : null),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => $this->cancelledBy ? [
                'id' => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ] : null),

            // Kanal va xaridor: bot/manual = shop, marketplace = xaridor diller
            'channel' => $this->channel?->value,
            'channel_label' => $this->channel?->label(),
            'customer_name' => $this->channel === OrderChannel::MARKETPLACE
                ? ($this->relationLoaded('buyerDealer') ? $this->buyerDealer?->name : null)
                : ($this->relationLoaded('shop') ? $this->shop?->name : null),
            'buyer_dealer' => $this->whenLoaded('buyerDealer', fn () => $this->buyerDealer ? [
                'id' => $this->buyerDealer->id,
                'name' => $this->buyerDealer->name,
                'phone' => $this->buyerDealer->contact_phone,
            ] : null),

            // Relations
            'shop' => ShopResource::make($this->whenLoaded('shop')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'dealer' => $this->whenLoaded('dealer', fn () => [
                'id' => $this->dealer->id,
                'name' => $this->dealer->name,
            ]),

            // Buyurtma ichidagi xabarlar (diller → mijoz)
            'messages' => $this->whenLoaded(
                'messages',
                fn () => $this->messages->map(fn (OrderMessage $m): array => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'updated_at' => $m->updated_at?->toIso8601String(),
                    'edited' => $m->updated_at !== null && $m->created_at !== null
                        && $m->updated_at->gt($m->created_at),
                    'author' => $m->relationLoaded('author') && $m->author !== null
                        ? ['id' => $m->author->id, 'name' => $m->author->name]
                        : null,
                ])->values(),
            ),

            // Status tarixi (kim, qachon, sabab)
            'status_history' => $this->whenLoaded(
                'statusHistory',
                fn () => $this->statusHistory->map(fn (OrderStatusHistory $h): array => [
                    'id' => $h->id,
                    'from_status' => $h->from_status?->value,
                    'to_status' => $h->to_status->value,
                    'to_status_label' => $h->to_status->label(),
                    'changed_at' => $h->changed_at?->toIso8601String(),
                    'reason' => $h->reason,
                    'actor' => $this->buildActor($h),
                ])->values(),
            ),
        ];
    }

    private function hasCarryStatus(): bool
    {
        return in_array($this->status, [
            OrderStatus::DELIVERING,
            OrderStatus::DELIVERED,
            OrderStatus::RECEIVED,
        ], true);
    }

    private function hasPendingReturnStatus(): bool
    {
        return in_array($this->status, [
            OrderStatus::DELIVERED,
            OrderStatus::RECEIVED,
        ], true);
    }

    /**
     * Buyurtmada sklad qabul qilmagan vozvrat bormi.
     * Avval scope qo'shgan subselect ustuni tekshiriladi, aks holda agar
     * items relation yuklangan bo'lsa, in-memory hisob qilinadi.
     */
    private function resolvePendingReturn(): bool
    {
        if (! $this->hasPendingReturnStatus()) {
            return false;
        }

        if (array_key_exists('has_pending_return', $this->resource->getAttributes())) {
            return (bool) $this->resource->getAttribute('has_pending_return');
        }

        if ($this->relationLoaded('items')) {
            return $this->items->contains(
                fn (OrderItem $item) => $item->carryQty() > 0 || $item->carryPackQty() > 0,
            );
        }

        return false;
    }

    private function buildActor(OrderStatusHistory $history): ?array
    {
        if ($history->relationLoaded('user') && $history->user !== null) {
            return [
                'type' => 'user',
                'id' => $history->user->id,
                'name' => $history->user->name,
                'role' => $history->user->role?->label(),
            ];
        }

        if ($history->relationLoaded('member') && $history->member !== null) {
            return [
                'type' => 'shop_member',
                'id' => $history->member->id,
                'name' => $history->member->name,
            ];
        }

        return null;
    }
}
