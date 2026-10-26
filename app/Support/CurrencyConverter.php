<?php

namespace App\Support;

use App\Models\CurrencyRate;

/**
 * Converts amounts for display only: charges always happen in the order's
 * own currency. Uses the current marketplace's manual override when it has
 * one, otherwise the shared fetched rate. Scoped to the request, so the
 * rates are read once per page however many prices it shows.
 */
class CurrencyConverter
{
    /**
     * @var array<int, array<string, CurrencyRate>> tenant id (0 for none) => quote => effective rate
     */
    private array $rates = [];

    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * Units of $to per one unit of $from, or null when no rate is known.
     */
    public function rate(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $fromPerBase = $this->perBase($from);
        $toPerBase = $this->perBase($to);

        return $fromPerBase === null || $toPerBase === null ? null : $toPerBase / $fromPerBase;
    }

    /**
     * Converts stored minor units of one currency into minor units of
     * another, rounding half up to the target's smallest unit.
     */
    public function convert(int $amount, string $from, string $to): ?int
    {
        $rate = $this->rate($from, $to);

        if ($rate === null) {
            return null;
        }

        $major = $amount / Money::subunits($from) * $rate;

        return (int) round($major * Money::subunits($to), 0, PHP_ROUND_HALF_UP);
    }

    /**
     * The rate row in use for "1 base = ? quote" on the current marketplace.
     */
    public function effectiveRate(string $quote): ?CurrencyRate
    {
        return $this->ratesForCurrentTenant()[strtoupper($quote)] ?? null;
    }

    /**
     * e.g. "1 USD = 1,450 RWF", for the footer. Null when no rate is known.
     */
    /**
     * The rate in use for reports, for accountability: "1 USD = 1,450 RWF",
     * where it came from and since when.
     *
     * @return array{label: string, source: string, since: string}|null
     */
    public function describe(string $quote): ?array
    {
        $rate = $this->effectiveRate($quote);
        $label = $this->label($quote);

        if ($rate === null || $label === null) {
            return null;
        }

        return [
            'label' => $label,
            'source' => $rate->tenant_id !== null ? 'set by the marketplace' : "daily rate ({$rate->source})",
            'since' => $rate->fetched_at->toISOString(),
        ];
    }

    public function label(string $quote): ?string
    {
        $rate = $this->effectiveRate($quote);

        if ($rate === null) {
            return null;
        }

        $decimals = (float) $rate->rate >= 100 ? 0 : 2;

        return "1 {$rate->base} = ".number_format((float) $rate->rate, $decimals)." {$rate->quote}";
    }

    /**
     * Forget remembered rates after they change in this request.
     */
    public function flush(): void
    {
        $this->rates = [];
    }

    private function perBase(string $currency): ?float
    {
        if ($currency === $this->base()) {
            return 1.0;
        }

        $rate = $this->effectiveRate($currency);

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * @return array<string, CurrencyRate>
     */
    private function ratesForCurrentTenant(): array
    {
        $tenantId = $this->currentTenant->id();

        return $this->rates[$tenantId ?? 0] ??= CurrencyRate::query()
            ->where('base', $this->base())
            ->where(fn ($query) => $query->whereNull('tenant_id')->when($tenantId, fn ($query) => $query->orWhere('tenant_id', $tenantId)))
            // Shared rows first, so the marketplace's override replaces them below.
            ->orderByRaw('tenant_id is not null')
            ->get()
            ->keyBy('quote')
            ->all();
    }

    private function base(): string
    {
        return strtoupper((string) config('currency.base'));
    }
}
