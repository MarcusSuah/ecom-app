<?php

namespace App\Services\Currency;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ExchangeRate-API's open endpoint: free, no key, updated daily.
 *
 * @see https://www.exchangerate-api.com/docs/free
 */
class OpenExchangeRateApiProvider implements ExchangeRateProvider
{
    public function __construct(private string $baseUrl) {}

    public function name(): string
    {
        return 'open_er_api';
    }

    public function rates(string $base, array $quotes): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->retry(2, 1000)
                ->get(rtrim($this->baseUrl, '/').'/latest/'.$base)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new RuntimeException('The exchange rate service could not be reached.', previous: $exception);
        }

        if ($response->json('result') !== 'success') {
            throw new RuntimeException('The exchange rate service returned an error: '.$response->json('error-type', 'unknown').'.');
        }

        $rates = [];

        foreach ($quotes as $quote) {
            $rate = $response->json("rates.{$quote}");

            if (! is_numeric($rate) || $rate <= 0) {
                throw new RuntimeException("The exchange rate service has no rate for {$base}/{$quote}.");
            }

            $rates[$quote] = (string) $rate;
        }

        return $rates;
    }
}
