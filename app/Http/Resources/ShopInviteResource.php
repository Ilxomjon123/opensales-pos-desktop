<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShopInvite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShopInvite
 */
final class ShopInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $botUsername = $this->shop?->dealer?->bot_username;

        // Bot ulanган bo'lsa — Telegram deep-link; aks holda mobil ilova uchun
        // universal HTTPS link ({app.url}/i/{token}) — bot bo'lmasa ham ishlaydi.
        $link = $botUsername !== null
            ? "https://t.me/{$botUsername}?start={$this->token}"
            : rtrim((string) config('app.url'), '/')."/i/{$this->token}";

        return [
            'id' => $this->id,
            'token' => $this->token,
            'link' => $link,
            'bot_username' => $botUsername,
            'expires_at' => $this->expires_at->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'is_valid' => $this->isValid(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
