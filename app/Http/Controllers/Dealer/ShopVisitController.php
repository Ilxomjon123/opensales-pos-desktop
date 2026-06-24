<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreShopVisitRequest;
use App\Http\Resources\ShopVisitResource;
use App\Models\Shop;
use App\Models\ShopVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ShopVisitController extends Controller
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorize('view', $shop);

        $visits = $shop->visits()
            ->with('user:id,name')
            ->latest('visited_at')
            ->limit(50)
            ->get();

        return ShopVisitResource::collection($visits)->response();
    }

    public function store(StoreShopVisitRequest $request, Shop $shop): RedirectResponse
    {
        $this->authorize('recordVisit', $shop);

        $shop->visits()->create([
            'dealer_id' => $shop->dealer_id,
            'user_id' => $request->user()->id,
            'note' => $request->validated('note'),
            'visited_at' => now(),
        ]);

        return back()->with('status', 'Vizit qayd etildi');
    }

    public function update(StoreShopVisitRequest $request, Shop $shop, ShopVisit $visit): RedirectResponse
    {
        abort_unless($visit->shop_id === $shop->id, 404);
        $this->authorize('update', $visit);

        $visit->update(['note' => $request->validated('note')]);

        return back()->with('status', 'Vizit yangilandi');
    }

    public function destroy(Shop $shop, ShopVisit $visit): RedirectResponse
    {
        abort_unless($visit->shop_id === $shop->id, 404);
        $this->authorize('delete', $visit);

        $visit->delete();

        return back()->with('status', 'Vizit o\'chirildi');
    }
}
