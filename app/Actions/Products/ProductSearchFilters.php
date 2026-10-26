<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * Catalogue filters. Prices are in minor units of $currency.
 */
final readonly class ProductSearchFilters
{
    public function __construct(
        public string $query = '',
        public ?Category $category = null,
        public bool $verifiedOnly = false,
        public ?int $maxMoq = null,
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public string $currency = 'RWF',
        public string $sort = 'relevance',
    ) {}

    /**
     * Filters come from a URL, so values that do not parse are ignored
     * rather than rejected.
     */
    public static function fromRequest(Request $request, string $defaultCurrency): self
    {
        $currency = in_array($request->string('currency')->toString(), Money::currencies(), true)
            ? $request->string('currency')->toString()
            : $defaultCurrency;
        $sort = in_array($request->string('sort')->toString(), SearchProducts::SORTS, true)
            ? $request->string('sort')->toString()
            : 'relevance';

        return new self(
            query: mb_substr($request->string('q')->trim()->toString(), 0, 100),
            category: $request->filled('category')
                ? Category::query()->where('slug', $request->string('category')->toString())->first()
                : null,
            verifiedOnly: $request->boolean('verified'),
            maxMoq: self::positiveInteger($request->input('max_moq')),
            minPrice: self::price($request->input('min_price'), $currency),
            maxPrice: self::price($request->input('max_price'), $currency),
            currency: $currency,
            sort: $sort,
        );
    }

    private static function positiveInteger(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function price(mixed $value, string $currency): ?int
    {
        return is_string($value) && preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1
            ? Money::toMinor($value, $currency)
            : null;
    }
}
