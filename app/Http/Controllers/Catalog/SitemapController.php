<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\CompanyType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Search engines' map of the current marketplace's public pages and
 * robots.txt pointing at it. Private areas are kept out of the index.
 */
class SitemapController extends Controller
{
    /**
     * More than this needs a sitemap index; far beyond a single marketplace today.
     */
    private const MAX_URLS = 50000;

    public function sitemap(Request $request, CurrentTenant $currentTenant): Response
    {
        $tenantId = $currentTenant->require()->id;

        $urls = Cache::remember("tenant:{$tenantId}:sitemap:{$request->getHost()}", now()->addHour(), fn (): array => array_slice([
            ...array_map(fn (string $route) => ['loc' => route($route), 'lastmod' => null], [
                'home', 'products.index', 'categories.index', 'suppliers.index', 'shops.index', 'rfqs.create', 'contact.create',
            ]),
            ...array_map(fn (string $page) => ['loc' => route('pages.show', $page), 'lastmod' => null], array_keys(ContentPageController::PAGES)),
            ...Category::query()->ordered()->get(['id', 'slug', 'updated_at'])
                ->map(fn (Category $category) => ['loc' => route('products.index', ['category' => $category->slug]), 'lastmod' => $category->updated_at?->toDateString()])
                ->all(),
            ...Product::query()->active()->orderBy('id')->get(['id', 'slug', 'updated_at'])
                ->map(fn (Product $product) => ['loc' => route('products.show', $product), 'lastmod' => $product->updated_at?->toDateString()])
                ->all(),
            ...Company::query()->ofType(CompanyType::SUPPLIER)->orderBy('id')->get(['id', 'slug', 'updated_at'])
                ->map(fn (Company $company) => ['loc' => route('suppliers.show', $company), 'lastmod' => $company->updated_at?->toDateString()])
                ->all(),
            ...Company::query()->ofType(CompanyType::SHOP)->whereHas('shop')->orderBy('id')->get(['id', 'slug', 'updated_at'])
                ->map(fn (Company $company) => ['loc' => route('shops.show', $company), 'lastmod' => $company->updated_at?->toDateString()])
                ->all(),
        ], 0, self::MAX_URLS));

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            ...array_map(fn (string $path) => "Disallow: /{$path}", ['admin', 'platform', 'dashboard', 'seller', 'supplier', 'shop/', 'company', 'cart', 'checkout', 'orders', 'messages', 'notifications', 'settings']),
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
