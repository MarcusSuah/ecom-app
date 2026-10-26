<?php

namespace App\Services\Payments;

final readonly class PaymentInitiation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public PaymentOutcome $outcome,
        public ?string $providerReference = null,
        public ?string $redirectUrl = null,
        public array $payload = [],
    ) {}
}
