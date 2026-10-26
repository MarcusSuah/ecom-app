<?php

namespace App\Support;

/**
 * The rules every tiered price list follows (product price tiers now, quote
 * tiers in Phase 3):
 *
 *  - at least one tier,
 *  - the first tier starts at the minimum order quantity,
 *  - each tier starts at a strictly higher quantity than the one before,
 *  - unit prices never go up as the quantity rises.
 *
 * Tiers are checked in the order given, so each message points at the row
 * the person typed.
 */
final class PriceTierRules
{
    /**
     * @param  list<array{min_qty: int, unit_price: int}>  $tiers  Prices in minor units
     * @return array<string, string> Messages keyed like "tiers.1.min_qty"
     */
    public static function violations(array $tiers, int $moq, string $key = 'tiers'): array
    {
        if ($tiers === []) {
            return [$key => 'Add at least one price tier.'];
        }

        $errors = [];

        if ($tiers[0]['min_qty'] !== $moq) {
            $errors["{$key}.0.min_qty"] = 'The first tier must start at the minimum order quantity ('.number_format($moq).').';
        }

        for ($index = 1; $index < count($tiers); $index++) {
            $previous = $tiers[$index - 1];
            $tier = $tiers[$index];

            if ($tier['min_qty'] <= $previous['min_qty']) {
                $errors["{$key}.{$index}.min_qty"] = 'Each tier must start at a higher quantity than the one before.';
            }

            if ($tier['unit_price'] > $previous['unit_price']) {
                $errors["{$key}.{$index}.unit_price"] = 'The unit price cannot be higher than the tier before.';
            }
        }

        return $errors;
    }
}
