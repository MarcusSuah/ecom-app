import { Head, Link, router } from '@inertiajs/react';
import {
    Banknote,
    FileDown,
    Landmark,
    Percent,
    Receipt,
    Repeat,
    ShoppingBag,
    Store,
} from 'lucide-react';
import {
    KpiCard,
    RangePicker,
    RevenueChart,
} from '@/components/dashboard/charts';
import type { Trend } from '@/components/dashboard/charts';
import Heading from '@/components/heading';
import { PeriodFilter } from '@/components/reports/period-filter';
import {
    RatesUsed,
    ReportCurrencyPicker,
} from '@/components/reports/report-currency';
import type {
    RateUsed,
    ReportCurrency,
} from '@/components/reports/report-currency';
import type { ReportPeriod } from '@/components/reports/period-filter';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import {
    exportMethod,
    index as analyticsIndex,
} from '@/routes/platform/analytics';
import tenantRoutes from '@/routes/platform/tenants';

type Level = 'marketplaces' | 'businesses' | 'stores';

type MarketplaceRow = {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string | null;
    subscription_status: string | null;
    mrr: string | null;
    sellers: number;
    members: number;
    orders: number;
    gmv: string;
    commission: string;
    seller_plans: string;
    revenue: string;
    expenses: string;
    net: string;
    refunds: string;
    vat_percent: number;
    vat: string;
};

type SellerRow = {
    id: number;
    name: string;
    type_label: string;
    city: string | null;
    marketplace: string;
    orders: number;
    revenue: string;
    commission: string;
    plan_fees: string;
    expenses: string;
    net: string;
    vat_percent: number;
    vat: string;
};

type Totals = {
    mrr: string;
    gmv: string;
    commission: string;
    seller_plans: string;
    platform_fees: string;
    refunds: string;
    vat: string;
    orders: number;
    sellers: number;
    members: number;
    marketplaces: number;
};

const levels: { value: Level; label: string; hint: string }[] = [
    {
        value: 'marketplaces',
        label: 'Marketplaces',
        hint: 'Revenue is commission and seller-plan fees; expenses are the platform plan invoices it paid.',
    },
    {
        value: 'businesses',
        label: 'Businesses',
        hint: 'Wholesale suppliers. Revenue is their sales; expenses are the commission and seller-plan fees they paid.',
    },
    {
        value: 'stores',
        label: 'Stores',
        hint: 'Local shops. Revenue is their sales; expenses are the commission and seller-plan fees they paid.',
    },
];

const num = 'text-right tabular-nums';

export default function PlatformAnalytics({
    period,
    range,
    level,
    currency,
    currencySubunits,
    marketplaces,
    sellers,
    totals,
    trend,
    reportCurrency,
    rates,
}: {
    period: ReportPeriod;
    range: number;
    level: Level;
    currency: string;
    currencySubunits: number;
    marketplaces: MarketplaceRow[];
    sellers: SellerRow[];
    totals: Totals;
    trend: Trend;
    reportCurrency: ReportCurrency;
    rates: RateUsed[];
}) {
    const keep = { level, currency };
    const current = levels.find((item) => item.value === level) ?? levels[0];
    const switchLevel = (value: Level) =>
        router.get(
            analyticsIndex.url(),
            { ...period, range, currency, level: value },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Platform analytics" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Platform analytics"
                    description={`Revenue, expenses and VAT for every marketplace, business and store. Amounts are added up in ${currency} at the rate in use.`}
                />

                <section aria-labelledby="sales-trend" className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="sales-trend" className="text-lg font-semibold">
                            Sales across all marketplaces
                        </h2>
                        <RangePicker
                            value={range}
                            url={analyticsIndex.url()}
                            extra={{ ...period, ...keep }}
                        />
                    </div>
                    <RevenueChart
                        trend={trend}
                        currency={currency}
                        subunits={currencySubunits}
                        title="Sales"
                    />
                </section>

                <section aria-labelledby="breakdown" className="space-y-4">
                    <h2 id="breakdown" className="text-lg font-semibold">
                        Revenue, expenses and tax
                    </h2>
                    <PeriodFilter
                        period={period}
                        url={analyticsIndex.url({
                            query: { range, ...keep },
                        })}
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <ReportCurrencyPicker
                            value={reportCurrency}
                            url={analyticsIndex.url()}
                            query={{ ...period, range, level }}
                            allowOwn={false}
                        />
                    </div>
                    <RatesUsed rates={rates} />

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <KpiCard
                            icon={Banknote}
                            label="Sales"
                            value={totals.gmv}
                            hint={`${totals.orders.toLocaleString()} paid orders · ${totals.refunds} refunded`}
                        />
                        <KpiCard
                            icon={Percent}
                            label="Commission"
                            value={totals.commission}
                            hint="Marketplace revenue, seller expense"
                        />
                        <KpiCard
                            icon={ShoppingBag}
                            label="Seller plan fees"
                            value={totals.seller_plans}
                            hint="Marketplace revenue, seller expense"
                        />
                        <KpiCard
                            icon={Landmark}
                            label="VAT in sales"
                            value={totals.vat}
                            hint="At each marketplace’s rate, included in prices"
                        />
                        <KpiCard
                            icon={Receipt}
                            label="Platform fees paid"
                            value={totals.platform_fees}
                            hint="Marketplace plan invoices in the period"
                        />
                        <KpiCard
                            icon={Repeat}
                            label="Monthly plan revenue"
                            value={totals.mrr}
                            hint="Marketplace plans, today"
                        />
                        <KpiCard
                            icon={Store}
                            label="Marketplaces"
                            value={totals.marketplaces.toLocaleString()}
                            hint={`${totals.sellers.toLocaleString()} sellers · ${totals.members.toLocaleString()} members`}
                        />
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div
                            role="tablist"
                            aria-label="Breakdown"
                            className="inline-flex rounded-md border p-0.5"
                        >
                            {levels.map((item) => (
                                <button
                                    key={item.value}
                                    type="button"
                                    role="tab"
                                    aria-selected={item.value === level}
                                    onClick={() => switchLevel(item.value)}
                                    className={cn(
                                        'rounded px-3 py-1 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        item.value === level &&
                                            'bg-primary text-primary-foreground hover:text-primary-foreground',
                                    )}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={exportMethod.url({
                                    query: { ...period, ...keep },
                                })}
                            >
                                <FileDown aria-hidden="true" />
                                Download CSV
                            </a>
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {current.hint}
                    </p>

                    <div className="overflow-x-auto rounded-md border">
                        {level === 'marketplaces' ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Marketplace</TableHead>
                                        <TableHead>Plan</TableHead>
                                        <TableHead className="text-right">
                                            Sellers
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Paid orders
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sales
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Expenses
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Net
                                        </TableHead>
                                        <TableHead className="text-right">
                                            VAT in sales
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {marketplaces.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link
                                                    href={tenantRoutes.show(
                                                        row.id,
                                                    )}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                                <span className="ml-2">
                                                    <StatusBadge
                                                        status={row.status}
                                                    />
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {row.plan ?? (
                                                    <span className="text-muted-foreground">
                                                        None
                                                    </span>
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.subscription_status ===
                                                    'trialing'
                                                        ? 'Trial'
                                                        : (row.mrr ?? '')}
                                                </span>
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.sellers.toLocaleString()}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.orders.toLocaleString()}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.gmv}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.revenue}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.commission} +{' '}
                                                    {row.seller_plans}
                                                </span>
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.expenses}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    num,
                                                    'font-medium',
                                                )}
                                            >
                                                {row.net}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.vat}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.vat_percent}%
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : sellers.length === 0 ? (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No {level} sold or paid fees in this period.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {level === 'stores'
                                                ? 'Store'
                                                : 'Business'}
                                        </TableHead>
                                        <TableHead>Marketplace</TableHead>
                                        <TableHead className="text-right">
                                            Paid orders
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Expenses
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Net
                                        </TableHead>
                                        <TableHead className="text-right">
                                            VAT in sales
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sellers.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {row.name}
                                                </span>
                                                {row.city && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {row.city}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {row.marketplace}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.orders.toLocaleString()}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.revenue}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.expenses}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.commission} commission
                                                    · {row.plan_fees} plan
                                                </span>
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    num,
                                                    'font-medium',
                                                )}
                                            >
                                                {row.net}
                                            </TableCell>
                                            <TableCell className={num}>
                                                {row.vat}
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.vat_percent}%
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

PlatformAnalytics.layout = {
    breadcrumbs: [{ title: 'Platform analytics', href: analyticsIndex() }],
};
