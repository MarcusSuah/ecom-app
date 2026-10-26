<?php

namespace App\Services\Payments;

final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $accepted,
        public ?string $providerReference = null,
        public array $payload = [],
    ) {}
}
