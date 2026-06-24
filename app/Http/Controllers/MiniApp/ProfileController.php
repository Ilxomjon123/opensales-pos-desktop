<?php

declare(strict_types=1);

namespace App\Http\Controllers\MiniApp;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ShopResource;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\ShopMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class ProfileController extends Controller
{
    public function info(Dealer $dealer): JsonResponse
    {
        return response()->json([
            'dealer' => [
                'id' => $dealer->id,
                'name' => $dealer->name,
                'bot_username' => $dealer->bot_username,
                'bot_display_name' => $dealer->effectiveBotDisplayName(),
                'bot_short_description' => $dealer->effectiveBotShortDescription(),
                'bot_description' => $dealer->effectiveBotDescription(),
                'contact_phone' => $dealer->contact_phone,
            ],
            'project' => [
                'name' => (string) config('project.name'),
                'url' => (string) config('project.url'),
                'support_telegram' => config('project.support_telegram'),
                'version' => (string) config('project.version'),
            ],
        ]);
    }

    public function me(Request $request, Dealer $dealer): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        $telegramId = (int) ($request->attributes->get('telegram_id') ?? 0);

        $shopsCount = $telegramId > 0 ? $this->userShopsQuery($dealer, $telegramId)->count() : 0;

        if ($shop === null) {
            return response()->json([
                'registered' => false,
                'telegram_id' => $telegramId,
                'shops_count' => 0,
                'message' => 'Mijozga biriktirilmagan. Yetkazib beruvchidan taklif link so\'rang.',
            ]);
        }

        return response()->json([
            'registered' => true,
            'shop' => ShopResource::make($shop),
            'shops_count' => $shopsCount,
        ]);
    }

    /**
     * Foydalanuvchi a'zo bo'lgan barcha mijozlar (shu diller ichida).
     */
    public function shops(Request $request, Dealer $dealer): JsonResponse
    {
        $telegramId = (int) ($request->attributes->get('telegram_id') ?? 0);

        if ($telegramId === 0) {
            return response()->json(['shops' => []]);
        }

        $shops = $this->userShopsQuery($dealer, $telegramId)
            ->with(['shop' => fn ($q) => $q->where('dealer_id', $dealer->id)])
            ->get()
            ->map(fn (ShopMember $m) => ShopResource::make($m->shop))
            ->values();

        return response()->json(['shops' => $shops]);
    }

    public function orders(Request $request, Dealer $dealer): AnonymousResourceCollection|JsonResponse
    {
        $shop = $request->attributes->get('shop');

        if ($shop === null) {
            return response()->json(['message' => 'Mijozga biriktirilmagansiz'], 403);
        }

        $allowedStatuses = array_merge(array_column(OrderStatus::cases(), 'value'), ['active']);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in($allowedStatuses)],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest', 'total_desc', 'total_asc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Order::query()
            ->forShop($shop->id)
            ->with(['items', 'deliveryman']);

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $query->open();
            } else {
                $query->where('status', $validated['status']);
            }
        }

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'total_desc' => $query->orderByDesc('total')->orderByDesc('id'),
            'total_asc' => $query->orderBy('total')->orderByDesc('id'),
            default => $query->latest(),
        };

        $orders = $query->paginate($validated['per_page'] ?? 20)->withQueryString();

        return OrderResource::collection($orders);
    }

    private function userShopsQuery(Dealer $dealer, int $telegramId)
    {
        return ShopMember::query()
            ->forTelegram($telegramId)
            ->active()
            ->whereHas('shop', fn ($q) => $q->where('dealer_id', $dealer->id));
    }
}
