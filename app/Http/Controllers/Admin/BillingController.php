<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\PayInvoice;
use App\Actions\Billing\StartSubscription;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\BillingOverview;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The marketplace's own subscription to the platform. Guarded by the
 * billing.manage permission on the route.
 */
class BillingController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function show(): Response
    {
        $tenant = $this->tenant();

        return Inertia::render('Admin/Billing', [
            'marketplace' => ['name' => $tenant->name, 'status' => $tenant->status->value, 'is_system' => $tenant->isSystem()],
            ...BillingOverview::for($tenant, Plan::query()->forMarketplaces()->where('is_active', true)->ordered()->get()),
        ]);
    }

    public function subscribe(Request $request, StartSubscription $startSubscription): RedirectResponse
    {
        $validated = $request->validate(['plan_id' => ['required', 'integer']]);
        $plan = Plan::query()->forMarketplaces()->whereKey($validated['plan_id'])->firstOrFail();

        $result = $startSubscription->handle($this->tenant(), $plan, route('admin.billing.show'));

        if ($result['checkout']?->redirectUrl) {
            return redirect()->away($result['checkout']->redirectUrl);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Your marketplace is on the {$plan->name} plan."]);

        return to_route('admin.billing.show');
    }

    public function cancel(CancelSubscription $cancelSubscription): RedirectResponse
    {
        $subscription = $this->tenant()->currentSubscription() ?? abort(404);

        $cancelSubscription->handle($subscription);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your marketplace plan will not renew.']);

        return to_route('admin.billing.show');
    }

    public function payInvoice(SubscriptionInvoice $invoice, PayInvoice $payInvoice): RedirectResponse
    {
        $tenant = $this->tenant();

        abort_unless($invoice->subscription->subscriber_type === $tenant->getMorphClass()
            && $invoice->subscription->subscriber_id === $tenant->id, 404);

        $checkout = $payInvoice->handle($invoice, route('admin.billing.show'));

        if ($checkout->redirectUrl) {
            return redirect()->away($checkout->redirectUrl);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invoice {$invoice->number} is paid."]);

        return to_route('admin.billing.show');
    }

    private function tenant(): Tenant
    {
        return $this->currentTenant->require();
    }
}
