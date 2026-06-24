<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreCategoryRequest;
use App\Http\Requests\Dealer\UpdateCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::query()
            ->forDealer((int) $request->user()->dealer_id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->paginate(30);

        return Inertia::render('Dealer/Categories/Index', [
            'categories' => ProductCategoryResource::collection($categories),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        ProductCategory::query()->create([
            ...$request->validated(),
            'dealer_id' => $request->user()->dealer_id,
        ]);

        return redirect()
            ->route('dealer.categories.index')
            ->with('status', 'Kategoriya yaratildi');
    }

    public function update(UpdateCategoryRequest $request, ProductCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return redirect()
            ->route('dealer.categories.index')
            ->with('status', 'Kategoriya yangilandi');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()
            ->route('dealer.categories.index')
            ->with('status', 'Kategoriya o\'chirildi');
    }
}
