<?php

namespace App\Actions\Suppliers;

use App\Models\Company;
use App\Models\Quote;
use App\Models\RfqRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SupplierResponseTime
{
    public const WINDOW_DAYS = 90;

    /**
     * Median minutes from being notified of an RFQ to sending the quote,
     * over RFQs notified in the last 90 days. Null without any quotes.
     * Cached for an hour.
     */
    public function minutes(Company $company): ?int
    {
        return Cache::remember(
            "tenant:{$company->tenant_id}:company:{$company->id}:response-minutes",
            now()->addHour(),
            fn (): ?int => $this->calculate($company),
        );
    }

    public function label(Company $company): ?string
    {
        $minutes = $this->minutes($company);

        return $minutes === null ? null : self::describe($minutes);
    }

    public static function describe(int $minutes): string
    {
        return match (true) {
            $minutes < 60 => 'Within 1 hour',
            $minutes < 60 * 24 => 'Within '.(int) ceil($minutes / 60).' hours',
            default => 'Within '.(int) ceil($minutes / (60 * 24)).' days',
        };
    }

    public function calculate(Company $company): ?int
    {
        $notifiedAt = RfqRecipient::query()
            ->where('company_id', $company->id)
            ->whereNotNull('notified_at')
            ->where('notified_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->pluck('notified_at', 'rfq_id');

        if ($notifiedAt->isEmpty()) {
            return null;
        }

        $durations = Quote::query()
            ->where('company_id', $company->id)
            ->whereIn('rfq_id', $notifiedAt->keys())
            ->get(['rfq_id', 'created_at'])
            ->map(fn (Quote $quote) => max(0, (int) CarbonImmutable::parse($notifiedAt[$quote->rfq_id])->diffInMinutes($quote->created_at)))
            ->sort()
            ->values();

        $count = $durations->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $durations[$middle]
            : (int) round(($durations[$middle - 1] + $durations[$middle]) / 2);
    }
}
