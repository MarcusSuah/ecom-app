<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\ReportCurrency;
use App\Actions\Reports\ReportExports;
use App\Actions\Reports\ReportPeriod;
use App\Actions\Reports\SalesReport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payout;
use App\Support\CurrencyConverter;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The marketplace's sales, commission, refunds and payouts for a period,
 * with a per-seller breakdown and CSV downloads. Guarded by reports.view.
 */
class ReportController extends Controller
{
    /** @var list<string> */
    public const EXPORTS = ['orders', 'items', 'payouts', 'sellers'];

    public function index(Request $request, SalesReport $report, CurrencyConverter $converter): Response
    {
        $period = ReportPeriod::fromRequest($request);
        $currency = ReportCurrency::fromRequest($request);

        return Inertia::render('Admin/Reports/Index', [
            'period' => $period->toArray(),
            'reportCurrency' => $currency->toArray(),
            'rates' => $currency->rates($converter),
            'totals' => $report->totals(Order::query(), $period, $currency->code),
            'sellers' => array_slice($report->bySeller(Order::query(), $period), 0, 25),
        ]);
    }

    public function export(Request $request, string $type, ReportExports $exports, CurrentTenant $currentTenant): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);
        $name = $currentTenant->require()->slug;

        return match ($type) {
            'orders' => $exports->orders(Order::query(), $period, $name, withSeller: true, in: ReportCurrency::fromRequest($request)->code),
            'items' => $exports->items(Order::query(), $period, $name),
            'payouts' => $exports->payouts(Payout::query(), $period, $name),
            'sellers' => $exports->sellers(Order::query(), $period, $name),
            default => abort(404),
        };
    }
}
