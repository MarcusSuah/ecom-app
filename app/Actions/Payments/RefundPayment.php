<?php

namespace App\Actions\Payments;

use App\Actions\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundPayment
{
    public function __construct(
        private PaymentGateways $gateways,
        private OrderStateMachine $stateMachine,
    ) {}

    /**
     * Give the held money back to the buyer and mark the order refunded.
     * Used when the seller cancels a paid order and when an admin resolves
     * a dispute for the buyer.
     */
    public function handle(Order $order, ?User $actor, string $note): Order
    {
        if (! $this->stateMachine->canMove($order, OrderStatus::REFUNDED)) {
            throw ValidationException::withMessages(['order' => 'This order can no longer be refunded.']);
        }

        return DB::transaction(function () use ($order, $actor, $note): Order {
            $payment = Payment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::HELD)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages(['order' => 'There is no held payment to refund for this order.']);
            }

            $result = $this->gateways->driver($payment->provider)
                ->refund((string) $payment->provider_reference, $payment->amount, $payment->currency, $payment->reference);

            if (! $result->accepted) {
                throw ValidationException::withMessages(['order' => 'The payment provider did not accept the refund. Try again later.']);
            }

            $payment->forceFill(['status' => PaymentStatus::REFUNDED, 'refunded_at' => now()])->save();

            return $this->stateMachine->transition($order, OrderStatus::REFUNDED, $actor, $note);
        });
    }
}
