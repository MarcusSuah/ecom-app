import { Banknote, FileDown, ShieldCheck } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { document as orderDocument } from '@/routes/orders';
import type { OrderSummary } from '@/types';

export type OrderDetailData = OrderSummary & {
    seller: { name: string; slug: string; phone: string | null };
    buyer: { name: string; email: string; phone: string | null };
    items: {
        name: string;
        quantity: number;
        unit_price: string;
        line_total: string;
    }[];
    seller_steps: { value: string; label: string }[];
    /** Pickup orders may be paid in cash at the shop. */
    can_pay_cash: boolean;
    payment: {
        status: 'initiated' | 'held' | 'released' | 'refunded' | 'failed';
        provider: string;
        amount: string;
        held_at: string | null;
        released_at: string | null;
        refunded_at: string | null;
    } | null;
    dispute: {
        status: string;
        reason: string;
        details: string;
        /** Proof the buyer added (private links). */
        photos?: string[];
        resolution_note: string | null;
        created_at: string | null;
    } | null;
    history: {
        to: string;
        by: string;
        note: string | null;
        at: string | null;
    }[];
};

/**
 * Items, totals, payment protection status and history of an order;
 * shared by the buyer, seller and admin views.
 */
export function OrderDetail({
    order,
    party,
    protectionLabel,
}: {
    order: OrderDetailData;
    party: 'buyer' | 'seller';
    protectionLabel: string;
}) {
    const isWholesale = order.type === 'wholesale';
    const isPaid = !['pending_payment', 'cancelled'].includes(order.status);

    return (
        <div className="space-y-6">
            <header className="flex flex-wrap items-center gap-3">
                <h1 className="text-xl font-semibold">Order {order.number}</h1>
                <StatusBadge status={order.status} />
                <span className="text-sm text-muted-foreground capitalize">
                    {order.type}
                </span>
                {order.payment_method === 'cash_on_pickup' && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        <Banknote className="size-3.5" aria-hidden="true" />
                        Cash on pickup
                    </span>
                )}
                <Button variant="outline" size="sm" className="ml-auto" asChild>
                    <a href={orderDocument.url(order.id)}>
                        <FileDown aria-hidden="true" />
                        {isPaid ? 'Download receipt' : 'Download invoice'}
                    </a>
                </Button>
            </header>

            {order.payment &&
                order.payment.status !== 'initiated' &&
                order.payment.status !== 'failed' && (
                    <p className="flex flex-wrap items-center gap-2 rounded-md border border-emerald-600/30 bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                        <ShieldCheck className="size-4" aria-hidden="true" />
                        <span className="font-medium">{protectionLabel}:</span>
                        {order.payment.status === 'held' &&
                            `${order.payment.amount} is held until the buyer confirms receipt.`}
                        {order.payment.status === 'released' &&
                            `${order.payment.amount} released to the seller on ${formatDate(order.payment.released_at)}.`}
                        {order.payment.status === 'refunded' &&
                            `${order.payment.amount} refunded to the buyer on ${formatDate(order.payment.refunded_at)}.`}
                    </p>
                )}

            <dl className="grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt className="text-muted-foreground">
                        {party === 'buyer' ? 'Seller' : 'Customer'}
                    </dt>
                    <dd className="font-medium">
                        {party === 'buyer'
                            ? order.seller.name
                            : order.buyer.name}
                    </dd>
                    <dd className="text-muted-foreground">
                        {party === 'buyer'
                            ? order.seller.phone
                            : (order.buyer.phone ?? order.buyer.email)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        {order.fulfilment === 'delivery'
                            ? 'Deliver to'
                            : 'Fulfilment'}
                    </dt>
                    {order.fulfilment === 'delivery' && order.address ? (
                        <>
                            <dd className="font-medium">
                                {order.address.label}
                            </dd>
                            <dd className="text-muted-foreground">
                                {[
                                    order.address.description,
                                    order.address.landmark &&
                                        `near ${order.address.landmark}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                                {order.address.distance_km !== null &&
                                !isWholesale
                                    ? ` · ${order.address.distance_km.toFixed(1)} km`
                                    : ''}
                            </dd>
                        </>
                    ) : (
                        <dd className="font-medium">Pickup at the shop</dd>
                    )}
                </div>
                <div>
                    <dt className="text-muted-foreground">Ordered</dt>
                    <dd className="font-medium">
                        {formatDate(order.placed_at ?? order.created_at)}
                    </dd>
                    {order.payment && (
                        <dd className="text-muted-foreground">
                            Paid with {order.payment.provider}
                        </dd>
                    )}
                </div>
            </dl>

            <div className="overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Item</TableHead>
                            <TableHead className="text-right">Qty</TableHead>
                            <TableHead className="text-right">
                                Unit price
                            </TableHead>
                            <TableHead className="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {order.items.map((item, index) => (
                            <TableRow key={index}>
                                <TableCell>{item.name}</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.quantity.toLocaleString()}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.unit_price}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.line_total}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        {order.fulfilment === 'delivery' && !isWholesale && (
                            <>
                                <TableRow>
                                    <TableCell colSpan={3}>Subtotal</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {order.subtotal}
                                    </TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell colSpan={3}>Delivery</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {order.delivery_fee}
                                    </TableCell>
                                </TableRow>
                            </>
                        )}
                        <TableRow>
                            <TableCell colSpan={3} className="font-semibold">
                                Total
                            </TableCell>
                            <TableCell className="text-right font-semibold tabular-nums">
                                {order.total}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>

            {order.dispute && (
                <section
                    aria-labelledby="dispute-heading"
                    className="space-y-1 rounded-md border border-amber-500/40 p-3 text-sm"
                >
                    <h2
                        id="dispute-heading"
                        className="flex items-center gap-2 font-semibold"
                    >
                        Dispute{' '}
                        <StatusBadge
                            status={
                                order.dispute.status === 'open'
                                    ? 'pending'
                                    : order.dispute.status.replace(
                                          'resolved_',
                                          '',
                                      )
                            }
                        />
                    </h2>
                    <p>
                        <span className="text-muted-foreground">
                            {order.dispute.reason}:{' '}
                        </span>
                        {order.dispute.details}
                    </p>
                    {(order.dispute.photos?.length ?? 0) > 0 && (
                        <ul
                            className="grid grid-cols-3 gap-2 sm:grid-cols-6"
                            aria-label="Photos added as proof"
                        >
                            {order.dispute.photos?.map((url, index) => (
                                <li key={url}>
                                    <a
                                        href={url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block aspect-square overflow-hidden rounded-md border hover:ring-2 hover:ring-primary"
                                    >
                                        <img
                                            src={url}
                                            alt={`Proof photo ${index + 1}`}
                                            loading="lazy"
                                            className="size-full object-cover"
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                    {order.dispute.resolution_note && (
                        <p className="text-muted-foreground">
                            Decision: {order.dispute.resolution_note}
                        </p>
                    )}
                </section>
            )}

            {order.history.length > 0 && (
                <section aria-labelledby="history-heading">
                    <h2 id="history-heading" className="mb-2 font-semibold">
                        History
                    </h2>
                    <ol className="space-y-1 border-l pl-4 text-sm">
                        {order.history.map((change, index) => (
                            <li key={index}>
                                <span className="font-medium">{change.to}</span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {change.by} ·{' '}
                                    {change.at
                                        ? new Date(change.at).toLocaleString(
                                              undefined,
                                              {
                                                  dateStyle: 'medium',
                                                  timeStyle: 'short',
                                              },
                                          )
                                        : ''}
                                </span>
                                {change.note && (
                                    <span className="block text-muted-foreground">
                                        {change.note}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>
            )}
        </div>
    );
}
