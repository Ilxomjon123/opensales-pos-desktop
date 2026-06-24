<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StorePromotionRequest;
use App\Http\Requests\Dealer\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $promotions = Promotion::query()
            ->forDealer($dealerId)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Dealer/Promotions/Index', [
            'promotions' => PromotionResource::collection($promotions),
        ]);
    }

    public function create(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        return Inertia::render('Dealer/Promotions/Create', [
            'products' => Product::query()
                ->forDealer($dealerId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => ProductCategory::query()
                ->forDealer($dealerId)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['dealer_id'] = $request->user()->dealer_id;

        Promotion::query()->create($data);

        $this->promotions->invalidate((int) $request->user()->dealer_id);

        return redirect()
            ->route('dealer.promotions.index')
            ->with('status', 'Aksiya yaratildi');
    }

    public function edit(Request $request, Promotion $promotion): Response
    {
        $this->authorizeAccess($request, $promotion);

        $dealerId = (int) $request->user()->dealer_id;

        return Inertia::render('Dealer/Promotions/Edit', [
            'promotion' => PromotionResource::make($promotion),
            'products' => Product::query()
                ->forDealer($dealerId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => ProductCategory::query()
                ->forDealer($dealerId)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizeAccess($request, $promotion);

        $promotion->update($request->validated());

        $this->promotions->invalidate((int) $request->user()->dealer_id);

        return redirect()
            ->route('dealer.promotions.index')
            ->with('status', 'Aksiya yangilandi');
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizeAccess($request, $promotion);

        $promotion->delete();

        $this->promotions->invalidate((int) $request->user()->dealer_id);

        return redirect()
            ->route('dealer.promotions.index')
            ->with('status', 'Aksiya o\'chirildi');
    }

    private function authorizeAccess(Request $request, Promotion $promotion): void
    {
        abort_if(
            $promotion->dealer_id !== (int) $request->user()->dealer_id,
            403,
        );
    }
}
