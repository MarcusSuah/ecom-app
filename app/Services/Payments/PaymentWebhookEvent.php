<?php

namespace App\Services\Payments;

final readonly class PaymentWebhookEvent
{
    /**
     * @param  string  $reference  Our payment reference
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $reference,
        public PaymentOutcome $outcome,
        public ?string $providerReference = null,
        public array $payload = [],
    ) {}
}
