<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Dashboard\DashboardRange;
use App\Actions\Dashboard\SalesTrend;
use App\Actions\Reports\PlatformAnalytics;
use App\Actions\Reports\ReportCurrency;
use App\Actions\Reports\ReportPeriod;
use App\Enums\CompanyType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CsvExport;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every marketplace side by side, for platform admins (platform.admin), and
 * every business (supplier) and store (local shop) on them: revenue,
 * expenses, what is left and the VAT in sales, for tax and business reporting.
 */
class AnalyticsController extends Controller
{
    /** @var list<string> */
    public const LEVELS = ['marketplaces', 'businesses', 'stores'];

    public function index(Request $request, PlatformAnalytics $analytics, SalesTrend $salesTrend, CurrencyConverter $converter): Response
    {
        $period = ReportPeriod::fromRequest($request);
        $range = DashboardRange::fromRequest($request);
        $level = $this->level($request);
        $currency = ReportCurrency::fromRequest($request)->code ?? PlatformAnalytics::CURRENCY;

        return Inertia::render('Platform/Analytics', [
            'period' => $period->toArray(),
            'range' => $range->days,
            'level' => $level,
            'currency' => $currency,
            'currencySubunits' => Money::subunits($currency),
            'reportCurrency' => (new ReportCurrency($currency))->toArray(),
            'rates' => (new ReportCurrency($currency))->rates($converter),
            ...$analytics->handle($period, $currency),
            'sellers' => $level === 'marketplaces' ? [] : $analytics->sellers($period, $this->type($level), in: $currency),
            'trend' => $salesTrend->handle(Order::query()->withoutGlobalScopes(), $range, $currency),
        ]);
    }

    public function export(Request $request, PlatformAnalytics $analytics): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);
        $level = $this->level($request);
        $currency = ReportCurrency::fromRequest($request)->code ?? PlatformAnalytics::CURRENCY;

        if ($level !== 'marketplaces') {
            $rows = array_map(fn (array $row) => [
                $row['name'], $row['type_label'], $row['marketplace'], $row['city'], $row['orders'],
                $row['revenue'], $row['commission'], $row['plan_fees'], $row['expenses'], $row['net'], $row['vat_percent'], $row['vat'],
            ], $analytics->sellers($period, $this->type($level), limit: PHP_INT_MAX, in: $currency));

            return CsvExport::download(
                "platform-{$level}-{$period->slug()}.csv",
                ['Seller', 'Kind', 'Marketplace', 'City', 'Paid orders', "Revenue ({$currency})", "Commission paid ({$currency})", "Plan fees paid ({$currency})", "Expenses ({$currency})", "Net ({$currency})", 'VAT rate (%)', "VAT in sales ({$currency})"],
                $rows,
            );
        }

        $rows = array_map(fn (array $row) => [
            $row['name'], $row['status'], $row['plan'], $row['mrr'], $row['sellers'], $row['members'], $row['orders'],
            Money::toMajor($row['gmv_minor'], $currency), $row['commission'], $row['seller_plans'],
            $row['revenue'], $row['expenses'], $row['net'], $row['refunds'], $row['vat_percent'], $row['vat'],
        ], $analytics->handle($period, $currency)['marketplaces']);

        return CsvExport::download(
            "platform-marketplaces-{$period->slug()}.csv",
            ['Marketplace', 'Status', 'Plan', 'Monthly plan price', 'Sellers', 'Members', 'Paid orders', "Sales ({$currency})", "Commission ({$currency})", "Seller plan revenue ({$currency})", "Revenue ({$currency})", "Platform fees paid ({$currency})", "Net ({$currency})", "Refunds ({$currency})", 'VAT rate (%)', "VAT in sales ({$currency})"],
            $rows,
        );
    }

    private function level(Request $request): string
    {
        $level = $request->string('level')->toString();

        return in_array($level, self::LEVELS, true) ? $level : 'marketplaces';
    }

    private function type(string $level): ?CompanyType
    {
        return match ($level) {
            'businesses' => CompanyType::SUPPLIER,
            'stores' => CompanyType::SHOP,
            default => null,
        };
    }
}
