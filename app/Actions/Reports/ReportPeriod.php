<?php

namespace App\Actions\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The dates a report covers: ?from=YYYY-MM-DD&to=YYYY-MM-DD, inclusive.
 * Defaults to this month so far; at most two years at once.
 */
final readonly class ReportPeriod
{
    public const MAX_DAYS = 731;

    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    public static function fromRequest(Request $request): self
    {
        $to = self::date($request->string('to')->toString())?->endOfDay() ?? CarbonImmutable::now()->endOfDay();
        $from = self::date($request->string('from')->toString())?->startOfDay() ?? $to->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS)->startOfDay();
        }

        return new self($from, $to);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }

    /**
     * For download names, e.g. "2026-10-01_2026-10-19".
     */
    public function slug(): string
    {
        return $this->from->toDateString().'_'.$this->to->toDateString();
    }

    private static function date(string $value): ?CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? CarbonImmutable::parse($value)
            : null;
    }
}
