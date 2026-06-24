<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShopMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShopMember
 */
final class ShopMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telegram_id' => $this->telegram_id,
            'name' => $this->name ?? $this->customer?->name,
            'username' => $this->username,
            'phone' => $this->customer?->phone,
            'channel' => $this->resolveChannel(),
            'is_active' => $this->is_active,
            'joined_at' => $this->joined_at?->toIso8601String(),
        ];
    }

    /**
     * Vakil qaysi kanal orqali biriktirilgan: bot, mobil ilova, yoki ikkalasi
     * (bot a'zoligi mobil akkauntga ulangan).
     */
    private function resolveChannel(): string
    {
        if ($this->telegram_id !== null && $this->app_linked_at !== null) {
            return 'both';
        }

        return $this->telegram_id !== null ? 'telegram' : 'mobile';
    }
}
