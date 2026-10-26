<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\CollectCashPayment;
use App\Actions\Orders\OrderStateMachine;
use App\Actions\Payments\RefundPayment;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController as BuyerOrderController;
use App\Models\Order;
use App\Models\Payout;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders a supplier or shop received, and the payouts they earned.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany() ?? abort(403);
        $status = OrderStatus::tryFrom($request->string('status')->toString());

        return Inertia::render('seller/orders/index', [
            'orders' => Order::query()
                ->where('seller_company_id', $company->id)
                // Unpaid orders are not the seller's to act on yet.
                ->where('status', '!=', OrderStatus::PENDING_PAYMENT)
                ->when($status, fn ($query) => $query->where('status', $status))
                ->with('buyer:id,name')
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Order $order) => [...$order->toSummary(), 'buyer' => $order->buyer->name]),
            'filters' => ['status' => $status->value ?? ''],
            'statuses' => collect(OrderStatus::cases())
                ->reject(fn (OrderStatus $case) => $case === OrderStatus::PENDING_PAYMENT)
                ->map(fn (OrderStatus $case) => ['value' => $case->value, 'label' => $case->label()])
                ->values(),
        ]);
    }

    public function show(Order $order): Response
    {
        Gate::authorize('fulfil', $order);

        return Inertia::render('seller/orders/show', [
            ...BuyerOrderController::detail($order),
            'canRefund' => Gate::allows('refund', $order),
            'canCollectCash' => Gate::allows('collectCash', $order),
            'canCancelCash' => Gate::allows('cancelCash', $order),
        ]);
    }

    /**
     * Fulfilment steps only; paying, completing and refunding have their own flows.
     */
    public function updateStatus(Request $request, Order $order, OrderStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('fulfil', $order);

        $validated = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);
        $status = OrderStatus::from($validated['status']);

        abort_unless(in_array($status, OrderStateMachine::sellerSteps($order), true) || ! $stateMachine->canMove($order, $status), 403);

        $stateMachine->transition($order, $status, $request->user());

        return back();
    }

    public function collectCash(Request $request, Order $order, CollectCashPayment $collectCashPayment): RedirectResponse
    {
        Gate::authorize('collectCash', $order);

        $collectCashPayment->handle($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cash recorded and the order completed.']);

        return back();
    }

    public function cancelCash(Request $request, Order $order, OrderStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('cancelCash', $order);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Tell the buyer why the order is cancelled.',
        ]);

        $stateMachine->transition($order, OrderStatus::CANCELLED, $request->user(), "Cancelled by the seller: {$validated['reason']}");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order cancelled. Nothing was paid, so there is nothing to refund.']);

        return back();
    }

    public function refund(Request $request, Order $order, RefundPayment $refundPayment): RedirectResponse
    {
        Gate::authorize('refund', $order);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Tell the buyer why the order is cancelled.',
        ]);

        $refundPayment->handle($order, $request->user(), "Cancelled by the seller: {$validated['reason']}");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order cancelled and the buyer refunded.']);

        return back();
    }

    public function payouts(Request $request): Response
    {
        $company = $request->user()->currentCompany() ?? abort(403);

        return Inertia::render('seller/payouts', [
            'payouts' => Payout::query()
                ->where('company_id', $company->id)
                ->with('order:id,number')
                ->latest('id')
                ->paginate(30)
                ->through(fn (Payout $payout) => [
                    'id' => $payout->id,
                    'order' => $payout->order->number,
                    'order_id' => $payout->order_id,
                    'gross' => Money::format($payout->gross, $payout->currency),
                    'commission_rate' => (float) $payout->commission_rate,
                    'commission' => Money::format($payout->commission, $payout->currency),
                    'net' => Money::format($payout->net, $payout->currency),
                    'status' => $payout->status->value,
                    'paid_at' => $payout->paid_at?->toISOString(),
                    'reference' => $payout->reference,
                    'created_at' => $payout->created_at?->toISOString(),
                ]),
            'pendingTotal' => Payout::query()->where('company_id', $company->id)->where('status', PayoutStatus::PENDING)
                ->selectRaw('currency, sum(net) as total')->groupBy('currency')->get()
                ->map(fn ($row) => Money::format((int) $row->getAttribute('total'), (string) $row->getAttribute('currency')))->values(),
        ]);
    }
}
