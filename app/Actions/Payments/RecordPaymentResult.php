<?php

namespace App\Actions\Payments;

use App\Actions\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateways;
use App\Services\Payments\PaymentOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordPaymentResult
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private PaymentGateways $gateways,
    ) {}

    /**
     * Apply a provider's result to a payment. Safe to receive more than
     * once: only a payment still "initiated" is changed. A success holds the
     * money and marks the order paid. If the order was already paid (or is
     * no longer awaiting payment), the duplicate is refunded at once.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Payment $payment, PaymentOutcome $outcome, ?string $providerReference = null, array $payload = []): Payment
    {
        if ($outcome === PaymentOutcome::PENDING) {
            return $payment;
        }

        $refundDuplicate = false;

        $payment = DB::transaction(function () use ($payment, $outcome, $providerReference, $payload, &$refundDuplicate): Payment {
            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::INITIATED) {
                return $payment;
            }

            if ($outcome === PaymentOutcome::FAILED) {
                $payment->forceFill(['status' => PaymentStatus::FAILED, 'raw_payload' => $payload ?: null])->save();

                return $payment;
            }

            $payment->forceFill([
                'status' => PaymentStatus::HELD,
                'held_at' => now(),
                'provider_reference' => $providerReference ?? $payment->provider_reference,
                'raw_payload' => $payload ?: null,
            ])->save();

            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->order_id);

            if ($order->status === OrderStatus::PENDING_PAYMENT) {
                $this->stateMachine->transition($order, OrderStatus::PAID, note: "Paid by {$payment->provider}");
            } else {
                $refundDuplicate = true;
            }

            return $payment;
        });

        if ($refundDuplicate) {
            $this->refundDuplicate($payment);
        }

        return $payment;
    }

    private function refundDuplicate(Payment $payment): void
    {
        $result = $this->gateways->driver($payment->provider)
            ->refund((string) $payment->provider_reference, $payment->amount, $payment->currency, $payment->reference);

        if ($result->accepted) {
            $payment->forceFill(['status' => PaymentStatus::REFUNDED, 'refunded_at' => now()])->save();
        } else {
            Log::error('Refund of a duplicate payment failed; refund it by hand', ['payment' => $payment->id]);
        }
    }
}
