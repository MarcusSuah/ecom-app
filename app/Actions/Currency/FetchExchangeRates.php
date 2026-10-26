<?php

namespace App\Actions\Currency;

use App\Models\CurrencyRate;
use App\Services\Currency\ExchangeRateProvider;
use App\Support\CurrencyConverter;
use App\Support\Money;
use RuntimeException;

class FetchExchangeRates
{
    public function __construct(
        private ExchangeRateProvider $provider,
        private CurrencyConverter $converter,
    ) {}

    /**
     * Fetch today's rates for every supported currency into the shared
     * rows. A failed fetch throws and leaves yesterday's rates in place.
     *
     * @return array<string, string> quote currency => rate stored
     *
     * @throws RuntimeException
     */
    public function handle(): array
    {
        $base = strtoupper((string) config('currency.base'));
        $quotes = array_values(array_diff(Money::currencies(), [$base]));
        $rates = $this->provider->rates($base, $quotes);

        foreach ($rates as $quote => $rate) {
            CurrencyRate::query()->updateOrCreate(
                ['tenant_id' => null, 'base' => $base, 'quote' => $quote],
                ['rate' => $rate, 'source' => $this->provider->name(), 'fetched_at' => now()],
            );
        }

        $this->converter->flush();

        return $rates;
    }
}
