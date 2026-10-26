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
import sellerOrders from '@/routes/seller/orders';
import { index as payoutsIndex } from '@/routes/seller/payouts';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    order: string;
    order_id: number;
    gross: string;
    commission_rate: number;
    commission: string;
    net: string;
    status: string;
    paid_at: string | null;
    reference: string | null;
    created_at: string | null;
};

export default function SellerPayouts({
    payouts,
    pendingTotal,
}: {
    payouts: Paginated<Row>;
    pendingTotal: string[];
}) {
    return (
        <>
            <Head title="Payouts" />
            <div className="p-4">
                <Heading
                    title="Payouts"
                    description={
                        pendingTotal.length
                            ? `Owed to you: ${pendingTotal.join(' + ')}`
                            : 'Released payments, after commission.'
                    }
                />
                {payouts.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No payouts yet. Payments are released when buyers
                        confirm receipt.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead className="text-right">
                                        Order total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Commission
                                    </TableHead>
                                    <TableHead className="text-right">
                                        You receive
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Paid</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payouts.data.map((payout) => (
                                    <TableRow key={payout.id}>
                                        <TableCell>
                                            <Link
                                                href={sellerOrders.show(
                                                    payout.order_id,
                                                )}
                                                className="font-mono text-xs text-primary hover:underline"
                                            >
                                                {payout.order}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {payout.gross}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {payout.commission} (
                                            {payout.commission_rate}%)
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {payout.net}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={payout.status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(payout.paid_at)}
                                            {payout.reference && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {payout.reference}
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination page={payouts} />
            </div>
        </>
    );
}

SellerPayouts.layout = {
    breadcrumbs: [{ title: 'Payouts', href: payoutsIndex() }],
};
