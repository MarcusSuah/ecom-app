<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Cache;

class CategoryTree
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * Top-level categories with their sub-categories and listed product
     * counts (a category's count includes its sub-categories). Counted from
     * the database and cached briefly per marketplace.
     *
     * @return list<array{id: int, name: string, slug: string, description: string|null, product_count: int, children: list<array{name: string, slug: string, product_count: int}>}>
     */
    public function handle(): array
    {
        $tenantId = $this->currentTenant->require()->id;

        return Cache::remember("tenant:{$tenantId}:category-product-counts", now()->addMinutes(5), function (): array {
            $counts = Product::query()
                ->where('status', ProductStatus::ACTIVE)
                ->whereIn('company_id', Company::query()->listable()->select('id'))
                ->selectRaw('category_id, count(*) as aggregate')
                ->groupBy('category_id')
                ->pluck('aggregate', 'category_id');

            $all = Category::query()->ordered()->get();

            return array_values($all->whereNull('parent_id')->map(fn (Category $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'description' => $parent->description,
                'product_count' => (int) ($counts[$parent->id] ?? 0)
                    + $all->where('parent_id', $parent->id)->sum(fn (Category $child) => (int) ($counts[$child->id] ?? 0)),
                'children' => array_values($all->where('parent_id', $parent->id)->map(fn (Category $child) => [
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'product_count' => (int) ($counts[$child->id] ?? 0),
                ])->all()),
            ])->all());
        });
    }
}
