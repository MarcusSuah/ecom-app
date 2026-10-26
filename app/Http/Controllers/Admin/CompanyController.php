<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\ActivatePlanManually;
use App\Actions\Companies\SetCompanyFeatured;
use App\Enums\CompanyType;
use App\Enums\PlanLimit;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ActivatePlanRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\ActingCompany;
use App\Support\Audit;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        Gate::authorize('viewAny', Company::class);

        $type = CompanyType::tryFrom($request->string('type')->toString());
        $search = $request->string('search')->trim()->toString();

        $companies = Company::query()
            ->with(['latestVerification', 'owner:id,name,email'])
            ->when($type, fn ($query) => $query->ofType($type))
            ->when($request->boolean('verified'), fn ($query) => $query->verified())
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $sellerPlans = Plan::query()->forSellersOf($currentTenant->require())->ordered()->get();
        $defaultPlan = $sellerPlans->first(fn (Plan $plan) => $plan->is_default && $plan->is_active);
        $currentPlans = $this->currentPlans(array_values($companies->getCollection()->modelKeys()));

        $companies->through(function (Company $company) use ($currentPlans, $defaultPlan): array {
            $plan = $currentPlans[$company->id] ?? $defaultPlan;

            return [
                ...$company->toSummary(),
                'owner' => ['name' => $company->owner->name, 'email' => $company->owner->email],
                'plan' => $plan?->name,
                // Featuring needs a plan with featured listings (SetCompanyFeatured).
                'can_be_featured' => (bool) $plan?->limit(PlanLimit::FEATURED_LISTING),
                'suspended_at' => $company->suspended_at?->toISOString(),
                'suspended_reason' => $company->suspended_reason,
            ];
        });

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'type' => $type->value ?? '',
                'verified' => $request->boolean('verified'),
                'search' => $search,
            ],
            'sellerPlans' => $sellerPlans->map(fn (Plan $plan) => ['id' => $plan->id, 'name' => $plan->name])->values(),
        ]);
    }

    public function feature(Request $request, Company $company, SetCompanyFeatured $setCompanyFeatured): RedirectResponse
    {
        Gate::authorize('feature', $company);

        $validated = $request->validate(['is_featured' => ['required', 'boolean']]);
        $setCompanyFeatured->handle($company, $validated['is_featured']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $validated['is_featured'] ? "{$company->name} is now featured." : "{$company->name} is no longer featured."]);

        return back();
    }

    /**
     * Hides a business from the storefront (listings, search, its pages)
     * until reinstated.
     */
    public function suspend(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('suspend', $company);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $company->forceFill(['suspended_at' => now(), 'suspended_reason' => $validated['reason']])->save();
        Audit::record('suspend', 'companies', "Suspended {$company->name}", null, ['reason' => $validated['reason']], (string) $company->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$company->name} is suspended and hidden from the storefront."]);

        return back();
    }

    public function reinstate(Company $company): RedirectResponse
    {
        Gate::authorize('suspend', $company);

        $company->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        Audit::record('reinstate', 'companies', "Reinstated {$company->name}", null, null, (string) $company->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$company->name} is back on the storefront."]);

        return back();
    }

    /**
     * Opens the business's seller screens for the super admin (ActingCompany).
     */
    public function manage(Company $company, ActingCompany $actingCompany): RedirectResponse
    {
        Gate::authorize('manage', $company);

        $actingCompany->start($company);
        Inertia::flash('toast', ['type' => 'info', 'message' => "You are managing {$company->name}. Changes are made on its behalf."]);

        return $company->isShop() ? to_route('shop.products.index') : to_route('supplier.products.index');
    }

    public function stopManaging(ActingCompany $actingCompany): RedirectResponse
    {
        $actingCompany->stop();

        return to_route('admin.companies.index');
    }

    public function activatePlan(ActivatePlanRequest $request, Company $company, ActivatePlanManually $activatePlanManually): RedirectResponse
    {
        Gate::authorize('manageSubscription', $company);

        $plan = Plan::query()->forSellersOf($company->tenant_id)->whereKey($request->integer('plan_id'))->firstOrFail();
        $activatePlanManually->handle($company, $plan, $request->integer('periods'), $request->user(), $request->validated('reference'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$company->name} is on the {$plan->name} plan."]);

        return back();
    }

    /**
     * The plan name of each company's current subscription, in one query.
     *
     * @param  list<int|string>  $companyIds
     * @return array<int, Plan>
     */
    private function currentPlans(array $companyIds): array
    {
        return Subscription::query()
            ->with('plan')
            ->where('subscriber_type', (new Company)->getMorphClass())
            ->whereIn('subscriber_id', $companyIds)
            ->whereIn('status', SubscriptionStatus::live())
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', SubscriptionStatus::CANCELLED)
                ->orWhere('ends_at', '>', now()))
            ->latest('id')
            ->get()
            ->unique('subscriber_id')
            ->mapWithKeys(fn (Subscription $subscription) => [$subscription->subscriber_id => $subscription->plan])
            ->all();
    }
}
