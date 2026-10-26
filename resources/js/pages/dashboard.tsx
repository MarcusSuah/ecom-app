import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Building2,
    CircleDollarSign,
    ClipboardList,
    FileText,
    Hourglass,
    PackageCheck,
    PackageX,
    Receipt,
    Scale,
    ShieldCheck,
    ShoppingBag,
    Store,
    Trophy,
    Truck,
    Wallet,
} from 'lucide-react';
import {
    KpiCard,
    OrdersChart,
    RangePicker,
    RevenueChart,
    StatusChart,
    type StatusCount,
    type Trend,
} from '@/components/dashboard/charts';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { VerifiedBadge } from '@/components/verified-badge';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { create as createCompany, edit as editCompany } from '@/routes/company';
import { show as billingShow } from '@/routes/company/billing';
import { index as ordersIndex, show as orderShow } from '@/routes/orders';
import { index as rfqsIndex } from '@/routes/rfqs';
import {
    index as sellerOrders,
    show as sellerOrderShow,
} from '@/routes/seller/orders';
import { index as sellerPayouts } from '@/routes/seller/payouts';
import type { CompanySummary, Plan, SharedData } from '@/types';

type OrderRow = {
    id: number;
    number: string;
    party: string;
    total: string;
    status: string;
    status_label: string;
    placed_at: string | null;
};

type Seller = {
    trend: Trend;
    kpis: {
        revenue_formatted: string;
        revenue_change: number | null;
        orders: number;
        orders_change: number | null;
        average_order: string | null;
        to_fulfil: number;
        awaiting_payment: number;
        open_disputes: number;
        payouts_pending: string;
        payouts_paid: string;
        listed: number;
        out_of_stock: number;
        rfqs_open: number | null;
        quotes_sent: number | null;
        quotes_won: number | null;
    };
    by_status: StatusCount[];
    top_products: { name: string; units: number; revenue: string }[];
    recent_orders: OrderRow[];
};

type Buyer = {
    trend: Trend;
    kpis: {
        spent: string;
        orders: number;
        awaiting_payment: number;
        on_the_way: number;
        rfqs_open: number;
        quotes_waiting: number;
    };
    recent_orders: OrderRow[];
};

type Props = {
    company: CompanySummary | null;
    plan: Plan | null;
    range: number;
    currency: string;
    currencySubunits: number;
    seller: Seller | null;
    buyer: Buyer;
};

function OrdersTable({
    title,
    orders,
    partyLabel,
    linkFor,
    more,
}: {
    title: string;
    orders: OrderRow[];
    partyLabel: string;
    linkFor: (id: number) => ReturnType<typeof orderShow>;
    more: ReturnType<typeof ordersIndex>;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle>{title}</CardTitle>
                <Link
                    href={more}
                    className="text-sm font-medium text-primary hover:underline"
                >
                    View all
                </Link>
            </CardHeader>
            <CardContent>
                {orders.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        No orders yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>{partyLabel}</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Placed</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.map((order) => (
                                    <TableRow key={order.id}>
                                        <TableCell>
                                            <Link
                                                href={linkFor(order.id)}
                                                className="font-mono text-xs text-primary hover:underline"
                                            >
                                                {order.number}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{order.party}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {order.total}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={order.status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(order.placed_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    company,
    plan,
    range,
    currency,
    currencySubunits,
    seller,
    buyer,
}: Props) {
    const { auth, tenant } = usePage<SharedData>().props;
    const { can } = usePermissions();

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Welcome, {auth.user.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {company
                                ? `${company.name} on ${tenant?.name}`
                                : tenant?.name}
                        </p>
                    </div>
                    <RangePicker value={range} url={dashboard.url()} />
                </div>

                {seller && company && (
                    <section
                        aria-labelledby="business-heading"
                        className="space-y-4"
                    >
                        <h2
                            id="business-heading"
                            className="text-lg font-semibold"
                        >
                            Your business
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <KpiCard
                                icon={CircleDollarSign}
                                color="green"
                                label="Revenue"
                                value={seller.kpis.revenue_formatted}
                                change={seller.kpis.revenue_change}
                                hint="paid orders"
                            />
                            <KpiCard
                                icon={Receipt}
                                color="blue"
                                label="Orders"
                                value={seller.kpis.orders.toLocaleString()}
                                change={seller.kpis.orders_change}
                                hint={
                                    seller.kpis.average_order
                                        ? `avg ${seller.kpis.average_order}`
                                        : undefined
                                }
                            />
                            <Link
                                href={sellerOrders()}
                                className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <KpiCard
                                    icon={Hourglass}
                                    color="amber"
                                    label="To fulfil"
                                    value={seller.kpis.to_fulfil}
                                    tone={
                                        seller.kpis.to_fulfil > 0
                                            ? 'attention'
                                            : 'default'
                                    }
                                    hint={`${seller.kpis.awaiting_payment} awaiting payment`}
                                />
                            </Link>
                            <Link
                                href={sellerPayouts()}
                                className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <KpiCard
                                    icon={Wallet}
                                    color="violet"
                                    label="Payouts pending"
                                    value={seller.kpis.payouts_pending}
                                    hint={`${seller.kpis.payouts_paid} paid this period`}
                                />
                            </Link>
                        </div>
                        <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                            <KpiCard
                                icon={PackageCheck}
                                color="teal"
                                label={
                                    company.type === 'shop'
                                        ? 'Items for sale'
                                        : 'Listed products'
                                }
                                value={seller.kpis.listed}
                            />
                            <KpiCard
                                icon={PackageX}
                                color="rose"
                                label="Out of stock"
                                value={seller.kpis.out_of_stock}
                                tone={
                                    seller.kpis.out_of_stock > 0
                                        ? 'attention'
                                        : 'default'
                                }
                            />
                            <KpiCard
                                icon={Scale}
                                color="orange"
                                label="Open disputes"
                                value={seller.kpis.open_disputes}
                                tone={
                                    seller.kpis.open_disputes > 0
                                        ? 'attention'
                                        : 'default'
                                }
                            />
                            {seller.kpis.rfqs_open !== null ? (
                                <KpiCard
                                    icon={FileText}
                                    color="sky"
                                    label="Open requests for you"
                                    value={seller.kpis.rfqs_open}
                                    hint={`${seller.kpis.quotes_sent ?? 0} quotes sent, ${seller.kpis.quotes_won ?? 0} won`}
                                />
                            ) : (
                                <KpiCard
                                    icon={Truck}
                                    color="slate"
                                    label="Awaiting payment"
                                    value={seller.kpis.awaiting_payment}
                                />
                            )}
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <RevenueChart
                                trend={seller.trend}
                                currency={currency}
                                subunits={currencySubunits}
                                title="Revenue trend"
                            />
                            <OrdersChart trend={seller.trend} />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <StatusChart data={seller.by_status} />
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Trophy
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        Best sellers
                                    </CardTitle>
                                    <CardDescription>
                                        By units sold in this period
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {seller.top_products.length === 0 ? (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No sales in this period yet.
                                        </p>
                                    ) : (
                                        <ol className="space-y-3">
                                            {seller.top_products.map(
                                                (product, index) => (
                                                    <li
                                                        key={product.name}
                                                        className="flex items-center gap-3 text-sm"
                                                    >
                                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted font-medium tabular-nums">
                                                            {index + 1}
                                                        </span>
                                                        <span className="min-w-0 flex-1 truncate font-medium">
                                                            {product.name}
                                                        </span>
                                                        <span className="text-muted-foreground tabular-nums">
                                                            {product.units.toLocaleString()}{' '}
                                                            units
                                                        </span>
                                                        <span className="w-28 text-right tabular-nums">
                                                            {product.revenue}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ol>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <OrdersTable
                            title="Latest orders received"
                            orders={seller.recent_orders}
                            partyLabel="Buyer"
                            linkFor={(id) => sellerOrderShow(id)}
                            more={sellerOrders()}
                        />
                    </section>
                )}

                <section aria-labelledby="buying-heading" className="space-y-4">
                    <h2 id="buying-heading" className="text-lg font-semibold">
                        Your buying
                    </h2>
                    <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                        <KpiCard
                            icon={ShoppingBag}
                            // color="green"
                            label="Spent"
                            value={buyer.kpis.spent}
                            hint={`${buyer.kpis.orders} orders this period`}
                        />
                        <Link
                            href={ordersIndex()}
                            className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <KpiCard
                                icon={AlertTriangle}
                                // color="rose"
                                label="Awaiting your payment"
                                value={buyer.kpis.awaiting_payment}
                                tone={
                                    buyer.kpis.awaiting_payment > 0
                                        ? 'attention'
                                        : 'default'
                                }
                            />
                        </Link>
                        <KpiCard
                            icon={Truck}
                            // color="slate"
                            label="On the way"
                            value={buyer.kpis.on_the_way}
                        />
                        <Link
                            href={rfqsIndex()}
                            className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <KpiCard
                                icon={ClipboardList}
                                // color="violet"
                                label="Open requests"
                                value={buyer.kpis.rfqs_open}
                                hint={`${buyer.kpis.quotes_waiting} quotes to compare`}
                            />
                        </Link>
                    </div>
                    {!seller && (
                        <RevenueChart
                            trend={buyer.trend}
                            currency={currency}
                            subunits={currencySubunits}
                            title="Your spending"
                        />
                    )}
                    <OrdersTable
                        title="Your latest orders"
                        orders={buyer.recent_orders}
                        partyLabel="Seller"
                        linkFor={(id) => orderShow(id)}
                        more={ordersIndex()}
                    />
                </section>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {company ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2">
                                    {company.name}
                                    <VerifiedBadge
                                        verified={company.is_verified}
                                    />
                                </CardTitle>
                                <CardDescription>
                                    {company.type_label}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <p className="flex items-center gap-2">
                                    Verification:{' '}
                                    {company.verification_status ? (
                                        <StatusBadge
                                            status={company.verification_status}
                                        />
                                    ) : (
                                        <span className="text-muted-foreground">
                                            not applied
                                        </span>
                                    )}
                                </p>
                                <p>
                                    Plan:{' '}
                                    <span className="font-medium">
                                        {plan?.name ?? 'None'}
                                    </span>
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild size="sm">
                                        <Link href={editCompany()}>
                                            Manage company
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={billingShow()}>
                                            <Banknote aria-hidden="true" />
                                            Plan & billing
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                    Sell on {tenant?.name}
                                </CardTitle>
                                <CardDescription>
                                    Register as a wholesale supplier or a local
                                    shop, then get verified.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild>
                                    <Link href={createCompany()}>
                                        <Store aria-hidden="true" />
                                        Start selling
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {can('admin.dashboard') && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                    Marketplace admin
                                </CardTitle>
                                <CardDescription>
                                    Members, companies, verifications, orders,
                                    payouts and settings.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild variant="outline">
                                    <Link href={adminDashboard()}>
                                        Open admin
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
