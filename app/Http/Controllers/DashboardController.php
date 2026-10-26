<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\DashboardRange;
use App\Actions\Dashboard\SalesTrend;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductStatus;
use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\RfqRecipient;
use App\Models\ShopProduct;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everyone's home: a seller sees their sales trend, revenue, orders waiting
 * on them and stock; a buyer sees their spending, orders and requests.
 */
class DashboardController extends Controller
{
    /** Paid orders the seller still has to act on. */
    private const TO_FULFIL = [OrderStatus::PAID, OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::IN_PRODUCTION];

    public function __invoke(Request $request, CurrentTenant $currentTenant, SalesTrend $salesTrend): Response
    {
        $user = $request->user();
        $company = $user->currentCompany()?->load('latestVerification');
        $range = DashboardRange::fromRequest($request);
        $currency = $currentTenant->require()->default_currency;

        return Inertia::render('dashboard', [
            'company' => $company?->toSummary(),
            'plan' => $company?->effectivePlan()?->toSummary(),
            'range' => $range->days,
            'currency' => $currency,
            'currencySubunits' => Money::subunits($currency),
            'seller' => $company ? $this->seller($company, $range, $currency, $salesTrend) : null,
            'buyer' => $this->buyer($user, $range, $currency, $salesTrend),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seller(Company $company, DashboardRange $range, string $currency, SalesTrend $salesTrend): array
    {
        $orders = Order::query()->where('seller_company_id', $company->id);
        $trend = $salesTrend->handle($orders, $range, $currency);
        $products = $company->isShop()
            ? ShopProduct::query()->whereHas('shop', fn (Builder $query) => $query->where('company_id', $company->id))
            : Product::query()->where('company_id', $company->id);

        return [
            'trend' => $trend,
            'kpis' => [
                'revenue' => $trend['revenue'],
                'revenue_formatted' => Money::format($trend['revenue'], $currency),
                'revenue_change' => self::change($trend['revenue'], $trend['previous_revenue']),
                'orders' => $trend['orders'],
                'orders_change' => self::change($trend['orders'], $trend['previous_orders']),
                'average_order' => $trend['orders'] > 0 ? Money::format(intdiv($trend['revenue'], $trend['orders']), $currency) : null,
                'to_fulfil' => (clone $orders)->whereIn('status', self::TO_FULFIL)->count(),
                'awaiting_payment' => (clone $orders)->where('status', OrderStatus::PENDING_PAYMENT)->count(),
                'open_disputes' => Dispute::query()->where('status', DisputeStatus::OPEN)->whereHas('order', fn (Builder $query) => $query->where('seller_company_id', $company->id))->count(),
                'payouts_pending' => Money::format((int) Payout::query()->where('company_id', $company->id)->where('status', PayoutStatus::PENDING)->sum('net'), $currency),
                'payouts_paid' => Money::format((int) Payout::query()->where('company_id', $company->id)->where('status', PayoutStatus::PAID)->where('paid_at', '>=', $range->from)->sum('net'), $currency),
                'listed' => $company->isShop()
                    ? (clone $products)->where('is_active', true)->count()
                    : (clone $products)->where('status', ProductStatus::ACTIVE)->count(),
                'out_of_stock' => $company->isShop()
                    ? (clone $products)->where('is_active', true)->where('stock', '<=', 0)->count()
                    : (clone $products)->where('status', ProductStatus::ACTIVE)->whereNotNull('stock')->whereColumn('stock', '<', 'moq')->count(),
                'rfqs_open' => $company->isSupplier()
                    ? RfqRecipient::query()->where('company_id', $company->id)->whereHas('rfq', fn (Builder $query) => $query->where('status', RfqStatus::OPEN))->count()
                    : null,
                'quotes_sent' => $company->isSupplier()
                    ? Quote::query()->where('company_id', $company->id)->where('created_at', '>=', $range->from)->count()
                    : null,
                'quotes_won' => $company->isSupplier()
                    ? Quote::query()->where('company_id', $company->id)->where('status', QuoteStatus::ACCEPTED)->where('updated_at', '>=', $range->from)->count()
                    : null,
            ],
            'by_status' => self::countByStatus((clone $orders)->where('placed_at', '>=', $range->from)),
            'top_products' => $this->topProducts($company, $range),
            'recent_orders' => (clone $orders)->with('buyer:id,name')->where('status', '!=', OrderStatus::PENDING_PAYMENT)->latest('placed_at')->limit(6)->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'party' => $order->buyer->name,
                    'total' => Money::format($order->total, $order->currency),
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'placed_at' => $order->placed_at?->toISOString(),
                ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buyer(User $user, DashboardRange $range, string $currency, SalesTrend $salesTrend): array
    {
        $orders = Order::query()->where('buyer_id', $user->id);
        $trend = $salesTrend->handle($orders, $range, $currency);

        return [
            'trend' => $trend,
            'kpis' => [
                'spent' => Money::format($trend['revenue'], $currency),
                'orders' => $trend['orders'],
                'awaiting_payment' => (clone $orders)->where('status', OrderStatus::PENDING_PAYMENT)->count(),
                'on_the_way' => (clone $orders)->whereIn('status', [...self::TO_FULFIL, OrderStatus::READY_FOR_PICKUP, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::SHIPPED])->count(),
                'rfqs_open' => Rfq::query()->where('buyer_id', $user->id)->where('status', RfqStatus::OPEN)->count(),
                'quotes_waiting' => Quote::query()->where('status', QuoteStatus::SUBMITTED)->whereHas('rfq', fn (Builder $query) => $query->where('buyer_id', $user->id)->where('status', RfqStatus::OPEN))->count(),
            ],
            'recent_orders' => (clone $orders)->with('seller:id,name')->latest('placed_at')->limit(5)->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'party' => $order->seller->name,
                    'total' => Money::format($order->total, $order->currency),
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'placed_at' => $order->placed_at?->toISOString(),
                ])->values(),
        ];
    }

    /**
     * Best sellers in the period by units, across both kinds of listing.
     *
     * @return list<array{name: string, units: int, revenue: string}>
     */
    private function topProducts(Company $company, DashboardRange $range): array
    {
        return array_values(OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query
                ->where('seller_company_id', $company->id)
                ->whereIn('status', SalesTrend::earningStatuses())
                ->where('placed_at', '>=', $range->from))
            ->with('order:id,currency')
            ->get(['id', 'order_id', 'name', 'quantity', 'line_total'])
            ->groupBy('name')
            ->map(fn ($items, string $name) => [
                'name' => $name,
                'units' => (int) $items->sum('quantity'),
                'revenue' => Money::format((int) $items->sum('line_total'), $items->first()->order->currency),
            ])
            ->sortByDesc('units')
            ->take(5)
            ->all());
    }

    /**
     * @param  Builder<Order>  $orders
     * @return list<array{status: string, label: string, count: int}>
     */
    public static function countByStatus(Builder $orders): array
    {
        $counts = $orders->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return array_values(array_filter(array_map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ], OrderStatus::cases()), fn (array $row) => $row['count'] > 0));
    }

    /**
     * Percentage change from the previous period; null when there was nothing to compare with.
     */
    public static function change(int $current, int $previous): ?float
    {
        return $previous === 0 ? null : round(($current - $previous) / $previous * 100, 1);
    }
}
