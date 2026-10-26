<?php

namespace App\Actions\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The period a dashboard covers (?range=7|30|90|365 days) and the one
 * before it, for "compared with the previous period".
 */
final readonly class DashboardRange
{
    public const CHOICES = [7, 30, 90, 365];

    public function __construct(
        public int $days,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public CarbonImmutable $previousFrom,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $days = in_array($request->integer('range'), self::CHOICES, true) ? $request->integer('range') : 30;
        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays($days - 1)->startOfDay();

        return new self($days, $from, $to, $from->subDays($days));
    }

    /**
     * A year is shown by month, shorter periods by day.
     */
    public function byMonth(): bool
    {
        return $this->days > 90;
    }
}
