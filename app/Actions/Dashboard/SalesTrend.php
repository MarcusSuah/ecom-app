<?php

namespace App\Actions\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revenue and order counts over a period, for dashboard charts. Grouped in
 * PHP so it runs the same on MySQL and SQLite; amounts in other currencies
 * are converted to the marketplace's currency at today's rate (approximate).
 */
class SalesTrend
{
    /**
     * Orders that brought money in: paid and not cancelled or refunded.
     *
     * @return list<OrderStatus>
     */
    public static function earningStatuses(): array
    {
        return array_values(array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status) => ! in_array($status, [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED, OrderStatus::REFUNDED], true),
        ));
    }

    public function __construct(private CurrencyConverter $converter) {}

    /**
     * @param  Builder<Order>  $orders  Already limited to whose orders these are
     * @return array{points: list<array{date: string, label: string, revenue: int, orders: int}>, revenue: int, previous_revenue: int, orders: int, previous_orders: int, converted: bool}
     */
    public function handle(Builder $orders, DashboardRange $range, string $currency): array
    {
        $rows = (clone $orders)
            ->whereIn('status', self::earningStatuses())
            ->whereBetween('placed_at', [$range->previousFrom, $range->to])
            ->get(['id', 'total', 'currency', 'placed_at']);

        $converted = false;
        $buckets = $this->emptyBuckets($range);
        $current = ['revenue' => 0, 'orders' => 0];
        $previous = ['revenue' => 0, 'orders' => 0];

        foreach ($rows as $order) {
            $amount = $order->currency === $currency ? $order->total : $this->converter->convert($order->total, $order->currency, $currency);

            if ($amount === null) {
                continue;
            }

            $converted = $converted || $order->currency !== $currency;
            $placedAt = CarbonImmutable::parse($order->placed_at);

            if ($placedAt->lt($range->from)) {
                $previous['revenue'] += $amount;
                $previous['orders']++;

                continue;
            }

            $current['revenue'] += $amount;
            $current['orders']++;
            $key = $range->byMonth() ? $placedAt->format('Y-m') : $placedAt->toDateString();

            if (isset($buckets[$key])) {
                $buckets[$key]['revenue'] += $amount;
                $buckets[$key]['orders']++;
            }
        }

        return [
            'points' => array_values($buckets),
            'revenue' => $current['revenue'],
            'previous_revenue' => $previous['revenue'],
            'orders' => $current['orders'],
            'previous_orders' => $previous['orders'],
            'converted' => $converted,
        ];
    }

    /**
     * @return array<string, array{date: string, label: string, revenue: int, orders: int}>
     */
    private function emptyBuckets(DashboardRange $range): array
    {
        $buckets = [];
        $cursor = $range->byMonth() ? $range->from->startOfMonth() : $range->from;

        while ($cursor->lte($range->to)) {
            $key = $range->byMonth() ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$key] = [
                'date' => $key,
                'label' => $range->byMonth() ? $cursor->format('M Y') : $cursor->format('j M'),
                'revenue' => 0,
                'orders' => 0,
            ];
            $cursor = $range->byMonth() ? $cursor->addMonth() : $cursor->addDay();
        }

        return $buckets;
    }
}
