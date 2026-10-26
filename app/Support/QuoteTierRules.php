<?php

namespace App\Support;

/**
 * Quote tiers follow the product tier rules, with the first tier acting as
 * the quote's minimum order, plus one more: the first tier must start at or
 * below the requested quantity, so the quote prices what was asked for.
 */
final class QuoteTierRules
{
    /**
     * @param  list<array{min_qty: int, unit_price: int}>  $tiers  Prices in minor units
     * @return array<string, string>
     */
    public static function violations(array $tiers, int $requestedQuantity): array
    {
        if ($tiers === []) {
            return PriceTierRules::violations($tiers, 0);
        }

        $errors = PriceTierRules::violations($tiers, $tiers[0]['min_qty']);

        if ($tiers[0]['min_qty'] > $requestedQuantity) {
            $errors['tiers.0.min_qty'] = 'The first tier must start at or below the requested quantity ('.number_format($requestedQuantity).').';
        }

        return $errors;
    }
}
