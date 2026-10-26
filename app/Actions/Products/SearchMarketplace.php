<?php

namespace App\Actions\Products;

use App\Enums\CompanyType;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ShopProduct;
use App\Support\DisplayCurrency;
use Illuminate\Database\Eloquent\Builder;

/**
 * One search across the whole marketplace: wholesale products, local shop
 * items, shops, suppliers and categories. Matches names (and descriptions,
 * cities and categories where they exist) anywhere in the text. Suspended
 * businesses and inactive owners are left out (Company::listable).
 */
class SearchMarketplace
{
    public const MIN_LENGTH = 2;

    public function __construct(private DisplayCurrency $displayCurrency) {}

    /**
     * @return array{products: list<array<string, mixed>>, items: list<array<string, mixed>>, shops: list<array<string, mixed>>, suppliers: list<array<string, mixed>>, categories: list<array<string, mixed>>, total: int}
     */
    public function handle(string $query, int $perGroup): array
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return ['products' => [], 'items' => [], 'shops' => [], 'suppliers' => [], 'categories' => [], 'total' => 0];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
        $listable = Company::query()->listable()->select('id');

        $products = Product::query()
            ->where('status', ProductStatus::ACTIVE)
            ->whereIn('company_id', $listable)
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like)))
            ->with(['company.latestVerification', 'category', 'coverImage'])
            ->orderByRaw('case when name like ? then 0 else 1 end', [$like])
            ->orderBy('name')
            ->limit($perGroup)
            ->get();

        $items = ShopProduct::query()
            ->available()
            ->whereHas('shop', fn (Builder $shop) => $shop->whereIn('company_id', $listable))
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like)))
            ->with(['shop.company', 'category:id,name,slug'])
            ->orderByDesc('units_sold')
            ->limit($perGroup)
            ->get();

        $companies = fn (CompanyType $type) => Company::query()
            ->ofType($type)
            ->listable()
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('city', 'like', $like))
            ->with('latestVerification')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit($perGroup)
            ->get();

        $categories = Category::query()
            ->where('name', 'like', $like)
            ->with('parent:id,name')
            ->ordered()
            ->limit($perGroup)
            ->get();

        $result = [
            'products' => array_values($products->map(fn (Product $product) => $product->toCard())->all()),
            'items' => array_values($items->map(fn (ShopProduct $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $this->displayCurrency->format($item->price, $item->shop->currency),
                'image_url' => $item->imageUrl(),
                'category' => $item->category->name,
                ...$item->stockBadge(),
                'shop' => ['name' => $item->shop->company->name, 'slug' => $item->shop->company->slug],
            ])->all()),
            'shops' => array_values($companies(CompanyType::SHOP)->map(fn (Company $company) => $company->toSummary())->all()),
            'suppliers' => array_values($companies(CompanyType::SUPPLIER)->map(fn (Company $company) => $company->toSummary())->all()),
            'categories' => array_values($categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent' => $category->parent?->name,
            ])->all()),
        ];

        return [...$result, 'total' => array_sum(array_map('count', $result))];
    }
}
