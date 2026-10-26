<?php

namespace App\Actions\Tenants;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Cache;

class MarketplaceStats
{
    public const CACHE_MINUTES = 10;

    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * The landing page's live numbers for the current marketplace, cached
     * for a few minutes: verified suppliers, listed products and the
     * countries those products come from.
     *
     * @return array{verified_suppliers: int, listed_products: int, countries: int}
     */
    public function handle(): array
    {
        $tenantId = $this->currentTenant->require()->id;

        return Cache::remember("tenant:{$tenantId}:landing-stats", now()->addMinutes(self::CACHE_MINUTES), fn (): array => [
            'verified_suppliers' => Company::query()->ofType(CompanyType::SUPPLIER)->verified()->count(),
            'listed_products' => Product::query()->active()->count(),
            'countries' => Product::query()->active()->whereNotNull('origin_country')->distinct()->count('origin_country'),
        ]);
    }
}
