<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Billing\ActivatePlanManually;
use App\Actions\Tenants\CreateTenant;
use App\Enums\CompanyType;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ActivatePlanRequest;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Requests\Platform\UpdateTenantRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\BillingOverview;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketplaces on the platform. Only reachable by platform admins, with no
 * tenant active (see EnsurePlatformAdmin).
 */
class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $tenants = Tenant::query()
            ->withCount('companies')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('domain', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'status' => $tenant->status->value,
                'companies_count' => $tenant->companies_count,
                'plan' => $tenant->currentSubscription()?->plan->name,
            ]);

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => ['search' => $search],
            'centralDomain' => config('tenancy.central_domain'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Platform/Tenants/Create', [
            'plans' => Plan::query()->forMarketplaces()->where('is_active', true)->ordered()->get()->map(fn (Plan $plan) => $plan->toSummary()),
            'currencies' => Money::currencies(),
            'centralDomain' => config('tenancy.central_domain'),
        ]);
    }

    public function store(StoreTenantRequest $request, CreateTenant $createTenant): RedirectResponse
    {
        $tenant = $createTenant->handle($request->tenantAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tenant->name} is ready."]);

        return to_route('platform.tenants.show', $tenant);
    }

    public function show(Tenant $tenant): Response
    {
        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'status' => $tenant->status->value,
                'default_currency' => $tenant->default_currency,
                'support_email' => $tenant->support_email,
                'created_at' => $tenant->created_at?->toISOString(),
            ],
            'counts' => [
                'suppliers' => $tenant->companies()->ofType(CompanyType::SUPPLIER)->count(),
                'shops' => $tenant->companies()->ofType(CompanyType::SHOP)->count(),
            ],
            'statuses' => array_map(fn (TenantStatus $status) => $status->value, TenantStatus::cases()),
            'currencies' => Money::currencies(),
            ...BillingOverview::for($tenant, Plan::query()->forMarketplaces()->ordered()->get()),
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marketplace saved.']);

        return back();
    }

    public function activatePlan(ActivatePlanRequest $request, Tenant $tenant, ActivatePlanManually $activatePlanManually): RedirectResponse
    {
        $plan = Plan::query()->forMarketplaces()->whereKey($request->integer('plan_id'))->firstOrFail();
        $activatePlanManually->handle($tenant, $plan, $request->integer('periods'), $request->user(), $request->validated('reference'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tenant->name} is on the {$plan->name} plan."]);

        return back();
    }
}
