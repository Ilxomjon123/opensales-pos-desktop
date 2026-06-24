<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dealer
 */
final class DealerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_id' => $this->country_id,
            'currency' => $this->currency?->value,
            'bot_username' => $this->bot_username,
            'bot_display_name' => $this->bot_display_name,
            'bot_short_description' => $this->bot_short_description,
            'bot_description' => $this->bot_description,
            'bot_display_name_default' => $this->resource->effectiveBotDisplayName(),
            'bot_short_description_default' => $this->resource->effectiveBotShortDescription(),
            'bot_description_default' => $this->resource->effectiveBotDescription(),
            'contact_phone' => $this->contact_phone,
            'warehouse_latitude' => $this->warehouse_latitude !== null ? (float) $this->warehouse_latitude : null,
            'warehouse_longitude' => $this->warehouse_longitude !== null ? (float) $this->warehouse_longitude : null,
            'warehouse_address' => $this->warehouse_address,
            'warehouse_map_provider' => $this->warehouse_map_provider,
            'telegram_chat_id' => $this->telegram_chat_id,
            'is_active' => $this->is_active,
            'is_self_registered' => (bool) $this->is_self_registered,
            'trial_days_left' => $this->trial_ends_at !== null ? $this->resource->trialDaysLeft() : null,
            'trial_expired' => $this->trial_ends_at !== null ? $this->resource->trialExpired() : null,
            'visibility' => $this->visibility?->value,
            'min_order_amount' => (int) $this->min_order_amount,
            'sells_on_marketplace' => (bool) $this->sells_on_marketplace,
            'marketplace_commission_type' => $this->marketplace_commission_type?->value,
            'marketplace_platform_fee_rate' => $this->marketplace_platform_fee_rate !== null ? (float) $this->marketplace_platform_fee_rate : null,
            'marketplace_fixed_commission_amount' => $this->marketplace_fixed_commission_amount !== null ? (int) $this->marketplace_fixed_commission_amount : null,
            'marketplace_min_order_amount' => (int) $this->marketplace_min_order_amount,
            'show_out_of_stock' => (bool) $this->show_out_of_stock,
            'notify_on_price_change' => (bool) $this->notify_on_price_change,
            'notify_on_new_product' => (bool) $this->notify_on_new_product,
            'webhook_set_at' => $this->webhook_set_at?->toIso8601String(),
            'webhook_active' => $this->webhook_set_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'shops_count' => $this->whenCounted('shops'),
            'orders_count' => $this->whenCounted('orders'),
            'products_count' => $this->whenCounted('products'),
            'revenue' => $this->when(
                $this->hasAttribute('revenue') || $this->hasAttribute('discount_total'),
                fn (): int => (int) ($this->resource->revenue ?? 0) - (int) ($this->resource->discount_total ?? 0),
            ),
            'discount' => $this->when(
                $this->hasAttribute('discount_total'),
                fn (): int => (int) $this->resource->discount_total,
            ),
        ];
    }

    private function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->resource->getAttributes());
    }
}
