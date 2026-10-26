<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Money owed to sellers. Paid out by hand for now (bank transfer, MoMo),
 * then recorded here. Guarded by payouts.manage on the routes.
 */
class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $status = PayoutStatus::tryFrom($request->string('status')->toString()) ?? PayoutStatus::PENDING;

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => Payout::query()
                ->where('status', $status)
                ->with(['company:id,name,phone,email', 'order:id,number,total,currency,completed_at,buyer_id', 'order.buyer:id,name'])
                ->oldest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Payout $payout) => [
                    'id' => $payout->id,
                    'company' => $payout->company->name,
                    'contact' => $payout->company->phone ?? $payout->company->email,
                    'order' => $payout->order->number,
                    'gross' => Money::format($payout->gross, $payout->currency),
                    'commission' => Money::format($payout->commission, $payout->currency).' ('.(float) $payout->commission_rate.'%)',
                    'net' => Money::format($payout->net, $payout->currency),
                    'status' => $payout->status->value,
                    'reference' => $payout->reference,
                    'paid_at' => $payout->paid_at?->toISOString(),
                    // Shown in the row's quick-view modal.
                    'company_email' => $payout->company->email,
                    'company_phone' => $payout->company->phone,
                    'buyer' => $payout->order->buyer->name,
                    'order_completed_at' => $payout->order->completed_at?->toISOString(),
                    'created_at' => $payout->created_at?->toISOString(),
                ]),
            'filters' => ['status' => $status->value],
        ]);
    }

    public function markPaid(Request $request, Payout $payout): RedirectResponse
    {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:100']], [
            'reference.required' => 'Enter the transfer reference.',
        ]);

        if ($payout->status !== PayoutStatus::PENDING) {
            throw ValidationException::withMessages(['reference' => 'This payout has already been marked paid.']);
        }

        $payout->forceFill(['status' => PayoutStatus::PAID, 'paid_at' => now(), 'reference' => $validated['reference']])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Payout to {$payout->company->name} recorded."]);

        return back();
    }
}
