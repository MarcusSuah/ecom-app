<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Products\ProductSearchFilters;
use App\Actions\Products\SearchProducts;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\CurrencyConverter;
use App\Support\CurrentTenant;
use App\Support\DisplayCurrency;
use App\Support\Money;
use App\Support\PageMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, SearchProducts $searchProducts, CurrentTenant $currentTenant): Response
    {
        $filters = ProductSearchFilters::fromRequest($request, $currentTenant->require()->default_currency);

        return Inertia::render('catalog/products/index', [
            'products' => $searchProducts->handle($filters)->through(fn (Product $product) => $product->toCard()),
            'filters' => [
                'q' => $filters->query,
                'category' => $filters->category->slug ?? '',
                'verified' => $filters->verifiedOnly,
                'max_moq' => $filters->maxMoq !== null ? (string) $filters->maxMoq : '',
                'min_price' => $request->string('min_price')->toString(),
                'max_price' => $request->string('max_price')->toString(),
                'currency' => $filters->currency,
                'sort' => $filters->sort,
            ],
            'categories' => Category::query()->whereNull('parent_id')->ordered()->get(['id', 'name', 'slug']),
            'currencies' => Money::currencies(),
        ]);
    }

    public function show(Request $request, Product $product, DisplayCurrency $displayCurrency, CurrencyConverter $converter): Response
    {
        // Unlisted products are hidden rather than refused, so their existence does not leak.
        abort_unless(Gate::forUser($request->user())->allows('view', $product), 404);

        $product->load(['priceTiers', 'images', 'category.parent', 'company.latestVerification']);

        return Inertia::render('catalog/products/show', [
            'meta' => PageMeta::make(
                $product->name,
                $product->description ?? "{$product->name} from {$product->company->name}. Minimum order {$product->moq} {$product->unit->value}.",
                $product->images->first()?->url(),
                'product',
            ),
            'product' => $this->details($product, $displayCurrency, $converter),
            'supplier' => $this->supplier($product),
            'canEdit' => $request->user() !== null && Gate::forUser($request->user())->allows('update', $product),
        ]);
    }

    /**
     * The product modal on the storefront: the same details as the page,
     * as JSON, so buyers can check tiers and add it to their inquiry list
     * without leaving the page.
     */
    public function quickView(Request $request, Product $product, DisplayCurrency $displayCurrency, CurrencyConverter $converter): JsonResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $product), 404);

        $product->load(['priceTiers', 'images', 'category.parent', 'company.latestVerification']);

        return response()->json([
            'product' => $this->details($product, $displayCurrency, $converter),
            'supplier' => $this->supplier($product),
            'url' => route('products.show', $product),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Product $product, DisplayCurrency $displayCurrency, CurrencyConverter $converter): array
    {
        $displayCode = $displayCurrency->code();
        $displayRate = $displayCode !== $product->currency ? $converter->rate($product->currency, $displayCode) : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status->value,
            'description' => $product->description,
            'unit' => $product->unit->value,
            'unit_label' => $product->unit->label(),
            'moq' => $product->moq,
            'lead_time' => $product->leadTimeLabel(),
            'currency' => $product->currency,
            'origin_country' => $product->origin_country,
            'sample_available' => $product->sample_available,
            ...$product->stockBadge(),
            'sample_price' => $product->sample_price !== null ? $displayCurrency->format($product->sample_price, $product->currency) : null,
            'category' => ['name' => $product->category->name, 'slug' => $product->category->slug, 'parent' => $product->category->parent?->name],
            'tiers' => $product->priceTiers->map(fn (PriceTier $tier) => [
                'min_qty' => $tier->min_qty,
                'unit_price' => $tier->unit_price,
                'formatted_unit_price' => $displayCurrency->format($tier->unit_price, $product->currency),
                'listed_unit_price' => $displayCurrency->listed($tier->unit_price, $product->currency),
            ])->values(),
            // Lets the page format live totals the same way the server does.
            'currency_subunits' => Money::subunits($product->currency),
            // For the approximate live total in the visitor's currency; null when it is the same.
            'display' => $displayRate !== null
                ? ['currency' => $displayCode, 'subunits' => Money::subunits($displayCode), 'rate' => $displayRate]
                : null,
            'images' => $product->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'thumbnail_url' => $image->thumbnailUrl(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplier(Product $product): array
    {
        $company = $product->company;

        return [
            'name' => $company->name,
            'slug' => $company->slug,
            'logo_url' => $company->logoUrl(),
            'city' => $company->city,
            'country' => $company->country,
            'is_verified' => $company->isVerified(),
            'member_since' => $company->created_at?->toISOString(),
            'listed_products' => $company->products()->active()->count(),
        ];
    }
}
