<?php

namespace App\Services\Billing;

final readonly class PaymentNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $invoiceNumber,
        public string $reference,
        public bool $paid,
        public array $payload = [],
    ) {}
}
