import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { DetailDialog } from '@/components/detail-dialog';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/pagination';
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
import disputeRoutes from '@/routes/admin/disputes';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    order: string;
    seller: string;
    buyer: string;
    buyer_email: string;
    reason: string;
    status: string;
    details: string;
    order_total: string;
    order_status: string;
    resolution_note: string | null;
    resolved_at: string | null;
    resolved_by: string | null;
    created_at: string | null;
};

const tabs = [
    { value: 'open', label: 'Open' },
    { value: 'resolved_release', label: 'Released to seller' },
    { value: 'resolved_refund', label: 'Refunded to buyer' },
];

export default function DisputesIndex({
    disputes,
    filters,
}: {
    disputes: Paginated<Row>;
    filters: { status: string };
}) {
    const [viewing, setViewing] = useState<Row | null>(null);

    return (
        <>
            <Head title="Disputes" />
            <div className="p-4">
                <Heading
                    title="Disputes"
                    description="Payments stay held until you release them to the seller or refund the buyer."
                />
                <nav
                    aria-label="Filter by status"
                    className="mb-4 flex flex-wrap gap-2"
                >
                    {tabs.map((tab) => (
                        <Link
                            key={tab.value}
                            href={disputeRoutes.index({
                                query: { status: tab.value },
                            })}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm hover:bg-accent',
                                filters.status === tab.value &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            aria-current={
                                filters.status === tab.value
                                    ? 'page'
                                    : undefined
                            }
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>
                {disputes.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        Nothing here.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Buyer</TableHead>
                                    <TableHead>Seller</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Opened</TableHead>
                                    <TableHead className="text-right">
                                        Details
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {disputes.data.map((dispute) => (
                                    <TableRow
                                        key={dispute.id}
                                        className="cursor-pointer"
                                        onClick={() => setViewing(dispute)}
                                    >
                                        <TableCell>
                                            <Link
                                                href={disputeRoutes.show(
                                                    dispute.id,
                                                )}
                                                className="font-mono text-xs text-primary hover:underline"
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                {dispute.order}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{dispute.buyer}</TableCell>
                                        <TableCell>{dispute.seller}</TableCell>
                                        <TableCell>{dispute.reason}</TableCell>
                                        <TableCell>
                                            {formatDate(dispute.created_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="icon"
                                                variant="outline"
                                                title="View details"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setViewing(dispute);
                                                }}
                                            >
                                                <Eye className="h-4 w-4" />
                                                <span className="sr-only">
                                                    View dispute on{' '}
                                                    {dispute.order}
                                                </span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination page={disputes} />
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={`Dispute on ${viewing?.order ?? ''}`}
                description={viewing?.reason}
                rows={
                    viewing
                        ? [
                              {
                                  label: 'Status',
                                  value: (
                                      <StatusBadge status={viewing.status} />
                                  ),
                              },
                              {
                                  label: 'Buyer',
                                  value: `${viewing.buyer} (${viewing.buyer_email})`,
                              },
                              { label: 'Seller', value: viewing.seller },
                              {
                                  label: 'Order total',
                                  value: viewing.order_total,
                              },
                              {
                                  label: 'Order status',
                                  value: viewing.order_status,
                              },
                              {
                                  label: 'Opened',
                                  value: formatDate(viewing.created_at),
                              },
                              {
                                  label: "Buyer's account",
                                  value: (
                                      <span className="whitespace-pre-line">
                                          {viewing.details}
                                      </span>
                                  ),
                              },
                              {
                                  label: 'Decision',
                                  value: viewing.resolution_note,
                              },
                              {
                                  label: 'Decided',
                                  value: viewing.resolved_at
                                      ? `${formatDate(viewing.resolved_at)}${viewing.resolved_by ? ` by ${viewing.resolved_by}` : ''}`
                                      : null,
                              },
                          ]
                        : []
                }
                actions={
                    viewing && (
                        <Button asChild>
                            <Link href={disputeRoutes.show(viewing.id)}>
                                {viewing.status === 'open'
                                    ? 'Review and decide'
                                    : 'Open full case'}
                            </Link>
                        </Button>
                    )
                }
            />
        </>
    );
}

DisputesIndex.layout = {
    breadcrumbs: [{ title: 'Disputes', href: disputeRoutes.index() }],
};
