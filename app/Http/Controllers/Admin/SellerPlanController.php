<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\SavePlan;
use App\Enums\PlanAudience;
use App\Enums\PlanLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SaveSellerPlanRequest;
use App\Models\Plan;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plans this marketplace sells to its suppliers and shops.
 */
class SellerPlanController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Plan::class);

        return Inertia::render('Admin/Plans/Index', [
            'title' => 'Seller plans',
            'description' => 'What suppliers and shops on your marketplace can subscribe to. Companies without a plan use the default one.',
            'audience' => PlanAudience::SELLER->value,
            'plans' => Plan::query()->forSellersOf($this->currentTenant->require())
                ->withCount('subscriptions')
                ->ordered()
                ->get()
                ->map(fn (Plan $plan) => [
                    ...$plan->toSummary(),
                    'price_input' => Money::toMajor($plan->price, $plan->currency),
                    'subscriptions_count' => $plan->subscriptions_count,
                ]),
            'limitDefinitions' => $this->limitDefinitions(),
            'currencies' => Money::currencies(),
            'defaultCurrency' => $this->currentTenant->require()->default_currency,
            'routes' => ['store' => route('admin.plans.store'), 'base' => url('admin/plans')],
        ]);
    }

    public function store(SaveSellerPlanRequest $request, SavePlan $savePlan): RedirectResponse
    {
        $savePlan->handle(null, PlanAudience::SELLER, $this->currentTenant->require()->id, $request->planAttributes(), $request->limits());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan created.']);

        return back();
    }

    public function update(SaveSellerPlanRequest $request, Plan $plan, SavePlan $savePlan): RedirectResponse
    {
        $savePlan->handle($plan, PlanAudience::SELLER, $plan->tenant_id, $request->planAttributes(), $request->limits());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan saved.']);

        return back();
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Companies have subscribed to this plan. Mark it inactive instead.']);
        }

        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan deleted.']);

        return back();
    }

    /**
     * @return list<array{key: string, label: string, is_switch: bool}>
     */
    private function limitDefinitions(): array
    {
        return array_map(fn (PlanLimit $limit) => [
            'key' => $limit->value,
            'label' => $limit->label(),
            'is_switch' => $limit->isSwitch(),
        ], PlanAudience::SELLER->limits());
    }
}
