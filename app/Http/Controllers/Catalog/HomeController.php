<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Products\CategoryTree;
use App\Actions\Products\ProductSearchFilters;
use App\Actions\Products\SearchProducts;
use App\Actions\Shops\FindShops;
use App\Actions\Suppliers\SupplierResponseTime;
use App\Actions\Tenants\MarketplaceStats;
use App\Enums\CompanyType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RfqController;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ShopProduct;
use App\Support\CurrentTenant;
use App\Support\CustomerLocation;
use App\Support\DisplayCurrency;
use App\Support\PageMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The marketplace's landing page. Each section is a closure, so the
 * product and shop filters reload only their own section.
 */
class HomeController extends Controller
{
    /**
     * Per "Show more": every listed product, shop item, shop and supplier is
     * reachable from the home page; only suspended businesses and inactive
     * owners are left out (Company::listable).
     */
    private const PRODUCTS_PER_PAGE = 12;

    private const SUPPLIERS_PER_PAGE = 8;

    private const LOCAL_ITEMS_PER_PAGE = 12;

    private const SHOPS_PER_PAGE = 8;

    public function __invoke(
        Request $request,
        CurrentTenant $currentTenant,
        MarketplaceStats $stats,
        CategoryTree $categoryTree,
        SearchProducts $searchProducts,
        FindShops $findShops,
        SupplierResponseTime $responseTime,
        DisplayCurrency $displayCurrency,
    ): Response {
        $tenant = $currentTenant->require();
        $location = CustomerLocation::get($request);
        $deliversOnly = $request->boolean('delivers') && $location !== null;
        $productFilters = ProductSearchFilters::fromRequest($request, $tenant->default_currency);

        return Inertia::render('welcome', [
            'meta' => PageMeta::make(null, "Buy in bulk from suppliers you can check on {$tenant->name}: tiered prices, minimum orders and lead times up front, one request for quotes to several suppliers, and local shops that deliver."),
            'stats' => fn (): array => $stats->handle(),
            'categories' => fn (): array => array_map(
                fn (array $category) => array_intersect_key($category, array_flip(['id', 'name', 'slug', 'product_count', 'children'])),
                $categoryTree->handle(),
            ),
            'products' => Inertia::scroll(fn () => $searchProducts
                ->handle($productFilters, self::PRODUCTS_PER_PAGE, pageName: 'products')
                ->through(fn (Product $product) => $product->toCard())),
            'productFilters' => [
                'verified' => $productFilters->verifiedOnly,
                'max_moq' => $productFilters->maxMoq !== null ? (string) $productFilters->maxMoq : '',
                'sort' => $productFilters->sort,
                'category' => $productFilters->category->slug ?? '',
            ],
            'localItems' => Inertia::scroll(fn () => $this->localItems($request, $findShops->handle($location, $deliversOnly), $displayCurrency)),
            'shops' => Inertia::scroll(fn () => $this->page($request, $findShops->handle($location, $deliversOnly), self::SHOPS_PER_PAGE, 'shops')),
            'shopFilters' => ['delivers' => $deliversOnly],
            'location' => $location,
            'neighbourhoods' => config('marketplace.neighbourhoods'),
            'roadFactor' => (float) config('marketplace.road_factor'),
            'suppliers' => Inertia::scroll(fn () => $this->suppliers($responseTime)),
            'rfqForm' => fn (): array => RfqController::formOptions(),
        ]);
    }

    /**
     * Every available item of the listed shops, nearest shops first (by name
     * without a location), best sellers first within a shop.
     *
     * @param  list<array<string, mixed>>  $shops  FindShops cards, in order
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function localItems(Request $request, array $shops, DisplayCurrency $displayCurrency): LengthAwarePaginator
    {
        $shopsById = collect($shops)->keyBy('id');
        $order = $shopsById->keys()->flip();

        $items = ShopProduct::query()
            ->available()
            ->whereIn('shop_id', $shopsById->keys())
            ->with(['category:id,name,slug', 'shop:id,currency'])
            ->orderByDesc('units_sold')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ShopProduct $item) => $order[$item->shop_id] ?? PHP_INT_MAX)
            ->values()
            ->map(fn (ShopProduct $item): array => $this->localItem($item, $shopsById[$item->shop_id], $displayCurrency));

        return $this->page($request, $items->all(), self::LOCAL_ITEMS_PER_PAGE, 'items');
    }

    /**
     * @param  array<string, mixed>  $shop  The item's FindShops card
     * @return array<string, mixed>
     */
    private function localItem(ShopProduct $item, array $shop, DisplayCurrency $displayCurrency): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => ['name' => $item->category->name, 'slug' => $item->category->slug],
            'price' => $displayCurrency->format($item->price, $item->shop->currency),
            'price_listed' => $displayCurrency->listed($item->price, $item->shop->currency),
            'image_url' => $item->imageUrl(),
            'stock' => $item->stock,
            ...$item->stockBadge(),
            'shop' => [
                'name' => $shop['name'],
                'slug' => $shop['slug'],
                'is_open' => $shop['is_open'],
                'offers_pickup' => $shop['offers_pickup'],
                'offers_delivery' => $shop['offers_delivery'],
                'base_fee' => $shop['base_fee'],
                'delivery' => $shop['delivery'],
            ],
        ];
    }

    /**
     * One page of an in-memory list, for lists ordered by distance.
     *
     * @template TItem
     *
     * @param  array<int, TItem>  $rows
     * @return LengthAwarePaginator<int, TItem>
     */
    private function page(Request $request, array $rows, int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = max(1, $request->integer($pageName, 1));
        $all = collect(array_values($rows));

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName],
        );
    }

    /**
     * Every listed supplier: featured first, then those with the most
     * listings, each with its three biggest categories.
     *
     * @return LengthAwarePaginator<int, non-empty-array<string, mixed>>
     */
    private function suppliers(SupplierResponseTime $responseTime): LengthAwarePaginator
    {
        $paginator = Company::query()
            ->ofType(CompanyType::SUPPLIER)
            ->listable()
            ->with('latestVerification')
            ->withCount(['products as listed_products_count' => fn (Builder $query) => $query->where('status', ProductStatus::ACTIVE)])
            ->orderByDesc('is_featured')
            ->orderByDesc('listed_products_count')
            ->orderBy('id')
            ->paginate(self::SUPPLIERS_PER_PAGE, pageName: 'suppliers');
        $suppliers = $paginator->getCollection();

        $counts = Product::query()
            ->active()
            ->whereIn('company_id', $suppliers->modelKeys())
            ->selectRaw('company_id, category_id, count(*) as aggregate')
            ->groupBy('company_id', 'category_id')
            ->get();
        $categoryNames = Category::query()->whereIn('id', $counts->pluck('category_id')->unique())->pluck('name', 'id');

        return $paginator->setCollection($suppliers->map(fn (Company $company) => [
            ...$company->toSummary(),
            'listed_products' => $company->listed_products_count,
            'response_time' => $responseTime->label($company),
            'top_categories' => $counts->where('company_id', $company->id)
                ->sortByDesc('aggregate')
                ->take(3)
                ->map(fn (Product $row) => $categoryNames[$row->category_id] ?? null)
                ->filter()
                ->values()
                ->all(),
        ]));
    }
}
