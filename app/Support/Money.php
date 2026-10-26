<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is always an integer in the currency's minor unit as stored:
 * RWF in whole francs, USD in cents. Never use floats for amounts.
 */
final class Money
{
    /**
     * Minor units per major unit for each supported currency.
     *
     * @var array<string, int>
     */
    private const SUBUNITS = [
        'RWF' => 1,
        'USD' => 100,
    ];

    public static function subunits(string $currency): int
    {
        return self::SUBUNITS[strtoupper($currency)]
            ?? throw new InvalidArgumentException("Unsupported currency [{$currency}].");
    }

    /**
     * @return list<string>
     */
    public static function currencies(): array
    {
        return array_keys(self::SUBUNITS);
    }

    public static function format(int $amount, string $currency): string
    {
        $subunits = self::subunits($currency);
        $decimals = $subunits === 1 ? 0 : (int) log10($subunits);

        return strtoupper($currency).' '.number_format($amount / $subunits, $decimals);
    }

    /**
     * Converts a major-unit amount typed by a person (e.g. "12.50") into
     * stored minor units, without going through a float.
     */
    public static function toMinor(string|int $amount, string $currency): int
    {
        $subunits = self::subunits($currency);
        $decimals = $subunits === 1 ? 0 : (int) log10($subunits);
        [$whole, $fraction] = array_pad(explode('.', trim((string) $amount), 2), 2, '');

        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);

        return ((int) $whole) * $subunits + ($decimals > 0 ? (int) $fraction : 0);
    }

    public static function toMajor(int $amount, string $currency): string
    {
        $subunits = self::subunits($currency);
        $decimals = $subunits === 1 ? 0 : (int) log10($subunits);

        return number_format($amount / $subunits, $decimals, '.', '');
    }
}
