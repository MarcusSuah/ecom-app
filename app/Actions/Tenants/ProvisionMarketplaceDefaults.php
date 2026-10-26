<?php

namespace App\Actions\Tenants;

use App\Enums\BillingInterval;
use App\Enums\PlanAudience;
use App\Enums\PlanLimit;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\CurrentTenant;

class ProvisionMarketplaceDefaults
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * Give a new marketplace the ten standard categories and a starter set of
     * seller plans. Existing records are left alone, so this can be re-run.
     */
    public function handle(Tenant $tenant): void
    {
        $this->currentTenant->runAs($tenant, function () use ($tenant): void {
            foreach (Category::DEFAULTS as $position => $name) {
                Category::query()->firstOrCreate(['name' => $name], ['sort_order' => ($position + 1) * 10]);
            }

            foreach ($this->sellerPlans($tenant) as $position => $plan) {
                Plan::query()->forSellersOf($tenant)->where('name', $plan['name'])->firstOr(fn () => Plan::create([
                    ...$plan,
                    'tenant_id' => $tenant->id,
                    'audience' => PlanAudience::SELLER,
                    'currency' => $tenant->default_currency,
                    'interval' => BillingInterval::MONTH,
                    'sort_order' => ($position + 1) * 10,
                ]));
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sellerPlans(Tenant $tenant): array
    {
        return [
            [
                'name' => 'Starter',
                'description' => 'List a few products and try the marketplace.',
                'price' => 0,
                'is_default' => true,
                'limits' => [
                    PlanLimit::MAX_PRODUCTS->value => 10,
                    PlanLimit::MONTHLY_QUOTES->value => 5,
                    PlanLimit::COMMISSION_PERCENT->value => 8,
                    PlanLimit::FEATURED_LISTING->value => false,
                ],
            ],
            [
                'name' => 'Growth',
                'description' => 'For sellers who respond to requests every week.',
                'price' => $tenant->default_currency === 'USD' ? 2000 : 25000,
                'trial_days' => 14,
                'limits' => [
                    PlanLimit::MAX_PRODUCTS->value => 100,
                    PlanLimit::MONTHLY_QUOTES->value => 50,
                    PlanLimit::COMMISSION_PERCENT->value => 5,
                    PlanLimit::FEATURED_LISTING->value => false,
                ],
            ],
            [
                'name' => 'Pro',
                'description' => 'Unlimited listings, lowest commission and featured placement.',
                'price' => $tenant->default_currency === 'USD' ? 6000 : 75000,
                'trial_days' => 14,
                'limits' => [
                    PlanLimit::MAX_PRODUCTS->value => null,
                    PlanLimit::MONTHLY_QUOTES->value => null,
                    PlanLimit::COMMISSION_PERCENT->value => 3,
                    PlanLimit::FEATURED_LISTING->value => true,
                ],
            ],
        ];
    }
}
