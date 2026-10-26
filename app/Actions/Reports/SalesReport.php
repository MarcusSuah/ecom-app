<?php

namespace App\Actions\Reports;

use App\Actions\Dashboard\SalesTrend;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payout;
use App\Support\CurrencyConverter;
use App\Support\MarketplaceSettings;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sales figures for a period, per currency, or converted into RWF or USD at
 * the rate in use (shown with the report). Orders count when placed in the period: paid ones as sales,
 * refunded ones as refunds. Commission and net come from the payouts of
 * those sales.
 */
class SalesReport
{
    public function __construct(private CurrencyConverter $converter) {}

    /**
     * @param  Builder<Order>  $orders  Already limited to whose orders these are
     * @param  string|null  $in  Convert everything into this currency (today's rate); null keeps one row per currency.
     * @return list<array{currency: string, converted: bool, orders: int, gross: string, average: string|null, vat: string, vat_percent: float, refunds: int, refunded: string, commission: string, net: string, paid_out: string, pending: string}>
     */
    public function totals(Builder $orders, ReportPeriod $period, ?string $in = null): array
    {
        $sales = $this->inPeriod($orders, $period)->whereIn('status', SalesTrend::earningStatuses())
            ->toBase()->selectRaw('currency, count(*) as orders, sum(total) as gross')->groupBy('currency')->get()->keyBy('currency');
        $refunds = $this->inPeriod($orders, $period)->where('status', OrderStatus::REFUNDED)
            ->toBase()->selectRaw('currency, count(*) as orders, sum(total) as amount')->groupBy('currency')->get()->keyBy('currency');
        $payouts = Payout::query()
            ->whereIn('order_id', $this->inPeriod($orders, $period)->whereIn('status', SalesTrend::earningStatuses())->select('id'))
            ->toBase()
            ->selectRaw('currency, sum(commission) as commission, sum(net) as net, sum(case when status = ? then net else 0 end) as paid_out, sum(case when status = ? then net else 0 end) as pending', [PayoutStatus::PAID->value, PayoutStatus::PENDING->value])
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $currencies = $sales->keys()->merge($refunds->keys())->unique()->sort()->values();
        $vatPercent = app(MarketplaceSettings::class)->vatPercent();

        $rows = $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'orders' => (int) ($sales[$currency]->orders ?? 0),
            'gross' => (int) ($sales[$currency]->gross ?? 0),
            'refunds' => (int) ($refunds[$currency]->orders ?? 0),
            'refunded' => (int) ($refunds[$currency]->amount ?? 0),
            'commission' => (int) ($payouts[$currency]->commission ?? 0),
            'net' => (int) ($payouts[$currency]->net ?? 0),
            'paid_out' => (int) ($payouts[$currency]->paid_out ?? 0),
            'pending' => (int) ($payouts[$currency]->pending ?? 0),
            'converted' => false,
        ])->values()->all();

        if ($in !== null) {
            $rows = $this->convertInto($rows, strtoupper($in));
        }

        return array_values(array_map(fn (array $row): array => [
            'currency' => $row['currency'],
            'converted' => $row['converted'],
            'orders' => $row['orders'],
            'gross' => Money::format($row['gross'], $row['currency']),
            'average' => $row['orders'] > 0 ? Money::format(intdiv($row['gross'], $row['orders']), $row['currency']) : null,
            // VAT included in the sales at the marketplace's rate (Settings).
            'vat' => Money::format(PlatformAnalytics::vatIn($row['gross'], $vatPercent), $row['currency']),
            'vat_percent' => $vatPercent,
            'refunds' => $row['refunds'],
            'refunded' => Money::format($row['refunded'], $row['currency']),
            'commission' => Money::format($row['commission'], $row['currency']),
            'net' => Money::format($row['net'], $row['currency']),
            'paid_out' => Money::format($row['paid_out'], $row['currency']),
            'pending' => Money::format($row['pending'], $row['currency']),
        ], $rows));
    }

    /**
     * One row in the chosen currency, at today's rate. A currency without a
     * known rate keeps its own row rather than being guessed.
     *
     * @param  array<int, array{currency: string, orders: int, gross: int, refunds: int, refunded: int, commission: int, net: int, paid_out: int, pending: int, converted: bool}>  $rows
     * @return list<array{currency: string, orders: int, gross: int, refunds: int, refunded: int, commission: int, net: int, paid_out: int, pending: int, converted: bool}>
     */
    private function convertInto(array $rows, string $target): array
    {
        $amounts = ['gross', 'refunded', 'commission', 'net', 'paid_out', 'pending'];
        $total = ['currency' => $target, 'orders' => 0, 'gross' => 0, 'refunds' => 0, 'refunded' => 0, 'commission' => 0, 'net' => 0, 'paid_out' => 0, 'pending' => 0, 'converted' => false];
        $unconverted = [];

        foreach ($rows as $row) {
            if ($row['currency'] !== $target && $this->converter->rate($row['currency'], $target) === null) {
                $unconverted[] = $row;

                continue;
            }

            foreach ($amounts as $key) {
                $total[$key] += $row['currency'] === $target ? $row[$key] : (int) $this->converter->convert($row[$key], $row['currency'], $target);
            }

            $total['orders'] += $row['orders'];
            $total['refunds'] += $row['refunds'];
            $total['converted'] = $total['converted'] || $row['currency'] !== $target;
        }

        return $rows === [] ? [] : [$total, ...$unconverted];
    }

    /**
     * Sales per seller and currency, biggest first.
     *
     * @param  Builder<Order>  $orders
     * @return list<array{company_id: int, seller: string, type: string, currency: string, orders: int, gross: int, gross_formatted: string, commission: int, commission_formatted: string, net: int, net_formatted: string}>
     */
    public function bySeller(Builder $orders, ReportPeriod $period): array
    {
        $sales = $this->inPeriod($orders, $period)->whereIn('status', SalesTrend::earningStatuses())
            ->toBase()->selectRaw('seller_company_id, currency, count(*) as orders, sum(total) as gross')->groupBy('seller_company_id', 'currency')->get();
        $payouts = Payout::query()
            ->whereIn('order_id', $this->inPeriod($orders, $period)->whereIn('status', SalesTrend::earningStatuses())->select('id'))
            ->toBase()->selectRaw('company_id, currency, sum(commission) as commission, sum(net) as net')->groupBy('company_id', 'currency')->get()
            ->keyBy(fn (object $row) => $row->company_id.'|'.$row->currency);
        $companies = Company::query()->withoutGlobalScopes()->whereIn('id', $sales->pluck('seller_company_id')->unique())->get(['id', 'name', 'type'])->keyBy('id');

        return array_values($sales->map(function (object $row) use ($payouts, $companies): array {
            $payout = $payouts[$row->seller_company_id.'|'.$row->currency] ?? null;
            $company = $companies[$row->seller_company_id] ?? null;

            return [
                'company_id' => (int) $row->seller_company_id,
                'seller' => $company->name ?? "Company #{$row->seller_company_id}",
                'type' => $company?->type->label() ?? '',
                'currency' => (string) $row->currency,
                'orders' => (int) $row->orders,
                'gross' => (int) $row->gross,
                'gross_formatted' => Money::format((int) $row->gross, $row->currency),
                'commission' => (int) ($payout->commission ?? 0),
                'commission_formatted' => Money::format((int) ($payout->commission ?? 0), $row->currency),
                'net' => (int) ($payout->net ?? 0),
                'net_formatted' => Money::format((int) ($payout->net ?? 0), $row->currency),
            ];
        })->sortByDesc('gross')->all());
    }

    /**
     * @param  Builder<Order>  $orders
     * @return Builder<Order>
     */
    public function inPeriod(Builder $orders, ReportPeriod $period): Builder
    {
        return (clone $orders)->whereBetween('placed_at', [$period->from, $period->to]);
    }
}
