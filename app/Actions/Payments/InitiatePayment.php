<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\ChargeRequest;
use App\Services\Payments\PaymentGateways;
use App\Services\Payments\PaymentInitiation;
use App\Services\Payments\PaymentOutcome;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InitiatePayment
{
    public function __construct(
        private PaymentGateways $gateways,
        private RecordPaymentResult $recordPaymentResult,
    ) {}

    /**
     * Start paying for an order with the chosen provider. Completes at once
     * with the fake provider; otherwise the result arrives by webhook.
     *
     * @return array{payment: Payment, initiation: PaymentInitiation}
     */
    public function handle(Order $order, string $provider, ?string $payerPhone = null): array
    {
        if ($order->status !== OrderStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages(['provider' => 'This order is not waiting for payment.']);
        }

        $gateway = $this->gateways->driver($provider);

        $payment = new Payment;
        $payment->forceFill([
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'reference' => (string) Str::uuid(),
            'provider' => $gateway->name(),
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => PaymentStatus::INITIATED,
            'payer_phone' => $payerPhone,
        ])->save();

        try {
            $initiation = $gateway->initiate(new ChargeRequest(
                reference: $payment->reference,
                amount: $payment->amount,
                currency: $payment->currency,
                description: "Order {$order->number}",
                // MoMo callbacks are unsigned, so the URL itself is signed.
                callbackUrl: URL::signedRoute('webhooks.payments', ['provider' => $gateway->name(), 'reference' => $payment->reference]),
                returnUrl: route('orders.show', $order),
                payerPhone: $payerPhone,
                payerEmail: $order->buyer->email,
                payerName: $order->buyer->name,
            ));
        } catch (RuntimeException $exception) {
            $payment->forceFill(['status' => PaymentStatus::FAILED, 'raw_payload' => ['error' => $exception->getMessage()]])->save();

            throw ValidationException::withMessages(['provider' => 'The payment could not be started. Please try again or choose another method.']);
        }

        $payment->forceFill(['provider_reference' => $initiation->providerReference])->save();

        if ($initiation->outcome !== PaymentOutcome::PENDING) {
            $this->recordPaymentResult->handle($payment, $initiation->outcome, $initiation->providerReference, $initiation->payload);
        }

        return ['payment' => $payment->refresh(), 'initiation' => $initiation];
    }
}
