<?php

namespace App\Actions\Reports;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Support\CsvExport;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CSV downloads behind the reports. Amounts are written in major units
 * (e.g. 12.50 for USD), with the currency in its own column.
 */
class ReportExports
{
    public function __construct(private SalesReport $salesReport, private CurrencyConverter $converter) {}

    /**
     * @param  Builder<Order>  $orders
     * @param  string|null  $in  Add each total in this currency and the rate used.
     */
    public function orders(Builder $orders, ReportPeriod $period, string $name, bool $withSeller, ?string $in = null): StreamedResponse
    {
        $headers = [
            'Order', 'Placed', 'Status', 'Type', 'Fulfilment', 'Buyer', 'Buyer email', ...($withSeller ? ['Seller'] : []),
            'Subtotal', 'Delivery', 'Total', 'Currency', 'Payment',
            // The converted total and the exact rate used, for accountability.
            ...($in !== null ? ["Total ({$in})", 'Rate used'] : []),
        ];

        $rows = (function () use ($orders, $period, $withSeller, $in) {
            foreach ($this->salesReport->inPeriod($orders, $period)->with(['buyer:id,name,email', 'seller:id,name', 'payments' => fn ($query) => $query->whereIn('status', PaymentStatus::secured())->latest('id')])->lazyById(500) as $order) {
                yield [
                    $order->number,
                    $order->placed_at?->toDateTimeString(),
                    $order->status->label(),
                    $order->type->value,
                    $order->fulfilment->value,
                    $order->buyer->name,
                    $order->buyer->email,
                    ...($withSeller ? [$order->seller->name] : []),
                    Money::toMajor($order->subtotal, $order->currency),
                    Money::toMajor($order->delivery_fee, $order->currency),
                    Money::toMajor($order->total, $order->currency),
                    $order->currency,
                    $order->payments->first()->status->value ?? 'unpaid',
                    ...($in !== null ? $this->converted($order->total, $order->currency, $in) : []),
                ];
            }
        })();

        return CsvExport::download("{$name}-orders-{$period->slug()}.csv", $headers, $rows);
    }

    /**
     * @param  Builder<Order>  $orders
     */
    public function items(Builder $orders, ReportPeriod $period, string $name): StreamedResponse
    {
        $rows = (function () use ($orders, $period) {
            $items = OrderItem::query()
                ->whereIn('order_id', $this->salesReport->inPeriod($orders, $period)->select('id'))
                ->with('order:id,number,placed_at,status,currency')
                ->lazyById(500);

            foreach ($items as $item) {
                yield [
                    $item->order->number,
                    $item->order->placed_at?->toDateTimeString(),
                    $item->order->status->label(),
                    $item->name,
                    $item->quantity,
                    Money::toMajor($item->unit_price, $item->order->currency),
                    Money::toMajor($item->line_total, $item->order->currency),
                    $item->order->currency,
                ];
            }
        })();

        return CsvExport::download("{$name}-order-lines-{$period->slug()}.csv", ['Order', 'Placed', 'Status', 'Item', 'Quantity', 'Unit price', 'Line total', 'Currency'], $rows);
    }

    /**
     * Payouts recorded in the period.
     *
     * @param  Builder<Payout>  $payouts
     */
    public function payouts(Builder $payouts, ReportPeriod $period, string $name): StreamedResponse
    {
        $rows = (function () use ($payouts, $period) {
            $records = (clone $payouts)
                ->whereBetween('created_at', [$period->from, $period->to])
                ->with(['company:id,name', 'order:id,number'])
                ->lazyById(500);

            foreach ($records as $payout) {
                yield [
                    $payout->order->number,
                    $payout->company->name,
                    $payout->created_at?->toDateTimeString(),
                    Money::toMajor($payout->gross, $payout->currency),
                    (float) $payout->commission_rate,
                    Money::toMajor($payout->commission, $payout->currency),
                    Money::toMajor($payout->net, $payout->currency),
                    $payout->currency,
                    $payout->status->value,
                    $payout->paid_at?->toDateTimeString(),
                    $payout->reference,
                ];
            }
        })();

        return CsvExport::download(
            "{$name}-payouts-{$period->slug()}.csv",
            ['Order', 'Seller', 'Recorded', 'Gross', 'Commission %', 'Commission', 'Net', 'Currency', 'Status', 'Paid', 'Reference'],
            $rows,
        );
    }

    /**
     * @param  Builder<Order>  $orders
     */
    public function sellers(Builder $orders, ReportPeriod $period, string $name): StreamedResponse
    {
        $rows = array_map(fn (array $row) => [
            $row['seller'],
            $row['type'],
            $row['orders'],
            Money::toMajor($row['gross'], $row['currency']),
            Money::toMajor($row['commission'], $row['currency']),
            Money::toMajor($row['net'], $row['currency']),
            $row['currency'],
        ], $this->salesReport->bySeller($orders, $period));

        return CsvExport::download("{$name}-sellers-{$period->slug()}.csv", ['Seller', 'Type', 'Paid orders', 'Gross', 'Commission', 'Net', 'Currency'], $rows);
    }

    /**
     * The amount in the report currency and the rate behind it, e.g.
     * ["10.00", "1 USD = 1,450 RWF"]; blank when no rate is known.
     *
     * @return array{0: string, 1: string}
     */
    private function converted(int $amount, string $from, string $to): array
    {
        if (strtoupper($from) === $to) {
            return [Money::toMajor($amount, $to), 'same currency'];
        }

        $converted = $this->converter->convert($amount, $from, $to);
        $base = strtoupper((string) config('currency.base'));
        $quote = strtoupper($from) === $base ? $to : strtoupper($from);

        return $converted === null ? ['', 'no rate'] : [Money::toMajor($converted, $to), (string) $this->converter->label($quote)];
    }
}
