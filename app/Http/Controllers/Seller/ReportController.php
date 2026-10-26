<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Reports\ReportCurrency;
use App\Actions\Reports\ReportExports;
use App\Actions\Reports\ReportPeriod;
use App\Actions\Reports\SalesReport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payout;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A seller's own sales, refunds, commission and payouts for a period, with
 * CSV downloads and a PDF payout statement.
 */
class ReportController extends Controller
{
    /** @var list<string> */
    public const EXPORTS = ['orders', 'items', 'payouts'];

    public function index(Request $request, SalesReport $report, CurrencyConverter $converter): Response
    {
        $company = $request->user()->currentCompany() ?? abort(403);
        $period = ReportPeriod::fromRequest($request);
        $currency = ReportCurrency::fromRequest($request);
        $orders = Order::query()->where('seller_company_id', $company->id);

        return Inertia::render('seller/reports', [
            'period' => $period->toArray(),
            'reportCurrency' => $currency->toArray(),
            'rates' => $currency->rates($converter),
            'totals' => $report->totals($orders, $period, $currency->code),
            'recentOrders' => $report->inPeriod($orders, $period)->with('buyer:id,name')->latest('placed_at')->limit(10)->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'buyer' => $order->buyer->name,
                    'total' => Money::format($order->total, $order->currency),
                    'status' => $order->status->value,
                    'placed_at' => $order->placed_at?->toISOString(),
                ])->values(),
        ]);
    }

    public function export(Request $request, string $type, ReportExports $exports): StreamedResponse
    {
        $company = $request->user()->currentCompany() ?? abort(403);
        $period = ReportPeriod::fromRequest($request);
        $orders = Order::query()->where('seller_company_id', $company->id);
        $name = Str::slug($company->name);

        return match ($type) {
            'orders' => $exports->orders($orders, $period, $name, withSeller: false, in: ReportCurrency::fromRequest($request)->code),
            'items' => $exports->items($orders, $period, $name),
            'payouts' => $exports->payouts(Payout::query()->where('company_id', $company->id), $period, $name),
            default => abort(404),
        };
    }
}
