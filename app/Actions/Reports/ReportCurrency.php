<?php

namespace App\Actions\Reports;

use App\Support\CurrencyConverter;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * The currency a report is shown in (?currency=RWF|USD), or each order's
 * own currency when none is chosen, and the rates used to convert, so
 * readers can check the figures.
 */
final readonly class ReportCurrency
{
    public function __construct(public ?string $code) {}

    public static function fromRequest(Request $request): self
    {
        $code = strtoupper($request->string('currency')->toString());

        return new self(in_array($code, Money::currencies(), true) ? $code : null);
    }

    /**
     * The rate behind each conversion: "1 USD = 1,450 RWF", where it came
     * from and since when. Empty when nothing is converted.
     *
     * @return list<array{label: string, source: string, since: string}>
     */
    public function rates(CurrencyConverter $converter): array
    {
        if ($this->code === null) {
            return [];
        }

        $base = strtoupper((string) config('currency.base'));

        return array_values(array_filter(array_map(
            fn (string $quote) => $converter->describe($quote),
            array_diff(Money::currencies(), [$base]),
        )));
    }

    /**
     * @return array{code: string|null, options: list<string>}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'options' => Money::currencies()];
    }
}
