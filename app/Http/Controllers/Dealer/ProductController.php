<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Actions\ReorderProductsAction;
use App\Actions\SyncProductTypesAction;
use App\Events\ProductCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\ReorderProductsRequest;
use App\Http\Requests\Dealer\StoreProductRequest;
use App\Http\Requests\Dealer\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\PriceChangeNotifier;
use App\Services\ProductImageService;
use App\Support\Translit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductImageService $images,
        private readonly SyncProductTypesAction $syncTypes,
        private readonly ReorderProductsAction $reorderAction,
        private readonly PriceChangeNotifier $priceNotifier,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $allowedSorts = [
            'low_stock', 'newest', 'oldest',
            'name_asc', 'name_desc',
            'price_asc', 'price_desc',
            'stock_asc', 'stock_desc',
        ];

        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'low_stock';

        $effectiveStockSql = '(CASE WHEN products.has_types '
            .'THEN COALESCE((SELECT SUM(stock) FROM product_types WHERE product_id = products.id), 0) '
            .'ELSE products.stock END)';

        $isLowStockSql = '(CASE WHEN '
            .'(NOT products.has_types AND products.min_stock > 0 AND products.stock <= products.min_stock) '
            .'OR (products.has_types AND EXISTS ('
            .'SELECT 1 FROM product_types WHERE product_id = products.id '
            .'AND min_stock > 0 AND stock <= min_stock'
            .')) THEN 0 ELSE 1 END)';

        $hasTypesFilter = $request->string('has_types')->toString();
        $hasTypesFilter = in_array($hasTypesFilter, ['with', 'without'], true) ? $hasTypesFilter : null;

        $query = Product::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->with(['category', 'images', 'types'])
            ->when($request->filled('search'), fn ($q) => Translit::applyLike(
                $q, ['name'], (string) $request->string('search')
            ))
            ->when($request->filled('category_id'), fn ($q) => $q->where(
                'category_id', (int) $request->input('category_id')
            ))
            ->when($hasTypesFilter === 'with', fn ($q) => $q->where('has_types', true))
            ->when($hasTypesFilter === 'without', fn ($q) => $q->where('has_types', false));

        match ($sort) {
            'low_stock' => $query
                ->orderByRaw($isLowStockSql.' asc')
                ->orderByRaw($effectiveStockSql.' asc')
                ->latest('id'),
            'newest' => $query->latest('id'),
            'oldest' => $query->oldest('id'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'stock_asc' => $query->orderByRaw($effectiveStockSql.' asc'),
            'stock_desc' => $query->orderByRaw($effectiveStockSql.' desc'),
        };

        $products = $query->paginate(60)->withQueryString();

        $suppliers = Supplier::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = ProductCategory::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $countsBase = Product::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->when($request->filled('search'), fn ($q) => Translit::applyLike(
                $q, ['name'], (string) $request->string('search')
            ))
            ->when($request->filled('category_id'), fn ($q) => $q->where(
                'category_id', (int) $request->input('category_id')
            ));

        $typeCounts = [
            'all' => (clone $countsBase)->count(),
            'with' => (clone $countsBase)->where('has_types', true)->count(),
            'without' => (clone $countsBase)->where('has_types', false)->count(),
        ];

        // Ombordagi tovarlar joriy qiymati (sotuv narxida): types bo'lsa har
        // type stock*price yig'indisi, aks holda products.stock*products.price.
        // Manfiy qoldiq (ortiqcha sotilgan/qarz) omborda yo'q — 0 deb hisoblanadi.
        $stockValueSql = 'COALESCE(SUM(CASE WHEN products.has_types '
            .'THEN COALESCE((SELECT SUM(CASE WHEN stock > 0 THEN stock ELSE 0 END * price) '
            .'FROM product_types WHERE product_id = products.id), 0) '
            .'ELSE CASE WHEN products.stock > 0 THEN products.stock ELSE 0 END * products.price END), 0)';

        $stockValue = (int) round((float) (clone $countsBase)
            ->selectRaw($stockValueSql.' as total')
            ->value('total'));

        return Inertia::render('Dealer/Products/Index', [
            'products' => Inertia::scroll(fn () => ProductResource::collection($products)),
            'suppliers' => $suppliers,
            'categories' => $categories,
            'typeCounts' => $typeCounts,
            'stockValue' => $stockValue,
            'filters' => $request->only(['search', 'sort', 'category_id', 'has_types']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Dealer/Products/Create', [
            'categories' => ProductCategory::query()
                ->forDealer((int) $request->user()->dealer_id)
                ->active()
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['images', 'types', 'removed_type_ids']);
        $data['dealer_id'] = $request->user()->dealer_id;

        $product = DB::transaction(function () use ($data, $request): Product {
            $product = Product::query()->create($data);

            $this->images->attachMany($product, $request->file('images') ?? []);

            if ($request->boolean('has_types')) {
                $this->syncTypes->execute(
                    product: $product,
                    typeRows: (array) $request->input('types', []),
                    removedTypeIds: [],
                    request: $request,
                );
            }

            return $product;
        });

        event(new ProductCreated($product->load('dealer')));

        return redirect()
            ->route('dealer.products.index')
            ->with('status', 'Mahsulot yaratildi');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $product->load(['images', 'types.images']);

        return Inertia::render('Dealer/Products/Edit', [
            'product' => ProductResource::make($product),
            'categories' => ProductCategory::query()
                ->forDealer((int) $request->user()->dealer_id)
                ->active()
                ->get(['id', 'name']),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['images', 'types.images', 'category']);

        return ProductResource::make($product)->response();
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->safe()->except(['images', 'remove_image_ids', 'image_order', 'types', 'removed_type_ids']);

        $oldPrice = (float) $product->price;
        $oldPackPrice = $product->pack_price !== null ? (float) $product->pack_price : null;

        DB::transaction(function () use ($product, $request, $data): void {
            $product->update($data);

            $this->images->detachMany($product->id, (array) $request->input('remove_image_ids', []));

            $startSortOrder = $this->images->currentMaxSortOrder($product->id) + 1;
            $newImages = $this->images->attachMany(
                $product,
                $request->file('images') ?? [],
                $startSortOrder,
            );

            $this->images->reorder(
                productId: $product->id,
                order: (array) $request->input('image_order', []),
                newImages: $newImages,
            );

            $this->syncTypes->execute(
                product: $product,
                typeRows: (array) $request->input('types', []),
                removedTypeIds: array_map('intval', (array) $request->input('removed_type_ids', [])),
                request: $request,
            );
        });

        $this->priceNotifier->dispatchIfChanged($product->fresh()->load('dealer'), $oldPrice, $oldPackPrice);

        return redirect()
            ->route('dealer.products.index')
            ->with('status', 'Mahsulot yangilandi');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->images->detachAll($product);

        $product->delete();

        return redirect()
            ->route('dealer.products.index')
            ->with('status', 'Mahsulot o\'chirildi');
    }

    public function reorder(Request $request): Response
    {
        $this->authorize('reorder', Product::class);

        $dealerId = (int) $request->user()->dealer_id;

        $products = Product::query()
            ->forDealer($dealerId)
            ->with(['images', 'category'])
            ->when($request->filled('search'), fn ($q) => Translit::applyLike(
                $q, ['name'], (string) $request->string('search')
            ))
            ->when($request->filled('category_id'), fn ($q) => $q->where(
                'category_id', (int) $request->input('category_id')
            ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(60)
            ->withQueryString();

        $categories = ProductCategory::query()
            ->forDealer($dealerId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dealer/Products/Reorder', [
            'products' => Inertia::scroll(fn () => ProductResource::collection($products)),
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id']),
        ]);
    }

    public function updateOrder(ReorderProductsRequest $request): JsonResponse
    {
        $this->reorderAction->execute(
            dealerId: (int) $request->user()->dealer_id,
            orderedIds: $request->orderedIds(),
        );

        return response()->json(['ok' => true]);
    }

    public function toggleActive(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $this->authorize('toggleActive', $product);

        $product->update(['is_active' => ! $product->is_active]);

        if ($request->expectsJson()) {
            return response()->json(['is_active' => $product->is_active]);
        }

        return back()->with(
            'status',
            $product->is_active ? 'Mahsulot faollashtirildi' : 'Mahsulot nofaol qilindi',
        );
    }
}
