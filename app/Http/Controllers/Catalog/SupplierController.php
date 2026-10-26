<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Suppliers\SupplierResponseTime;
use App\Enums\CompanyType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Support\PageMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = mb_substr($request->string('q')->trim()->toString(), 0, 100);
        $verifiedOnly = $request->boolean('verified');

        $suppliers = Company::query()
            ->listable()
            ->ofType(CompanyType::SUPPLIER)
            ->with('latestVerification')
            ->withCount(['products as listed_products_count' => fn (Builder $query) => $query->where('status', ProductStatus::ACTIVE)])
            ->when($verifiedOnly, fn ($query) => $query->verified())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Company $company) => [
                ...$company->toSummary(),
                'listed_products' => $company->listed_products_count,
            ]);

        return Inertia::render('catalog/suppliers/index', [
            'suppliers' => $suppliers,
            'filters' => ['q' => $search, 'verified' => $verifiedOnly],
        ]);
    }

    public function show(Company $company, SupplierResponseTime $responseTime): Response
    {
        abort_unless($company->isSupplier() && $company->isListable(), 404);

        $company->load('latestVerification');

        $topCategoryCounts = $company->products()->active()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->orderByDesc('aggregate')
            ->limit(3)
            ->pluck('aggregate', 'category_id');
        $topCategories = Category::query()->whereIn('id', $topCategoryCounts->keys())->get()
            ->sortByDesc(fn (Category $category) => $topCategoryCounts[$category->id])
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'product_count' => (int) $topCategoryCounts[$category->id],
            ])->values();

        $products = $company->products()->active()
            ->with(['company.latestVerification', 'category', 'coverImage'])
            ->latest('id')
            ->paginate(12)
            ->through(fn (Product $product) => $product->toCard());

        return Inertia::render('catalog/suppliers/show', [
            'meta' => PageMeta::make(
                $company->name,
                $company->description ?? "{$company->name}, a wholesale supplier in ".($company->city ?? $company->country).'. See their products, tier prices and response time.',
                $company->logoUrl(),
            ),
            'supplier' => [
                ...$company->toSummary(),
                'description' => $company->description,
                'member_since' => $company->created_at?->toISOString(),
                'response_time' => $responseTime->label($company),
            ],
            'topCategories' => $topCategories,
            'products' => $products,
        ]);
    }
}
