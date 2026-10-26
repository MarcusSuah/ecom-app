<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPaymentResult;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Billing\InvalidWebhookSignature;
use App\Services\Payments\PaymentGateways;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Payment results pushed by providers. Signature-checked by the driver and
 * safe to receive more than once (RecordPaymentResult only changes a
 * payment that is still "initiated").
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentGateways $gateways,
        RecordPaymentResult $recordPaymentResult,
        CurrentTenant $currentTenant,
    ): JsonResponse {
        try {
            $event = $gateways->driver($provider)->verifyWebhook($request);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Unknown provider.'], 404);
        } catch (InvalidWebhookSignature) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // Providers call one platform URL, so the payment may belong to any marketplace.
        $payment = Payment::withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('reference', $event->reference)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Unknown payment.'], 404);
        }

        // Eager loading would apply the current tenant's scope to the order.
        $order = Order::withoutGlobalScopes()->with('tenant')->findOrFail($payment->order_id);
        $payment->setRelation('order', $order);

        $currentTenant->runAs($order->tenant, fn () => $recordPaymentResult->handle(
            $payment,
            $event->outcome,
            $event->providerReference,
            $event->payload,
        ));

        return response()->json(['message' => 'OK']);
    }
}
