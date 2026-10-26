<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\PayInvoice;
use App\Actions\Billing\StartSubscription;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Support\BillingOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A company's seller plan on its marketplace.
 */
class CompanyBillingController extends Controller
{
    public function show(Request $request): Response
    {
        $company = $this->company($request);

        return Inertia::render('company/billing', [
            'company' => $company->toSummary(),
            ...BillingOverview::for($company, Plan::query()->forSellersOf($company->tenant_id)->where('is_active', true)->ordered()->get()),
        ]);
    }

    public function subscribe(Request $request, StartSubscription $startSubscription): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $request->validate(['plan_id' => ['required', 'integer']]);

        $plan = Plan::query()->forSellersOf($company->tenant_id)->whereKey($validated['plan_id'])->firstOrFail();
        $result = $startSubscription->handle($company, $plan, route('company.billing.show'));

        if ($result['checkout']?->redirectUrl) {
            return redirect()->away($result['checkout']->redirectUrl);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "You are now on the {$plan->name} plan."]);

        return to_route('company.billing.show');
    }

    public function cancel(Request $request, CancelSubscription $cancelSubscription): RedirectResponse
    {
        $company = $this->company($request);
        $subscription = $company->currentSubscription() ?? abort(404);

        $cancelSubscription->handle($subscription);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your plan will not renew. You keep it until the end of the paid period.']);

        return to_route('company.billing.show');
    }

    public function payInvoice(Request $request, SubscriptionInvoice $invoice, PayInvoice $payInvoice): RedirectResponse
    {
        $company = $this->company($request);

        abort_unless($invoice->subscription->subscriber_type === $company->getMorphClass()
            && $invoice->subscription->subscriber_id === $company->id, 404);

        $checkout = $payInvoice->handle($invoice, route('company.billing.show'));

        if ($checkout->redirectUrl) {
            return redirect()->away($checkout->redirectUrl);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invoice {$invoice->number} is paid."]);

        return to_route('company.billing.show');
    }

    private function company(Request $request): Company
    {
        $company = $request->user()->currentCompany() ?? abort(404);
        Gate::authorize('manageBilling', $company);

        return $company;
    }
}
