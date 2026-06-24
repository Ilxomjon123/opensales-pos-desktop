<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Shop
 */
final class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'landmark' => $this->landmark,
            'region' => $this->region,
            'district' => $this->district,
            'inn' => $this->inn,
            'contact_person' => $this->contact_person,
            'photo' => $this->photo,
            'photo_url' => $this->photo ? Storage::url($this->photo) : null,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'map_provider' => in_array($this->map_provider, ['yandex', 'google', 'osm'], true)
                ? $this->map_provider
                : 'yandex',
            'balance' => $this->balance,
            'parent_shop_id' => $this->parent_shop_id,
            'is_main_branch' => $this->parent_shop_id === null,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ] : null),
            'branches_count' => $this->when(
                isset($this->branches_count),
                fn () => (int) $this->branches_count,
            ),
            'branches_balance_sum' => $this->when(
                array_key_exists('branches_sum_balance', $this->resource->getAttributes()),
                fn () => (int) ($this->branches_sum_balance ?? 0),
            ),
            'total_balance_with_branches' => $this->when(
                $this->parent_shop_id === null
                    && array_key_exists('branches_sum_balance', $this->resource->getAttributes()),
                fn () => (int) $this->balance + (int) ($this->branches_sum_balance ?? 0),
            ),
            'branches' => self::collection($this->whenLoaded('branches')),
            'pending_total' => $this->when(
                array_key_exists('pending_orders_sum_total', $this->resource->getAttributes()),
                fn () => (int) ($this->pending_orders_sum_total ?? 0),
            ),
            'outside_zone' => $this->when(
                array_key_exists('zone_covered', $this->resource->getAttributes()),
                fn () => $this->region !== null && $this->resource->getAttributes()['zone_covered'] === null,
            ),
            'is_active' => $this->is_active,
            'deliveryman_id' => $this->deliveryman_id,
            'deliveryman' => $this->whenLoaded('deliveryman', fn () => [
                'id' => $this->deliveryman->id,
                'name' => $this->deliveryman->name,
            ]),
            'has_members' => $this->when(
                $this->relationLoaded('members') || isset($this->members_count),
                fn () => $this->relationLoaded('members')
                    ? $this->members->isNotEmpty()
                    : (int) $this->members_count > 0,
            ),
            'members_count' => $this->when(
                isset($this->members_count),
                fn () => (int) $this->members_count,
            ),
            'active_members_count' => $this->when(
                isset($this->active_members_count),
                fn () => (int) $this->active_members_count,
            ),
            'last_order_at' => $this->when(
                array_key_exists('last_order_at', $this->resource->getAttributes()),
                fn () => $this->last_order_at ? Carbon::parse($this->last_order_at)->toIso8601String() : null,
            ),
            'last_visit_at' => $this->when(
                array_key_exists('last_visit_at', $this->resource->getAttributes()),
                fn () => $this->last_visit_at ? Carbon::parse($this->last_visit_at)->toIso8601String() : null,
            ),
            'visits_count' => $this->when(
                isset($this->visits_count),
                fn () => (int) $this->visits_count,
            ),
            'members' => ShopMemberResource::collection($this->whenLoaded('members')),
            'active_invite' => ShopInviteResource::make($this->whenLoaded('activeInvite')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
