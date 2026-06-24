<?php

declare(strict_types=1);

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\MiniApp\ProductResource;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CatalogController extends Controller
{
    public function __construct(private readonly FavoriteService $favorites) {}

    public function categories(Dealer $dealer): JsonResponse
    {
        $categories = ProductCategory::query()
            ->forDealer($dealer->id)
            ->active()
            ->get(['id', 'name']);

        return response()->json([
            'categories' => $categories->map(fn (ProductCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
        ]);
    }

    public function products(Request $request, Dealer $dealer): AnonymousResourceCollection
    {
        $shop = $this->shop($request);
        $favoriteIds = $shop !== null ? $this->favorites->productIds($shop->id) : [];
        $request->attributes->set('favorite_product_ids', $favoriteIds);

        $products = Product::query()
            ->forDealer($dealer->id)
            ->active()
            ->visibleInBot()
            ->with(['images', 'types' => fn ($q) => $q->where('is_active', true), 'types.images'])
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$term]);
            })
            ->when($request->filled('category_id'), function ($q) use ($request): void {
                $q->where('category_id', $request->integer('category_id'));
            })
            ->when($request->boolean('only_favorites') && $favoriteIds !== [], function ($q) use ($favoriteIds): void {
                $q->whereIn('id', $favoriteIds);
            })
            ->when($request->boolean('only_favorites') && $favoriteIds === [], function ($q): void {
                $q->whereRaw('1 = 0');
            })
            ->when(! $dealer->show_out_of_stock, fn ($q) => $q->availableInStock())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ProductResource::collection($products);
    }

    public function product(Request $request, Dealer $dealer, Product $product): ProductResource|JsonResponse
    {
        if ($product->dealer_id !== $dealer->id || ! $product->is_active || ! $product->visibility->visibleInBot()) {
            return response()->json(['message' => 'Mahsulot topilmadi'], 404);
        }

        $product->load(['images', 'types' => fn ($q) => $q->where('is_active', true), 'types.images']);

        if (! $dealer->show_out_of_stock && ! $this->hasStock($product)) {
            return response()->json(['message' => 'Mahsulot topilmadi'], 404);
        }

        $shop = $this->shop($request);
        if ($shop !== null) {
            $request->attributes->set('favorite_product_ids', $this->favorites->productIds($shop->id));
        }

        return ProductResource::make($product);
    }

    private function hasStock(Product $product): bool
    {
        if ($product->has_types) {
            return $product->types->contains(fn ($t): bool => $t->is_active && (float) $t->stock > 0);
        }

        return (float) $product->stock > 0;
    }

    private function shop(Request $request): ?Shop
    {
        return $request->attributes->get('shop');
    }
}
