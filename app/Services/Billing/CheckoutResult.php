<?php

namespace App\Services\Billing;

final readonly class CheckoutResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $reference,
        public bool $paid,
        public ?string $redirectUrl = null,
        public array $payload = [],
    ) {}
}
