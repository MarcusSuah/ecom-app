<?php

namespace App\Services\Currency;

use RuntimeException;

/**
 * Rates from configuration, for development, tests and marketplaces that
 * set their rate by hand.
 */
class FixedExchangeRateProvider implements ExchangeRateProvider
{
    /**
     * @param  array<string, string|int|float>  $rates  quote currency => units per one base
     */
    public function __construct(private array $rates) {}

    public function name(): string
    {
        return 'fixed';
    }

    public function rates(string $base, array $quotes): array
    {
        $rates = [];

        foreach ($quotes as $quote) {
            if (! isset($this->rates[$quote])) {
                throw new RuntimeException("No fixed rate is configured for {$base}/{$quote}.");
            }

            $rates[$quote] = (string) $this->rates[$quote];
        }

        return $rates;
    }
}
