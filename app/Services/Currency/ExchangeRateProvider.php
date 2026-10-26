<?php

namespace App\Services\Currency;

use RuntimeException;

interface ExchangeRateProvider
{
    /**
     * Stored as the rate's source, e.g. "open_er_api".
     */
    public function name(): string;

    /**
     * Units of each quote currency per one unit of the base currency, as
     * decimal strings so no precision is lost on the way to the database.
     *
     * @param  list<string>  $quotes
     * @return array<string, string> quote currency => rate
     *
     * @throws RuntimeException when the rates cannot be fetched
     */
    public function rates(string $base, array $quotes): array;
}
