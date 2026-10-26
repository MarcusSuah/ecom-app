import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import sellerOrders from '@/routes/seller/orders';
import type { OrderSummary, Paginated } from '@/types';

export default function SellerOrders({
    orders,
    filters,
    statuses,
}: {
    orders: Paginated<OrderSummary & { buyer: string }>;
    filters: { status: string };
    statuses: { value: string; label: string }[];
}) {
    return (
        <>
            <Head title="Orders received" />
            <div className="p-4">
                <Heading
                    title="Orders received"
                    description="Paid orders from buyers. Move each one along as you prepare and send it."
                />
                <nav
                    aria-label="Filter by status"
                    className="mb-4 flex flex-wrap gap-2"
                >
                    {[{ value: '', label: 'All' }, ...statuses].map(
                        (status) => (
                            <Link
                                key={status.value}
                                href={sellerOrders.index({
                                    query: status.value
                                        ? { status: status.value }
                                        : {},
                                })}
                                className={cn(
                                    'rounded-md border px-3 py-1.5 text-sm hover:bg-accent',
                                    filters.status === status.value &&
                                        'border-primary bg-primary text-primary-foreground hover:bg-primary',
                                )}
                                aria-current={
                                    filters.status === status.value
                                        ? 'page'
                                        : undefined
                                }
                            >
                                {status.label}
                            </Link>
                        ),
                    )}
                </nav>
                {orders.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No orders here.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Ordered</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.data.map((order) => (
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
                                        <TableCell className="capitalize">
                                            {order.type === 'wholesale'
                                                ? 'Wholesale'
                                                : order.fulfilment}
                                        </TableCell>
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
                <Pagination page={orders} />
            </div>
        </>
    );
}

SellerOrders.layout = {
    breadcrumbs: [{ title: 'Orders received', href: sellerOrders.index() }],
};
