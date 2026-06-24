<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrderChannel;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\ShopMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobil ilova: Sanctum token egasi (Customer) → URL dagi {dealer} va
 * X-Shop-Id header bo'yicha aniq vakil/shop ni topadi va request
 * atributlariga yozadi. MiniApp controllerlari shu atributlardan
 * foydalanadi, shuning uchun ular qayta ishlatiladi.
 */
final class ResolveMobileMember
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Dealer|null $dealer */
        $dealer = $request->route('dealer');
        abort_if($dealer === null || ! $dealer->is_active, 404);

        $customer = $request->user();
        abort_if(! $customer instanceof Customer, 401);
        abort_if(! $customer->is_active, 403, 'Akkaunt bloklangan');

        $baseQuery = ShopMember::query()
            ->forCustomer($customer->id)
            ->active()
            ->whereHas('shop', fn ($q) => $q->where('dealer_id', $dealer->id)->where('is_active', true))
            ->with('shop');

        $desiredShopId = (int) ($request->header('X-Shop-Id') ?? $request->input('shop_id') ?? 0);

        $member = null;
        if ($desiredShopId > 0) {
            $member = (clone $baseQuery)->where('shop_id', $desiredShopId)->first();
        }

        $member ??= $baseQuery->first();

        $request->attributes->set('dealer', $dealer);
        $request->attributes->set('member', $member);
        $request->attributes->set('shop', $member?->shop);
        $request->attributes->set('telegram_id', $member?->cartOwnerKey());
        $request->attributes->set('order_channel', OrderChannel::MOBILE_APP);

        return $next($request);
    }
}
