<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder as ScoutBuilder;
use UnexpectedValueException;

/**
 * The public catalogue query. Works with the Scout database engine (SQL on
 * the products table) and with Meilisearch (filterable attributes are
 * configured in config/scout.php). Every filter is a real products column
 * except "verified suppliers only", which differs per engine.
 */
class SearchProducts
{
    public const SORTS = ['relevance', 'price', 'moq', 'newest'];

    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(ProductSearchFilters $filters, int $perPage = 24, string $pageName = 'page'): LengthAwarePaginator
    {
        $search = Product::search($filters->query)
            // External engines ignore Eloquent global scopes, so the tenant and status are explicit.
            ->where('tenant_id', $this->currentTenant->require()->id)
            ->where('status', ProductStatus::ACTIVE->value);

        if ($filters->category) {
            $search->whereIn('category_id', $this->categoryIds($filters->category));
        }

        if ($filters->maxMoq !== null) {
            $search->where('moq', '<=', $filters->maxMoq);
        }

        if ($filters->minPrice !== null || $filters->maxPrice !== null) {
            // Prices are only comparable within one currency.
            $search->where('currency', $filters->currency);

            if ($filters->minPrice !== null) {
                $search->where('min_unit_price', '>=', $filters->minPrice);
            }

            if ($filters->maxPrice !== null) {
                $search->where('min_unit_price', '<=', $filters->maxPrice);
            }
        }

        $this->applySort($search, $filters->sort);

        $databaseEngine = Product::searchesDatabaseDirectly();

        if ($filters->verifiedOnly && ! $databaseEngine) {
            $search->where('supplier_verified', true);
        }

        $results = $search
            ->query(function (Builder $query) use ($filters, $databaseEngine): void {
                $query->with(['company.latestVerification', 'category', 'coverImage'])
                    // Suspended businesses and inactive owners are hidden from the storefront.
                    ->whereIn('company_id', Company::query()->listable()->select('id'));

                if ($filters->verifiedOnly && $databaseEngine) {
                    $query->whereIn('company_id', Company::query()->verified()->select('id'));
                }
            })
            ->paginate($perPage, $pageName);

        // Every Scout engine returns Laravel's paginator; callers rely on through().
        if (! $results instanceof LengthAwarePaginator) {
            throw new UnexpectedValueException('Scout returned an unexpected paginator.');
        }

        return $results->withQueryString();
    }

    /**
     * @param  ScoutBuilder<Product>  $search
     */
    private function applySort(ScoutBuilder $search, string $sort): void
    {
        match ($sort) {
            'price' => $search->orderBy('min_unit_price'),
            'moq' => $search->orderBy('moq'),
            'newest' => $search->orderBy('created_at', 'desc'),
            default => Product::searchesDatabaseDirectly() ? $search->orderBy('id', 'desc') : null,
        };

        if ($sort !== 'relevance') {
            $search->orderBy('id', 'desc');
        }
    }

    /**
     * A category matches its own products and those of its sub-categories.
     *
     * @return list<int>
     */
    private function categoryIds(Category $category): array
    {
        return array_values([$category->id, ...$category->children()->pluck('id')->map(fn (mixed $id) => (int) $id)->all()]);
    }
}
