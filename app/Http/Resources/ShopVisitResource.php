<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShopVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShopVisit
 */
final class ShopVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'visited_at' => $this->visited_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            // Tahrirlash/o'chirish ruxsati (muallif/diller + 4 soatlik oyna)
            'can_modify' => $request->user()?->can('update', $this->resource) ?? false,
        ];
    }
}
