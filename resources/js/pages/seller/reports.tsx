import { Head, Link } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import Heading from '@/components/heading';
import { PeriodFilter } from '@/components/reports/period-filter';
import type { ReportPeriod } from '@/components/reports/period-filter';
import {
    RatesUsed,
    ReportCurrencyPicker,
} from '@/components/reports/report-currency';
import type {
    RateUsed,
    ReportCurrency,
} from '@/components/reports/report-currency';
import { SalesTotalsCards } from '@/components/reports/sales-totals';
import type { SalesTotals } from '@/components/reports/sales-totals';
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
import { formatDate } from '@/lib/format';
import sellerOrders from '@/routes/seller/orders';
import { statement } from '@/routes/seller/payouts';
import { exportMethod, index as reportsIndex } from '@/routes/seller/reports';

type RecentOrder = {
    id: number;
    number: string;
    buyer: string;
    total: string;
    status: string;
    placed_at: string | null;
};

const downloads = [
    { type: 'orders', label: 'Orders CSV' },
    { type: 'items', label: 'Order items CSV' },
    { type: 'payouts', label: 'Payouts CSV' },
];

export default function SellerReports({
    period,
    reportCurrency,
    rates,
    totals,
    recentOrders,
}: {
    period: ReportPeriod;
    reportCurrency: ReportCurrency;
    rates: RateUsed[];
    totals: SalesTotals[];
    recentOrders: RecentOrder[];
}) {
    return (
        <>
            <Head title="Reports" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Reports"
                    description="Your sales, commission and payouts for a period. Downloads cover the same dates."
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PeriodFilter
                        period={period}
                        url={reportsIndex.url(
                            reportCurrency.code
                                ? { query: { currency: reportCurrency.code } }
                                : undefined,
                        )}
                    />
                    <ReportCurrencyPicker
                        value={reportCurrency}
                        url={reportsIndex.url()}
                        query={period}
                    />
                </div>
                <RatesUsed rates={rates} />

                <div className="flex flex-wrap gap-2">
                    {downloads.map((download) => (
                        <Button
                            key={download.type}
                            variant="outline"
                            size="sm"
                            asChild
                        >
                            <a
                                href={exportMethod.url(download.type, {
                                    query: {
                                        ...period,
                                        ...(reportCurrency.code
                                            ? { currency: reportCurrency.code }
                                            : {}),
                                    },
                                })}
                            >
                                <FileDown aria-hidden="true" />
                                {download.label}
                            </a>
                        </Button>
                    ))}
                    <Button variant="outline" size="sm" asChild>
                        <a href={statement.url({ query: period })}>
                            <FileDown aria-hidden="true" />
                            Payout statement PDF
                        </a>
                    </Button>
                </div>

                <SalesTotalsCards totals={totals} netLabel="You receive" />

                <section aria-labelledby="recent-orders" className="space-y-3">
                    <h2 id="recent-orders" className="text-lg font-semibold">
                        Latest orders in this period
                    </h2>
                    {recentOrders.length === 0 ? (
                        <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                            No orders in this period.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Order</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Placed</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentOrders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell>
                                                <Link
                                                    href={sellerOrders.show(
                                                        order.id,
                                                    )}
                                                    className="font-mono text-xs text-primary hover:underline"
                                                >
                                                    {order.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{order.buyer}</TableCell>
                                            <TableCell>
                                                {formatDate(order.placed_at)}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={order.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {order.total}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

SellerReports.layout = {
    breadcrumbs: [{ title: 'Reports', href: reportsIndex() }],
};
