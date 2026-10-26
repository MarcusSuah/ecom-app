<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Shops\SaveShopProduct;
use App\Enums\PlanLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shops\ShopProductRequest;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\ShopProductImage;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A local shop's items.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $shop = $this->shop($request);

        if (! $shop) {
            return to_route('shop.settings.edit');
        }

        return Inertia::render('shop/products/index', [
            'products' => $shop->products()
                ->with('category:id,name')
                ->latest('id')
                ->paginate(30)
                ->through(fn (ShopProduct $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name,
                    'price' => Money::format($product->price, $shop->currency),
                    'stock' => $product->stock,
                    'is_active' => $product->is_active,
                    'image_url' => $product->imageUrl(),
                    ...$product->stockBadge(),
                ]),
            'listing' => [
                'listed' => $shop->products()->where('is_active', true)->count(),
                'limit' => $shop->company->planLimit(PlanLimit::MAX_PRODUCTS),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ShopProduct::class);

        return Inertia::render('shop/products/form', [
            'product' => null,
            'currency' => $this->shop($request)?->currency,
            'categories' => $this->categories(),
            'maxImages' => ShopProduct::MAX_IMAGES,
        ]);
    }

    public function store(ShopProductRequest $request, SaveShopProduct $saveShopProduct): RedirectResponse
    {
        $shop = $this->shop($request) ?? abort(403);
        $saveShopProduct->handle($shop, null, $request->productAttributes($shop->currency), $request->galleryUploads(), [], $request->galleryCover());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item added.']);

        return to_route('shop.products.index');
    }

    public function edit(Request $request, ShopProduct $shopProduct): Response
    {
        Gate::authorize('update', $shopProduct);

        return Inertia::render('shop/products/form', [
            'product' => [
                'id' => $shopProduct->id,
                'name' => $shopProduct->name,
                'category_id' => $shopProduct->category_id,
                'description' => $shopProduct->description ?? '',
                'price' => Money::toMajor($shopProduct->price, $shopProduct->shop->currency),
                'stock' => $shopProduct->stock,
                'is_active' => $shopProduct->is_active,
                'image_url' => $shopProduct->imageUrl(),
                'images' => $shopProduct->images->map(fn (ShopProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'thumbnail_url' => $image->thumbnailUrl(),
                ])->values(),
                ...$shopProduct->stockBadge(),
            ],
            'currency' => $shopProduct->shop->currency,
            'categories' => $this->categories(),
            'maxImages' => ShopProduct::MAX_IMAGES,
        ]);
    }

    public function update(ShopProductRequest $request, ShopProduct $shopProduct, SaveShopProduct $saveShopProduct): RedirectResponse
    {
        $saveShopProduct->handle(
            $shopProduct->shop,
            $shopProduct,
            $request->productAttributes($shopProduct->shop->currency),
            $request->galleryUploads(),
            $request->galleryRemovals(),
            $request->galleryCover(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item saved.']);

        return to_route('shop.products.index');
    }

    /**
     * Past orders keep their own copy of the name and price.
     */
    public function destroy(ShopProduct $shopProduct): RedirectResponse
    {
        Gate::authorize('delete', $shopProduct);

        if ($shopProduct->image) {
            Storage::disk('public')->delete($shopProduct->image);
        }
        $shopProduct->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item deleted.']);

        return to_route('shop.products.index');
    }

    private function shop(Request $request): ?Shop
    {
        $company = $request->user()->currentCompany();
        abort_unless($company?->isShop(), 403);

        return $company->shop;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categories(): array
    {
        return array_values(Category::query()->with('parent:id,name')->ordered()->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->parent ? "{$category->parent->name} › {$category->name}" : $category->name,
            ])->sortBy('name')->all());
    }
}
