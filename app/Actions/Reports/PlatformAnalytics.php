<?php

namespace App\Actions\Reports;

use App\Actions\Dashboard\SalesTrend;
use App\Enums\BillingInterval;
use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\CurrencyConverter;
use App\Support\MarketplaceSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Every marketplace side by side for the platform admin: its plan and what
 * it pays each month, its sellers and members, and its sales, commission and
 * seller-plan revenue in the period. Platform totals are in the platform
 * currency (USD, or RWF when asked), converted at the rate in use.
 *
 * Revenue, expenses and tax follow one reading throughout:
 * - a marketplace earns commission and seller-plan fees, and pays the
 *   platform its plan invoices;
 * - a seller (business or store) earns its sales, and pays commission and
 *   its seller-plan invoices;
 * - VAT is the part of sales included at the marketplace's rate
 *   (MarketplaceSettings vat_percent), shown for tax reporting.
 */
class PlatformAnalytics
{
    public const CURRENCY = 'USD';

    /** The currency amounts are added up in for this call (USD unless RWF is asked for). */
    private string $currency = self::CURRENCY;

    public function __construct(private CurrencyConverter $converter) {}

    /**
     * @return array{marketplaces: list<array<string, mixed>>, totals: array<string, string|int>}
     */
    public function handle(ReportPeriod $period, ?string $in = null): array
    {
        $this->currency = $in ?? self::CURRENCY;

        $tenants = Tenant::query()->orderBy('name')->get();
        $platform = ['mrr' => 0, 'gmv' => 0, 'commission' => 0, 'seller_plans' => 0, 'platform_fees' => 0, 'refunds' => 0, 'vat' => 0, 'orders' => 0, 'sellers' => 0, 'members' => 0];
        $vatRates = $this->vatRates();
        $refunds = Order::query()->withoutGlobalScopes()
            ->where('status', OrderStatus::REFUNDED)
            ->whereBetween('placed_at', [$period->from, $period->to])
            ->toBase()->selectRaw('tenant_id, currency, sum(total) as amount')->groupBy('tenant_id', 'currency')->get()->groupBy('tenant_id');
        $platformFees = SubscriptionInvoice::query()->withoutGlobalScopes()
            ->where('status', InvoiceStatus::PAID)
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->whereHas('subscription', fn ($query) => $query->withoutGlobalScopes()->where('subscriber_type', (new Tenant)->getMorphClass()))
            ->toBase()->selectRaw('tenant_id, currency, sum(amount) as amount')->groupBy('tenant_id', 'currency')->get()->groupBy('tenant_id');

        $sales = Order::query()->withoutGlobalScopes()
            ->whereIn('status', SalesTrend::earningStatuses())
            ->whereBetween('placed_at', [$period->from, $period->to])
            ->toBase()->selectRaw('tenant_id, currency, count(*) as orders, sum(total) as gross')->groupBy('tenant_id', 'currency')->get()->groupBy('tenant_id');
        $commission = Payout::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->toBase()->selectRaw('tenant_id, currency, sum(commission) as commission')->groupBy('tenant_id', 'currency')->get()->groupBy('tenant_id');
        $sellerPlans = SubscriptionInvoice::query()->withoutGlobalScopes()
            ->where('status', InvoiceStatus::PAID)
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->whereHas('subscription', fn ($query) => $query->withoutGlobalScopes()->where('subscriber_type', (new Company)->getMorphClass()))
            ->toBase()->selectRaw('tenant_id, currency, sum(amount) as amount')->groupBy('tenant_id', 'currency')->get()->groupBy('tenant_id');
        $sellers = Company::query()->withoutGlobalScopes()->toBase()->selectRaw('tenant_id, count(*) as aggregate')->groupBy('tenant_id')->pluck('aggregate', 'tenant_id');
        $members = DB::table('model_has_roles')->selectRaw('tenant_id, count(distinct model_id) as aggregate')->groupBy('tenant_id')->pluck('aggregate', 'tenant_id');

        $rows = $tenants->map(function (Tenant $tenant) use ($sales, $commission, $sellerPlans, $sellers, $members, $refunds, $platformFees, $vatRates, &$platform): array {
            $subscription = Subscription::query()->withoutGlobalScopes()
                ->where('subscriber_type', $tenant->getMorphClass())
                ->where('subscriber_id', $tenant->id)
                ->whereIn('status', SubscriptionStatus::live())
                ->with('plan:id,name')
                ->latest('id')
                ->first();
            $mrr = $subscription && $subscription->status !== SubscriptionStatus::TRIALING
                ? ($subscription->interval === BillingInterval::YEAR ? intdiv($subscription->price, 12) : $subscription->price)
                : 0;

            $gmv = $this->sum($sales[$tenant->id] ?? collect(), 'gross');
            $earned = $this->sum($commission[$tenant->id] ?? collect(), 'commission');
            $plans = $this->sum($sellerPlans[$tenant->id] ?? collect(), 'amount');
            $fees = $this->sum($platformFees[$tenant->id] ?? collect(), 'amount');
            $refunded = $this->sum($refunds[$tenant->id] ?? collect(), 'amount');
            $vatPercent = $vatRates[$tenant->id] ?? (float) MarketplaceSettings::DEFAULTS['vat_percent'];
            $vat = self::vatIn($gmv, $vatPercent);
            $orders = (int) ($sales[$tenant->id] ?? collect())->sum('orders');
            $mrrConverted = $subscription ? (int) $this->converter->convert($mrr, $subscription->currency, $this->currency) : 0;

            $platform['mrr'] += $mrrConverted;
            $platform['gmv'] += $gmv;
            $platform['commission'] += $earned;
            $platform['seller_plans'] += $plans;
            $platform['platform_fees'] += $fees;
            $platform['refunds'] += $refunded;
            $platform['vat'] += $vat;
            $platform['orders'] += $orders;
            $platform['sellers'] += (int) ($sellers[$tenant->id] ?? 0);
            $platform['members'] += (int) ($members[$tenant->id] ?? 0);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
                'plan' => $subscription?->plan->name,
                'subscription_status' => $subscription?->status->value,
                'mrr' => $subscription ? Money::format($mrr, $subscription->currency) : null,
                'mrr_minor' => $mrrConverted,
                'sellers' => (int) ($sellers[$tenant->id] ?? 0),
                'members' => (int) ($members[$tenant->id] ?? 0),
                'orders' => $orders,
                'gmv' => Money::format($gmv, $this->currency),
                'gmv_minor' => $gmv,
                'commission' => Money::format($earned, $this->currency),
                'seller_plans' => Money::format($plans, $this->currency),
                'revenue' => Money::format($earned + $plans, $this->currency),
                'expenses' => Money::format($fees, $this->currency),
                'net' => Money::format($earned + $plans - $fees, $this->currency),
                'refunds' => Money::format($refunded, $this->currency),
                'vat_percent' => $vatPercent,
                'vat' => Money::format($vat, $this->currency),
            ];
        });

        return [
            'marketplaces' => array_values($rows->all()),
            'totals' => [
                'mrr' => Money::format($platform['mrr'], $this->currency),
                'gmv' => Money::format($platform['gmv'], $this->currency),
                'commission' => Money::format($platform['commission'], $this->currency),
                'seller_plans' => Money::format($platform['seller_plans'], $this->currency),
                'platform_fees' => Money::format($platform['platform_fees'], $this->currency),
                'refunds' => Money::format($platform['refunds'], $this->currency),
                'vat' => Money::format($platform['vat'], $this->currency),
                'orders' => $platform['orders'],
                'sellers' => $platform['sellers'],
                'members' => $platform['members'],
                'marketplaces' => $tenants->count(),
            ],
        ];
    }

    /**
     * Every seller on every marketplace (businesses are suppliers, stores
     * are local shops), biggest sales first: what it sold, what it paid in
     * commission and seller-plan fees, what it kept and the VAT in its sales.
     *
     * @return list<array<string, mixed>>
     */
    public function sellers(ReportPeriod $period, ?CompanyType $type = null, int $limit = 100, ?string $in = null): array
    {
        $this->currency = $in ?? self::CURRENCY;

        $vatRates = $this->vatRates();
        $sales = Order::query()->withoutGlobalScopes()
            ->whereIn('status', SalesTrend::earningStatuses())
            ->whereBetween('placed_at', [$period->from, $period->to])
            ->toBase()->selectRaw('seller_company_id, currency, count(*) as orders, sum(total) as gross')->groupBy('seller_company_id', 'currency')->get()->groupBy('seller_company_id');
        $commission = Payout::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->toBase()->selectRaw('company_id, currency, sum(commission) as commission')->groupBy('company_id', 'currency')->get()->groupBy('company_id');
        $planFees = DB::table('subscription_invoices')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_invoices.subscription_id')
            ->where('subscriptions.subscriber_type', (new Company)->getMorphClass())
            ->where('subscription_invoices.status', InvoiceStatus::PAID->value)
            ->whereBetween('subscription_invoices.paid_at', [$period->from, $period->to])
            ->selectRaw('subscriptions.subscriber_id as company_id, subscription_invoices.currency as currency, sum(subscription_invoices.amount) as amount')
            ->groupBy('subscriptions.subscriber_id', 'subscription_invoices.currency')
            ->get()->groupBy('company_id');

        $companyIds = $sales->keys()->merge($planFees->keys())->unique()->all();
        $companies = Company::query()->withoutGlobalScopes()
            ->whereIn('id', $companyIds)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->with(['tenant:id,name'])
            ->get(['id', 'tenant_id', 'name', 'slug', 'type', 'city']);

        return array_values($companies->map(function (Company $company) use ($sales, $commission, $planFees, $vatRates): array {
            $gross = $this->sum($sales[$company->id] ?? collect(), 'gross');
            $paidCommission = $this->sum($commission[$company->id] ?? collect(), 'commission');
            $fees = $this->sum($planFees[$company->id] ?? collect(), 'amount');
            $vatPercent = $vatRates[$company->tenant_id] ?? (float) MarketplaceSettings::DEFAULTS['vat_percent'];

            return [
                'id' => $company->id,
                'name' => $company->name,
                'type' => $company->type->value,
                'type_label' => $company->type === CompanyType::SHOP ? 'Store' : 'Business',
                'city' => $company->city,
                'marketplace' => $company->tenant->name ?? '',
                'orders' => (int) ($sales[$company->id] ?? collect())->sum('orders'),
                'gross_minor' => $gross,
                'revenue' => Money::format($gross, $this->currency),
                'commission' => Money::format($paidCommission, $this->currency),
                'plan_fees' => Money::format($fees, $this->currency),
                'expenses' => Money::format($paidCommission + $fees, $this->currency),
                'net' => Money::format($gross - $paidCommission - $fees, $this->currency),
                'vat_percent' => $vatPercent,
                'vat' => Money::format(self::vatIn($gross, $vatPercent), $this->currency),
            ];
        })->sortByDesc('gross_minor')->take($limit)->all());
    }

    /**
     * The VAT included in a VAT-inclusive amount, e.g. 18% of 118 is 18.
     */
    public static function vatIn(int $amount, float $percent): int
    {
        return $percent <= 0 ? 0 : (int) round($amount * $percent / (100 + $percent));
    }

    /**
     * Each marketplace's VAT rate (its setting, or the default).
     *
     * @return array<int, float>
     */
    private function vatRates(): array
    {
        return Setting::query()->withoutGlobalScopes()
            ->where('key', 'vat_percent')
            ->pluck('value', 'tenant_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * Amounts per currency added up in USD; currencies without a rate are left out.
     *
     * @param  iterable<\stdClass>  $rows
     */
    private function sum(iterable $rows, string $column): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += (int) $this->converter->convert((int) $row->{$column}, (string) $row->currency, $this->currency);
        }

        return $total;
    }
}
