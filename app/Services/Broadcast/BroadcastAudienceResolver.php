<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Enums\BroadcastAudienceType;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\ShopMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Campaign uchun qabul qiluvchilarni hisoblash.
 *
 * Asosiy metodlar:
 *  - resolve(Campaign): LazyCollection — chunked streaming, RAM-safe (faqat 1000 row bir vaqtda)
 *  - count(Campaign): int — alohida COUNT(*) query, qabul qiluvchilarni RAM ga olib chiqmaydi
 *
 * Element format: array{shop_id:?int, dealer_id:int, chat_id:int}
 */
final class BroadcastAudienceResolver
{
    private const CHUNK_SIZE = 1000;

    public function resolve(BroadcastCampaign $campaign): LazyCollection
    {
        return match ($campaign->audience_type) {
            BroadcastAudienceType::ALL_ACTIVE,
            BroadcastAudienceType::SELECTED_SHOPS,
            BroadcastAudienceType::FILTER => $this->streamShopMembers($campaign),

            BroadcastAudienceType::PLATFORM_DEALERS => $this->streamPlatformDealers($campaign),
            BroadcastAudienceType::PLATFORM_SHOP_MEMBERS => $this->streamPlatformShopMembers($campaign),
        };
    }

    /**
     * Mobil ilova uchun qabul qiluvchilar — Telegram emas, customer_id bog'langan
     * a'zolar (har customer bir marta). Audience/filtr mantiqi resolve() bilan bir xil.
     *
     * @return LazyCollection<int, ShopMember>
     */
    public function mobileMembers(BroadcastCampaign $campaign): LazyCollection
    {
        $query = match ($campaign->audience_type) {
            BroadcastAudienceType::ALL_ACTIVE,
            BroadcastAudienceType::SELECTED_SHOPS,
            BroadcastAudienceType::FILTER => $this->shopMembersQuery($campaign),

            BroadcastAudienceType::PLATFORM_SHOP_MEMBERS => $this->platformShopMembersQuery($campaign),

            // Dilerlarga yo'naltirilgan (admin) — mobil mijozlarga tegishli emas.
            BroadcastAudienceType::PLATFORM_DEALERS => null,
        };

        if ($query === null) {
            return LazyCollection::empty();
        }

        // Renderer shop maydonlaridan (nom/telefon/balans/manzil) foydalanadi — to'liq shop.
        return $query
            ->whereNotNull('customer_id')
            ->with(['shop', 'shop.dealer:id,name', 'customer.deviceTokens'])
            ->lazyById(self::CHUNK_SIZE)
            ->unique('customer_id')
            ->values();
    }

    public function count(BroadcastCampaign $campaign): int
    {
        return match ($campaign->audience_type) {
            BroadcastAudienceType::ALL_ACTIVE,
            BroadcastAudienceType::SELECTED_SHOPS,
            BroadcastAudienceType::FILTER => $this->shopMembersQuery($campaign)->count(),

            BroadcastAudienceType::PLATFORM_DEALERS => $this->platformDealersQuery($campaign)->count(),
            BroadcastAudienceType::PLATFORM_SHOP_MEMBERS => $this->platformShopMembersQuery($campaign)->count(),
        };
    }

    private function shopMembersQuery(BroadcastCampaign $campaign): Builder
    {
        if ($campaign->dealer_id === null) {
            return ShopMember::query()->whereRaw('1=0');
        }

        return ShopMember::query()
            ->active()
            ->whereHas('shop', function (Builder $q) use ($campaign): void {
                $q->where('dealer_id', $campaign->dealer_id)->where('is_active', true);

                $this->applyAudienceScope($q, $campaign);
            });
    }

    private function streamShopMembers(BroadcastCampaign $campaign): LazyCollection
    {
        $dealerId = (int) ($campaign->dealer_id ?? 0);

        return $this->shopMembersQuery($campaign)
            ->with('shop:id,dealer_id')
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (ShopMember $m): array => [
                'shop_id' => (int) $m->shop_id,
                'dealer_id' => (int) ($m->shop->dealer_id ?? $dealerId),
                'chat_id' => (int) $m->telegram_id,
            ])
            ->unique('chat_id')
            ->values();
    }

    private function applyAudienceScope(Builder $shopQuery, BroadcastCampaign $campaign): void
    {
        if ($campaign->audience_type === BroadcastAudienceType::SELECTED_SHOPS) {
            $ids = array_map('intval', (array) ($campaign->audience_config['shop_ids'] ?? []));
            $shopQuery->whereIn('shops.id', $ids === [] ? [0] : $ids);

            return;
        }

        if ($campaign->audience_type !== BroadcastAudienceType::FILTER) {
            return;
        }

        $this->applyFilter($shopQuery, $campaign);
    }

    private function applyFilter(Builder $shopQuery, BroadcastCampaign $campaign): void
    {
        $config = (array) ($campaign->audience_config ?? []);

        if (isset($config['balance_op'], $config['balance_value'])) {
            $op = (string) $config['balance_op'];
            $val = (int) $config['balance_value'];

            if (in_array($op, ['<', '<=', '=', '>=', '>'], true)) {
                $shopQuery->where('balance', $op, $val);
            }
        }

        if (isset($config['debtors_only']) && $config['debtors_only']) {
            $shopQuery->where('balance', '<', 0);
        }

        if (isset($config['min_days_since_last_order'])) {
            $days = (int) $config['min_days_since_last_order'];
            $threshold = now()->subDays($days)->toDateTimeString();

            $shopQuery->where(function (Builder $q) use ($threshold): void {
                $q->whereDoesntHave('orders')
                    ->orWhereDoesntHave('orders', function (Builder $oq) use ($threshold): void {
                        $oq->where('created_at', '>=', $threshold);
                    });
            });
        }

        if (isset($config['region']) && $config['region'] !== '') {
            $shopQuery->where('region', $config['region']);
        }

        if (isset($config['category_ids']) && is_array($config['category_ids']) && $config['category_ids'] !== []) {
            $categoryIds = array_map('intval', $config['category_ids']);

            $shopQuery->whereExists(function ($q) use ($categoryIds): void {
                $q->select(DB::raw(1))
                    ->from('orders')
                    ->join('order_items', 'order_items.order_id', '=', 'orders.id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('orders.shop_id', 'shops.id')
                    ->whereIn('products.category_id', $categoryIds);
            });
        }
    }

    private function platformDealersQuery(BroadcastCampaign $campaign): Builder
    {
        $query = Dealer::query()
            ->active()
            ->whereNotNull('telegram_chat_id');

        $dealerIds = array_map('intval', (array) ($campaign->audience_config['dealer_ids'] ?? []));

        if ($dealerIds !== []) {
            $query->whereIn('id', $dealerIds);
        }

        return $query;
    }

    private function streamPlatformDealers(BroadcastCampaign $campaign): LazyCollection
    {
        return $this->platformDealersQuery($campaign)
            ->lazyById(self::CHUNK_SIZE)
            ->map(fn (Dealer $d): array => [
                'shop_id' => null,
                'dealer_id' => (int) $d->id,
                'chat_id' => (int) $d->telegram_chat_id,
            ])
            ->unique('chat_id')
            ->values();
    }

    private function platformShopMembersQuery(BroadcastCampaign $campaign): Builder
    {
        $dealerIds = array_map('intval', (array) ($campaign->audience_config['dealer_ids'] ?? []));

        return ShopMember::query()
            ->active()
            ->whereHas('shop', function (Builder $q) use ($campaign, $dealerIds): void {
                $q->where('is_active', true)
                    ->whereHas('dealer', fn (Builder $q2) => $q2->where('is_active', true));

                if ($dealerIds !== []) {
                    $q->whereIn('dealer_id', $dealerIds);
                }

                // Saldo/qarz/hudud/kategoriya/faolsizlik filtrlari — diller filtri bilan bir xil.
                $this->applyFilter($q, $campaign);
            });
    }

    private function streamPlatformShopMembers(BroadcastCampaign $campaign): LazyCollection
    {
        return $this->platformShopMembersQuery($campaign)
            ->with('shop:id,dealer_id,is_active')
            ->lazyById(self::CHUNK_SIZE)
            ->filter(fn (ShopMember $m): bool => $m->shop !== null)
            ->map(fn (ShopMember $m): array => [
                'shop_id' => (int) $m->shop_id,
                'dealer_id' => (int) $m->shop->dealer_id,
                'chat_id' => (int) $m->telegram_id,
            ])
            ->unique('chat_id')
            ->values();
    }
}
