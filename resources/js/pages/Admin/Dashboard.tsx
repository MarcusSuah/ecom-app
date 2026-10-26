import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    Building2,
    ClipboardCheck,
    Coins,
    Factory,
    Receipt,
    Scale,
    ShoppingBag,
    Store,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import {
    KpiCard,
    type KpiColor,
    OrdersChart,
    RangePicker,
    RevenueChart,
    StatusChart,
    type StatusCount,
    type Trend,
} from '@/components/dashboard/charts';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { show as billingShow } from '@/routes/admin/billing';
import { index as companiesIndex } from '@/routes/admin/companies';
import { index as disputesIndex } from '@/routes/admin/disputes';
import { index as payoutsIndex } from '@/routes/admin/payouts';
import { index as usersIndex } from '@/routes/admin/users';
import { index as verificationsIndex } from '@/routes/admin/verifications';
import type { Subscription } from '@/types';

type Tab =
    | 'members'
    | 'suppliers'
    | 'shops'
    | 'verified'
    | 'pending_verifications'
    | 'orders'
    | 'disputes'
    | 'payouts';

type Props = {
    range: number;
    currency: string;
    currencySubunits: number;
    counts: Record<Tab, number>;
    kpis: {
        gmv: string;
        gmv_change: number | null;
        orders_change: number | null;
        commission: string;
        pending_payouts: string;
    };
    trend: Trend;
    byStatus: StatusCount[];
    tab: Tab;
    table: {
        columns: string[];
        rows: {
            id: number | string;
            link?: string;
            cells: (string | number | null)[];
        }[];
    };
    marketplacePlan: Subscription | null;
};

const cards: {
    tab: Tab;
    label: string;
    icon: typeof Users;
    color: KpiColor;
    title: string;
    more: { href: ReturnType<typeof usersIndex>; label: string } | null;
    attention?: boolean;
}[] = [
    {
        tab: 'orders',
        label: 'Orders',
        icon: Receipt,
        color: 'blue',
        title: 'Latest orders',
        more: null,
    },
    {
        tab: 'members',
        label: 'Members',
        icon: Users,
        color: 'sky',
        title: 'Newest members',
        more: { href: usersIndex(), label: 'All members' },
    },
    {
        tab: 'suppliers',
        label: 'Suppliers',
        icon: Factory,
        color: 'violet',
        title: 'Newest suppliers',
        more: {
            href: companiesIndex({ query: { type: 'supplier' } }),
            label: 'All suppliers',
        },
    },
    {
        tab: 'shops',
        label: 'Shops',
        icon: Store,
        color: 'teal',
        title: 'Newest shops',
        more: {
            href: companiesIndex({ query: { type: 'shop' } }),
            label: 'All shops',
        },
    },
    {
        tab: 'verified',
        label: 'Verified suppliers',
        icon: BadgeCheck,
        color: 'green',
        title: 'Verified suppliers',
        more: {
            href: companiesIndex({ query: { type: 'supplier', verified: 1 } }),
            label: 'All verified',
        },
    },
    {
        tab: 'pending_verifications',
        label: 'Pending verifications',
        icon: ClipboardCheck,
        color: 'amber',
        title: 'Waiting for review',
        more: { href: verificationsIndex(), label: 'Review queue' },
        attention: true,
    },
    {
        tab: 'disputes',
        label: 'Open disputes',
        icon: Scale,
        color: 'orange',
        title: 'Open disputes',
        more: { href: disputesIndex(), label: 'All disputes' },
        attention: true,
    },
    {
        tab: 'payouts',
        label: 'Payouts to send',
        icon: Banknote,
        color: 'rose',
        title: 'Payouts to send',
        more: { href: payoutsIndex(), label: 'All payouts' },
        attention: true,
    },
];

const isDate = (value: unknown) =>
    typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(value);

export default function AdminDashboard({
    range,
    currency,
    currencySubunits,
    counts,
    kpis,
    trend,
    byStatus,
    tab,
    table,
    marketplacePlan,
}: Props) {
    const current = cards.find((card) => card.tab === tab) ?? cards[0];

    const select = (next: Tab) =>
        router.get(
            dashboard.url(),
            { tab: next, range },
            {
                only: ['tab', 'table'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );

    return (
        <>
            <Head title="Marketplace admin" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Marketplace admin"
                        description="Live figures for this marketplace. Choose a card to see its records below."
                    />
                    <RangePicker
                        value={range}
                        url={dashboard.url()}
                        extra={{ tab }}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        icon={TrendingUp}
                        color="green"
                        label="Sales (GMV)"
                        value={kpis.gmv}
                        change={kpis.gmv_change}
                        hint="paid orders"
                    />
                    <KpiCard
                        icon={Coins}
                        color="blue"
                        label="Commission earned"
                        value={kpis.commission}
                        hint="on released payments"
                    />
                    <KpiCard
                        icon={Wallet}
                        color="violet"
                        label="Owed to sellers"
                        value={kpis.pending_payouts}
                        hint="pending payouts"
                    />
                    <KpiCard
                        icon={ShoppingBag}
                        color="teal"
                        label="Paid orders"
                        value={counts.orders.toLocaleString()}
                        change={kpis.orders_change}
                    />
                </div>

                <section
                    aria-labelledby="records-heading"
                    className="space-y-4"
                >
                    <h2 id="records-heading" className="sr-only">
                        Records
                    </h2>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {cards.map((card) => (
                            <KpiCard
                                key={card.tab}
                                icon={card.icon}
                                color={card.color}
                                label={card.label}
                                value={counts[card.tab].toLocaleString()}
                                tone={
                                    card.attention && counts[card.tab] > 0
                                        ? 'attention'
                                        : 'default'
                                }
                                active={card.tab === tab}
                                onSelect={() => select(card.tab)}
                            />
                        ))}
                    </div>

                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <current.icon
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    {current.title}
                                </CardTitle>
                                <CardDescription>
                                    The ten most recent.
                                </CardDescription>
                            </div>
                            {current.more && (
                                <Link
                                    href={current.more.href}
                                    className="text-sm font-medium text-primary hover:underline"
                                >
                                    {current.more.label}
                                </Link>
                            )}
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {table.columns.map((column) => (
                                                <TableHead key={column}>
                                                    {column}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {table.rows.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={
                                                        table.columns.length
                                                    }
                                                    className="h-24 text-center text-muted-foreground"
                                                >
                                                    Nothing here yet.
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            table.rows.map((row) => (
                                                <TableRow
                                                    key={row.id}
                                                    className={
                                                        row.link
                                                            ? 'cursor-pointer'
                                                            : undefined
                                                    }
                                                    onClick={() =>
                                                        row.link &&
                                                        router.visit(row.link)
                                                    }
                                                >
                                                    {row.cells.map(
                                                        (cell, index) => (
                                                            <TableCell
                                                                key={index}
                                                                className={
                                                                    index === 0
                                                                        ? 'font-medium'
                                                                        : undefined
                                                                }
                                                            >
                                                                {isDate(
                                                                    cell,
                                                                ) ? (
                                                                    formatDate(
                                                                        cell as string,
                                                                    )
                                                                ) : table
                                                                      .columns[
                                                                      index
                                                                  ] ===
                                                                  'Status' ? (
                                                                    <StatusBadge
                                                                        status={String(
                                                                            cell,
                                                                        )
                                                                            .toLowerCase()
                                                                            .replaceAll(
                                                                                ' ',
                                                                                '_',
                                                                            )}
                                                                    />
                                                                ) : (
                                                                    (cell ??
                                                                    '—')
                                                                )}
                                                            </TableCell>
                                                        ),
                                                    )}
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RevenueChart
                        trend={trend}
                        currency={currency}
                        subunits={currencySubunits}
                        title="Sales trend"
                    />
                    <OrdersChart trend={trend} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <StatusChart data={byStatus} />
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                Platform plan
                                {marketplacePlan && (
                                    <StatusBadge
                                        status={marketplacePlan.status}
                                    />
                                )}
                            </CardTitle>
                            <CardDescription>
                                {marketplacePlan
                                    ? `${marketplacePlan.plan.name} · renews ${formatDate(marketplacePlan.current_period_ends_at)}`
                                    : 'No current plan: the marketplace is closed to visitors.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Link
                                href={billingShow()}
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                Manage platform plan
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Admin', href: dashboard() }],
};
