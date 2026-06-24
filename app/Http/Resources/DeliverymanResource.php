<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class DeliverymanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'shops_count' => $this->when(
                isset($this->shops_count),
                fn () => (int) $this->shops_count,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
