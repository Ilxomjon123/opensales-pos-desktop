<?php

declare(strict_types=1);

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\MiniApp\ProductResource;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Shop;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favorites) {}

    public function index(Request $request, Dealer $dealer): AnonymousResourceCollection|JsonResponse
    {
        $shop = $this->shop($request);

        if ($shop === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        $ids = $this->favorites->productIds($shop->id);
        $request->attributes->set('favorite_product_ids', $ids);

        $products = Product::query()
            ->forDealer($dealer->id)
            ->active()
            ->visibleInBot()
            ->whereIn('id', $ids)
            ->when(! $dealer->show_out_of_stock, fn ($q) => $q->availableInStock())
            ->with('images')
            ->orderBy('name')
            ->paginate(20);

        return ProductResource::collection($products);
    }

    public function toggle(Request $request, Dealer $dealer, Product $product): JsonResponse
    {
        $shop = $this->shop($request);

        if ($shop === null) {
            return response()->json(['message' => 'Avval mijozni tanlang'], 403);
        }

        if ($product->dealer_id !== $dealer->id) {
            return response()->json(['message' => 'Mahsulot topilmadi'], 404);
        }

        $isFavorite = $this->favorites->toggle($shop, $product);

        return response()->json([
            'product_id' => $product->id,
            'is_favorite' => $isFavorite,
        ]);
    }

    private function shop(Request $request): ?Shop
    {
        return $request->attributes->get('shop');
    }
}
