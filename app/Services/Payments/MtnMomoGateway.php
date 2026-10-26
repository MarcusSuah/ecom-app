<?php

namespace App\Services\Payments;

use App\Services\Billing\InvalidWebhookSignature;
use App\Support\Money;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MTN Mobile Money Open API: Collection "request to pay" and Disbursement
 * "refund". The payer approves on their phone; MoMo then calls back.
 *
 * MoMo callbacks are not signed, so the callback URL is a Laravel signed
 * URL carrying our reference, and the status is always confirmed with
 * MoMo's API before it is trusted.
 *
 * @see https://momodeveloper.mtn.com/api-documentation
 */
class MtnMomoGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config  config('payments.providers.mtn_momo')
     */
    public function __construct(private array $config, private Cache $cache) {}

    public function name(): string
    {
        return 'mtn_momo';
    }

    public function initiate(ChargeRequest $charge): PaymentInitiation
    {
        if (! $charge->payerPhone) {
            throw new RuntimeException('Mobile Money needs the payer\'s phone number.');
        }

        // Our reference doubles as MoMo's X-Reference-Id (a UUID), so the status can be fetched from the callback.
        $response = $this->client('collection')
            ->withHeaders(['X-Reference-Id' => $charge->reference, 'X-Callback-Url' => $charge->callbackUrl])
            ->post('/collection/v1_0/requesttopay', [
                'amount' => Money::toMajor($charge->amount, $charge->currency),
                'currency' => $this->currency($charge->currency),
                'externalId' => $charge->reference,
                'payer' => ['partyIdType' => 'MSISDN', 'partyId' => preg_replace('/\D+/', '', $charge->payerPhone)],
                'payerMessage' => Str::limit($charge->description, 160, ''),
                'payeeNote' => Str::limit($charge->description, 160, ''),
            ]);

        if ($response->status() !== 202) {
            return new PaymentInitiation(PaymentOutcome::FAILED, payload: ['status' => $response->status(), 'body' => $response->json()]);
        }

        return new PaymentInitiation(PaymentOutcome::PENDING, providerReference: $charge->reference);
    }

    public function verifyWebhook(Request $request): PaymentWebhookEvent
    {
        if (! $request->hasValidSignature()) {
            throw new InvalidWebhookSignature('The callback URL signature is not valid.');
        }

        $reference = (string) $request->query('reference');
        $status = $this->client('collection')->get("/collection/v1_0/requesttopay/{$reference}")->throw()->json();

        return new PaymentWebhookEvent(
            reference: $reference,
            outcome: match ($status['status'] ?? null) {
                'SUCCESSFUL' => PaymentOutcome::SUCCEEDED,
                'FAILED', 'REJECTED', 'TIMEOUT' => PaymentOutcome::FAILED,
                default => PaymentOutcome::PENDING,
            },
            providerReference: $reference,
            payload: (array) $status,
        );
    }

    public function refund(string $providerReference, int $amount, string $currency, string $reference): RefundResult
    {
        $refundId = (string) Str::uuid();

        $response = $this->client('disbursement')
            ->withHeaders(['X-Reference-Id' => $refundId])
            ->post('/disbursement/v1_0/refund', [
                'amount' => Money::toMajor($amount, $currency),
                'currency' => $this->currency($currency),
                'externalId' => $reference,
                'payerMessage' => 'Refund',
                'payeeNote' => 'Refund',
                'referenceIdToRefund' => $providerReference,
            ]);

        return new RefundResult(accepted: $response->status() === 202, providerReference: $refundId, payload: (array) $response->json());
    }

    /**
     * An authenticated client for the "collection" or "disbursement" product.
     */
    private function client(string $product): PendingRequest
    {
        return Http::baseUrl((string) $this->config['base_url'])
            ->acceptJson()
            ->timeout(15)
            ->withToken($this->accessToken($product))
            ->withHeaders([
                'X-Target-Environment' => (string) $this->config['environment'],
                'Ocp-Apim-Subscription-Key' => (string) $this->config["{$product}_subscription_key"],
            ]);
    }

    private function accessToken(string $product): string
    {
        return $this->cache->remember("payments:mtn_momo:{$product}:token", 3000, function () use ($product): string {
            return (string) Http::baseUrl((string) $this->config['base_url'])
                ->withBasicAuth((string) $this->config["{$product}_user_id"], (string) $this->config["{$product}_api_key"])
                ->withHeaders(['Ocp-Apim-Subscription-Key' => (string) $this->config["{$product}_subscription_key"]])
                ->timeout(15)
                ->post("/{$product}/token/")
                ->throw()
                ->json('access_token');
        });
    }

    private function currency(string $currency): string
    {
        return (string) ($this->config['currency_override'] ?: $currency);
    }
}
