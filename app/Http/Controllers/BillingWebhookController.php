<?php

namespace App\Http\Controllers;

use App\Actions\Billing\RecordInvoicePayment;
use App\Enums\InvoiceStatus;
use App\Models\SubscriptionInvoice;
use App\Services\Billing\InvalidWebhookSignature;
use App\Services\Billing\SubscriptionGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment results pushed by the gateway. Signature-checked, and safe to
 * receive more than once: a paid invoice is never changed again.
 */
class BillingWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionGateway $gateway, RecordInvoicePayment $recordInvoicePayment): JsonResponse
    {
        try {
            $notification = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignature) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // Gateways call one platform URL, so the invoice may belong to any marketplace.
        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('number', $notification->invoiceNumber)->first();

        if (! $invoice) {
            return response()->json(['message' => 'Unknown invoice.'], 404);
        }

        if ($notification->paid) {
            $recordInvoicePayment->handle($invoice, $gateway->name(), $notification->reference, $notification->payload);
        } elseif ($invoice->status === InvoiceStatus::PENDING) {
            $invoice->update(['status' => InvoiceStatus::FAILED, 'raw_payload' => $notification->payload]);
        }

        return response()->json(['message' => 'OK']);
    }
}
