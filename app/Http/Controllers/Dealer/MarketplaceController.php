<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketplace\MarketplaceProductResource;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Translit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Birja — diller boshqa dillerlar (distribyutor) mahsulotlarini ko'radi va zakas beradi.
 */
final class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $dealerId = (int) $request->user()->dealer_id;

        $products = Product::query()
            ->where('dealer_id', '!=', $dealerId)
            ->visibleInMarketplace()
            ->active()
            ->availableInStock()
            ->with(['images', 'category', 'dealer', 'activeTypes.images'])
            ->when($request->filled('search'), fn ($q) => Translit::applyLike(
                $q, ['name'], (string) $request->string('search'),
            ))
            ->when($request->filled('seller_id'), fn ($q) => $q->where(
                'dealer_id', (int) $request->input('seller_id'),
            ))
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category', fn ($c) => $c->where('name', (string) $request->string('category')),
            ))
            ->orderBy('dealer_id')
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        $sellers = Dealer::query()
            ->where('id', '!=', $dealerId)
            ->where('sells_on_marketplace', true)
            ->whereHas('products', fn ($q) => $q->visibleInMarketplace()->active()->availableInStock())
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = ProductCategory::query()
            ->whereHas('products', fn ($q) => $q->where('dealer_id', '!=', $dealerId)
                ->visibleInMarketplace()->active()->availableInStock()
                ->when($request->filled('seller_id'), fn ($qq) => $qq->where(
                    'dealer_id', (int) $request->input('seller_id'),
                )))
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name): array => ['name' => $name])
            ->values();

        return Inertia::render('Dealer/Marketplace/Index', [
            'products' => Inertia::scroll(MarketplaceProductResource::collection($products)),
            'sellers' => $sellers,
            'categories' => $categories,
            'filters' => $request->only(['search', 'seller_id', 'category']),
        ]);
    }
}
