<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Billing\SavePlan;
use App\Enums\PlanAudience;
use App\Enums\PlanLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SaveMarketplacePlanRequest;
use App\Models\Plan;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The plans the platform sells to marketplaces.
 */
class MarketplacePlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'title' => 'Marketplace plans',
            'description' => 'What marketplaces pay the platform. A marketplace without a current plan is closed to visitors.',
            'audience' => PlanAudience::MARKETPLACE->value,
            'plans' => Plan::query()->forMarketplaces()
                ->withCount('subscriptions')
                ->ordered()
                ->get()
                ->map(fn (Plan $plan) => [
                    ...$plan->toSummary(),
                    'price_input' => Money::toMajor($plan->price, $plan->currency),
                    'subscriptions_count' => $plan->subscriptions_count,
                ]),
            'limitDefinitions' => array_map(fn (PlanLimit $limit) => [
                'key' => $limit->value,
                'label' => $limit->label(),
                'is_switch' => $limit->isSwitch(),
            ], PlanAudience::MARKETPLACE->limits()),
            'currencies' => Money::currencies(),
            'defaultCurrency' => 'USD',
            'routes' => ['store' => route('platform.plans.store'), 'base' => url('platform/plans')],
        ]);
    }

    public function store(SaveMarketplacePlanRequest $request, SavePlan $savePlan): RedirectResponse
    {
        $savePlan->handle(null, PlanAudience::MARKETPLACE, null, $request->planAttributes(), $request->limits());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan created.']);

        return back();
    }

    public function update(SaveMarketplacePlanRequest $request, Plan $plan, SavePlan $savePlan): RedirectResponse
    {
        abort_unless($plan->audience === PlanAudience::MARKETPLACE, 404);

        $savePlan->handle($plan, PlanAudience::MARKETPLACE, null, $request->planAttributes(), $request->limits());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan saved.']);

        return back();
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        abort_unless($plan->audience === PlanAudience::MARKETPLACE, 404);

        if ($plan->subscriptions()->withoutGlobalScopes()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Marketplaces have subscribed to this plan. Mark it inactive instead.']);
        }

        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan deleted.']);

        return back();
    }
}
