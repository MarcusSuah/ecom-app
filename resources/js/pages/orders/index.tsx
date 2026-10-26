import { Head, Link } from '@inertiajs/react';
import { Eye, FileDown } from 'lucide-react';
import { useState } from 'react';
import { DetailDialog } from '@/components/detail-dialog';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
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
import orderRoutes from '@/routes/orders';
import type { OrderSummary, Paginated } from '@/types';

type Row = OrderSummary & {
    seller: string;
    seller_phone: string | null;
    items: { name: string; quantity: number; line_total: string }[];
};

export default function MyOrders({ orders }: { orders: Paginated<Row> }) {
    const [viewing, setViewing] = useState<Row | null>(null);
    const isPaid = viewing
        ? !['pending_payment', 'cancelled'].includes(viewing.status)
        : false;

    return (
        <>
            <Head title="My orders" />
            <div className="p-4">
                <Heading
                    title="My orders"
                    description="Select an order to see its items and totals."
                />
                {orders.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No orders yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Shop</TableHead>
                                    <TableHead>Fulfilment</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead className="sr-only">
                                        Details
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.data.map((order) => (
                                    <TableRow
                                        key={order.id}
                                        className="cursor-pointer"
                                        onClick={() => setViewing(order)}
                                    >
                                        <TableCell className="font-mono text-xs">
                                            {order.number}
                                        </TableCell>
                                        <TableCell>{order.seller}</TableCell>
                                        <TableCell className="capitalize">
                                            {order.fulfilment}
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
                                            {formatDate(order.created_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View order ${order.number}`}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setViewing(order);
                                                }}
                                            >
                                                <Eye />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination page={orders} />
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={`Order ${viewing?.number ?? ''}`}
                description={viewing?.seller}
                rows={
                    viewing
                        ? [
                              {
                                  label: 'Status',
                                  value: (
                                      <StatusBadge status={viewing.status} />
                                  ),
                              },
                              { label: 'Shop', value: viewing.seller },
                              {
                                  label: 'Shop phone',
                                  value: viewing.seller_phone,
                              },
                              {
                                  label: 'Fulfilment',
                                  value:
                                      viewing.fulfilment === 'delivery'
                                          ? `Delivery to ${viewing.address?.label ?? 'your address'}`
                                          : 'Pickup',
                              },
                              { label: 'Subtotal', value: viewing.subtotal },
                              {
                                  label: 'Delivery',
                                  value:
                                      viewing.fulfilment === 'delivery'
                                          ? viewing.delivery_fee
                                          : null,
                              },
                              {
                                  label: 'Total',
                                  value: (
                                      <span className="font-semibold">
                                          {viewing.total}
                                      </span>
                                  ),
                              },
                              {
                                  label: 'Placed',
                                  value: formatDate(
                                      viewing.placed_at ?? viewing.created_at,
                                  ),
                              },
                          ]
                        : []
                }
                actions={
                    viewing && (
                        <>
                            <Button variant="outline" asChild>
                                <a href={orderRoutes.document.url(viewing.id)}>
                                    <FileDown aria-hidden="true" />
                                    {isPaid ? 'Receipt' : 'Invoice'}
                                </a>
                            </Button>
                            <Button asChild>
                                <Link href={orderRoutes.show(viewing.id)}>
                                    Open order
                                </Link>
                            </Button>
                        </>
                    )
                }
            >
                {viewing && viewing.items.length > 0 && (
                    <ul className="divide-y rounded-md border text-sm">
                        {viewing.items.map((item) => (
                            <li
                                key={item.name}
                                className="flex justify-between gap-3 px-3 py-2"
                            >
                                <span>
                                    {item.quantity.toLocaleString()} ×{' '}
                                    {item.name}
                                </span>
                                <span className="tabular-nums">
                                    {item.line_total}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </DetailDialog>
        </>
    );
}

MyOrders.layout = {
    breadcrumbs: [{ title: 'My orders', href: orderRoutes.index() }],
};
