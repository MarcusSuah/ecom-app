import { Head } from '@inertiajs/react';
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
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { exportMethod, index as reportsIndex } from '@/routes/admin/reports';

type SellerRow = {
    company_id: number;
    seller: string;
    type: string;
    currency: string;
    orders: number;
    gross_formatted: string;
    commission_formatted: string;
    net_formatted: string;
};

const downloads = [
    { type: 'orders', label: 'Orders CSV' },
    { type: 'items', label: 'Order items CSV' },
    { type: 'payouts', label: 'Payouts CSV' },
    { type: 'sellers', label: 'Sales by seller CSV' },
];

export default function AdminReports({
    period,
    reportCurrency,
    rates,
    totals,
    sellers,
}: {
    period: ReportPeriod;
    reportCurrency: ReportCurrency;
    rates: RateUsed[];
    totals: SalesTotals[];
    sellers: SellerRow[];
}) {
    return (
        <>
            <Head title="Reports" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Reports"
                    description="Sales, commission, refunds and payouts across the marketplace. Downloads cover the same dates."
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
                </div>

                <SalesTotalsCards totals={totals} />

                <section aria-labelledby="top-sellers" className="space-y-3">
                    <h2 id="top-sellers" className="text-lg font-semibold">
                        Top sellers
                    </h2>
                    {sellers.length === 0 ? (
                        <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                            No sales in this period.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Seller</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead className="text-right">
                                            Orders
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sales
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Commission
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Seller earnings
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sellers.map((row) => (
                                        <TableRow
                                            key={`${row.company_id}-${row.currency}`}
                                        >
                                            <TableCell className="font-medium">
                                                {row.seller}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {row.type}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.orders.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.gross_formatted}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.commission_formatted}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.net_formatted}
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

AdminReports.layout = {
    breadcrumbs: [{ title: 'Reports', href: reportsIndex() }],
};
