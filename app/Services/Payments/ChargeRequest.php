<?php

namespace App\Services\Payments;

/**
 * Amount is in minor units of the currency (RWF francs, USD cents).
 * The reference is a UUID: MoMo requires one as its X-Reference-Id.
 */
final readonly class ChargeRequest
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public string $description,
        public string $callbackUrl,
        public string $returnUrl,
        public ?string $payerPhone = null,
        public ?string $payerEmail = null,
        public ?string $payerName = null,
    ) {}
}
