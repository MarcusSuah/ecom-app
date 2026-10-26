<?php

use App\Support\PriceTierRules;

it('accepts tiers that start at the MOQ, rise in quantity and do not rise in price', function (array $tiers) {
    expect(PriceTierRules::violations($tiers, 100))->toBe([]);
})->with([
    'one tier' => [[['min_qty' => 100, 'unit_price' => 1000]]],
    'falling prices' => [[['min_qty' => 100, 'unit_price' => 1000], ['min_qty' => 500, 'unit_price' => 900], ['min_qty' => 1000, 'unit_price' => 800]]],
    'equal prices' => [[['min_qty' => 100, 'unit_price' => 1000], ['min_qty' => 500, 'unit_price' => 1000]]],
]);

it('reports each broken rule against the row that breaks it', function (array $tiers, array $expected) {
    expect(PriceTierRules::violations($tiers, 100))->toBe($expected);
})->with([
    'no tiers' => [[], ['tiers' => 'Add at least one price tier.']],
    'first tier above the MOQ' => [
        [['min_qty' => 150, 'unit_price' => 1000]],
        ['tiers.0.min_qty' => 'The first tier must start at the minimum order quantity (100).'],
    ],
    'first tier below the MOQ' => [
        [['min_qty' => 50, 'unit_price' => 1000]],
        ['tiers.0.min_qty' => 'The first tier must start at the minimum order quantity (100).'],
    ],
    'repeated quantity' => [
        [['min_qty' => 100, 'unit_price' => 1000], ['min_qty' => 100, 'unit_price' => 900]],
        ['tiers.1.min_qty' => 'Each tier must start at a higher quantity than the one before.'],
    ],
    'falling quantity' => [
        [['min_qty' => 100, 'unit_price' => 1000], ['min_qty' => 500, 'unit_price' => 900], ['min_qty' => 300, 'unit_price' => 800]],
        ['tiers.2.min_qty' => 'Each tier must start at a higher quantity than the one before.'],
    ],
    'rising price' => [
        [['min_qty' => 100, 'unit_price' => 1000], ['min_qty' => 500, 'unit_price' => 1001]],
        ['tiers.1.unit_price' => 'The unit price cannot be higher than the tier before.'],
    ],
]);
