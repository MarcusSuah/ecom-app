<?php

namespace App\Services\Payments;

use App\Services\Billing\InvalidWebhookSignature;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Flutterwave Standard: the payer is sent to a hosted page. Webhooks carry
 * the dashboard's secret hash in "verif-hash"; the transaction is then
 * re-verified with the API before it is trusted.
 *
 * @see https://developer.flutterwave.com/docs/collecting-payments/standard
 */
class FlutterwaveGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config  config('payments.providers.flutterwave')
     */
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'flutterwave';
    }

    public function initiate(ChargeRequest $charge): PaymentInitiation
    {
        $response = $this->client()->post('/v3/payments', [
            'tx_ref' => $charge->reference,
            'amount' => Money::toMajor($charge->amount, $charge->currency),
            'currency' => $charge->currency,
            'redirect_url' => $charge->returnUrl,
            'customer' => array_filter([
                'email' => $charge->payerEmail,
                'phonenumber' => $charge->payerPhone,
                'name' => $charge->payerName,
            ]),
            'customizations' => ['title' => $charge->description],
        ]);

        $link = $response->json('data.link');

        if (! $response->successful() || ! is_string($link)) {
            return new PaymentInitiation(PaymentOutcome::FAILED, payload: (array) $response->json());
        }

        return new PaymentInitiation(PaymentOutcome::PENDING, redirectUrl: $link);
    }

    public function verifyWebhook(Request $request): PaymentWebhookEvent
    {
        $expected = (string) ($this->config['webhook_hash'] ?? '');

        if ($expected === '' || ! hash_equals($expected, (string) $request->header('verif-hash'))) {
            throw new InvalidWebhookSignature('The webhook hash is not valid.');
        }

        $transactionId = $request->input('data.id');
        $reference = (string) $request->input('data.tx_ref');
        $verified = $this->client()->get("/v3/transactions/{$transactionId}/verify")->throw()->json('data');

        // Trust only what the API says, and only for the reference the webhook claimed.
        $outcome = match (true) {
            ($verified['tx_ref'] ?? null) !== $reference => PaymentOutcome::FAILED,
            ($verified['status'] ?? null) === 'successful' => PaymentOutcome::SUCCEEDED,
            ($verified['status'] ?? null) === 'failed' => PaymentOutcome::FAILED,
            default => PaymentOutcome::PENDING,
        };

        return new PaymentWebhookEvent(
            reference: $reference,
            outcome: $outcome,
            providerReference: (string) $transactionId,
            payload: (array) $verified,
        );
    }

    public function refund(string $providerReference, int $amount, string $currency, string $reference): RefundResult
    {
        $response = $this->client()->post("/v3/transactions/{$providerReference}/refund", [
            'amount' => Money::toMajor($amount, $currency),
        ]);

        return new RefundResult(
            accepted: $response->successful() && $response->json('status') === 'success',
            providerReference: (string) $response->json('data.id'),
            payload: (array) $response->json(),
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) $this->config['base_url'])
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->withToken((string) $this->config['secret_key']);
    }
}
