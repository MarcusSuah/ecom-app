<?php

namespace App\Http\Controllers;

use App\Actions\Disputes\OpenDispute;
use App\Actions\Orders\ChooseCashOnPickup;
use App\Actions\Orders\ConfirmReceipt;
use App\Actions\Orders\OrderStateMachine;
use App\Actions\Payments\InitiatePayment;
use App\Enums\DisputeReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusChange;
use App\Services\Payments\PaymentGateways;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A buyer's orders: paying, confirming receipt and disputes.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('orders/index', [
            'orders' => Order::query()
                ->where('buyer_id', $request->user()->id)
                ->with(['seller:id,name,slug,phone', 'items:id,order_id,name,quantity,line_total'])
                ->latest('id')
                ->paginate(20)
                ->through(fn (Order $order) => [
                    ...$order->toSummary(),
                    'seller' => $order->seller->name,
                    'seller_phone' => $order->seller->phone,
                    // For the quick view on the list.
                    'items' => $order->items->map(fn (OrderItem $item) => [
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'line_total' => Money::format($item->line_total, $order->currency),
                    ])->values(),
                ]),
        ]);
    }

    public function show(Order $order, PaymentGateways $gateways): Response
    {
        Gate::authorize('view', $order);

        return Inertia::render('orders/show', [
            ...self::detail($order),
            'paymentProviders' => array_map(fn (string $name) => ['value' => $name, 'label' => PaymentGateways::label($name)], $gateways->enabled()),
            'disputeReasons' => array_map(fn (DisputeReason $reason) => ['value' => $reason->value, 'label' => $reason->label()], DisputeReason::cases()),
            'autoReleaseDays' => (int) config('payments.auto_release_days'),
        ]);
    }

    public function pay(Request $request, Order $order, InitiatePayment $initiatePayment, PaymentGateways $gateways): HttpResponse
    {
        Gate::authorize('pay', $order);

        $validated = $request->validate([
            'provider' => ['required', Rule::in($gateways->enabled())],
            'phone' => [Rule::requiredIf($request->input('provider') === 'mtn_momo'), 'nullable', 'string', 'regex:/^\+?\d{9,15}$/'],
        ], [
            'phone.required' => 'Enter the Mobile Money number to charge.',
            'phone.regex' => 'Enter the number in international format, e.g. +250788123456.',
        ]);

        $result = $initiatePayment->handle($order, $validated['provider'], $validated['phone'] ?? null);

        if ($result['initiation']->redirectUrl) {
            return Inertia::location($result['initiation']->redirectUrl);
        }

        Inertia::flash('toast', $result['payment']->status === PaymentStatus::HELD
            ? ['type' => 'success', 'message' => 'Payment received. The seller has been told.']
            : ['type' => 'info', 'message' => 'Approve the payment on your phone. This page updates when it is confirmed.']);

        return back();
    }

    public function payCash(Request $request, Order $order, ChooseCashOnPickup $chooseCashOnPickup): RedirectResponse
    {
        Gate::authorize('payCash', $order);

        $chooseCashOnPickup->handle($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "You'll pay {$order->toSummary()['total']} in cash when you collect the order. The shop has been told."]);

        return back();
    }

    public function cancel(Request $request, Order $order, OrderStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $stateMachine->transition($order, OrderStatus::CANCELLED, $request->user(), 'Cancelled by the buyer');

        return back();
    }

    public function confirmReceipt(Request $request, Order $order, ConfirmReceipt $confirmReceipt): RedirectResponse
    {
        Gate::authorize('confirmReceipt', $order);

        $confirmReceipt->handle($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks. The payment has been released to the seller.']);

        return back();
    }

    public function dispute(Request $request, Order $order, OpenDispute $openDispute): RedirectResponse
    {
        Gate::authorize('dispute', $order);

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(DisputeReason::class)],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:'.Dispute::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photos.max' => 'Add at most '.Dispute::MAX_PHOTOS.' photos.',
            'photos.*.image' => 'Each file must be a photo.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.max' => 'Each photo must be 10 MB or smaller.',
        ]);

        /** @var list<UploadedFile> $photos */
        $photos = array_values(Arr::wrap($request->file('photos')));
        $openDispute->handle($order, $request->user(), DisputeReason::from($validated['reason']), $validated['details'], $photos);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispute opened. The payment stays held until our team decides.']);

        return back();
    }

    /**
     * A dispute photo, for the buyer, the seller and dispute staff only.
     */
    public function disputePhoto(Request $request, Dispute $dispute, int $index): StreamedResponse
    {
        $dispute->loadMissing('order.seller');
        $user = $request->user();

        abort_unless(
            $dispute->order->buyer_id === $user->id || $user->managesCompany($dispute->order->seller) || $user->can('disputes.resolve'),
            403,
        );

        $path = $dispute->photos[$index] ?? abort(404);

        return Storage::disk(Dispute::PHOTO_DISK)->response($path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * Shared with the seller's order screen.
     *
     * @return array<string, mixed>
     */
    public static function detail(Order $order): array
    {
        $order->load(['items', 'seller:id,name,slug,phone,owner_id', 'buyer:id,name,email,phone', 'dispute', 'history.changer:id,name']);
        $payment = $order->securedPayment() ?? $order->payments()->latest('id')->first();

        return [
            'order' => [
                ...$order->toSummary(),
                'seller' => ['name' => $order->seller->name, 'slug' => $order->seller->slug, 'phone' => $order->seller->phone],
                'buyer' => ['name' => $order->buyer->name, 'email' => $order->buyer->email, 'phone' => $order->buyer->phone],
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => Money::format($item->unit_price, $order->currency),
                    'line_total' => Money::format($item->line_total, $order->currency),
                ])->values(),
                'can_pay_cash' => $order->canPayCash(),
                'seller_steps' => array_map(fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()], OrderStateMachine::sellerSteps($order)),
                'payment' => $payment ? [
                    'status' => $payment->status->value,
                    'provider' => PaymentGateways::label($payment->provider),
                    'amount' => Money::format($payment->amount, $payment->currency),
                    'held_at' => $payment->held_at?->toISOString(),
                    'released_at' => $payment->released_at?->toISOString(),
                    'refunded_at' => $payment->refunded_at?->toISOString(),
                ] : null,
                'dispute' => $order->dispute ? [
                    'status' => $order->dispute->status->value,
                    'reason' => $order->dispute->reason->label(),
                    'details' => $order->dispute->details,
                    'photos' => array_map(
                        fn (int $index) => route('disputes.photos.show', [$order->dispute, $index]),
                        array_keys($order->dispute->photos ?? []),
                    ),
                    'resolution_note' => $order->dispute->resolution_note,
                    'created_at' => $order->dispute->created_at?->toISOString(),
                ] : null,
                'history' => $order->history->map(fn (OrderStatusChange $change) => [
                    'to' => $change->to_status->label(),
                    'by' => $change->changer->name ?? 'System',
                    'note' => $change->note,
                    'at' => $change->created_at?->toISOString(),
                ])->values(),
            ],
            'protectionLabel' => (string) config('payments.protection_label'),
        ];
    }
}
