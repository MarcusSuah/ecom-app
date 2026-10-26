import { Head, Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
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
import rfqRoutes from '@/routes/rfqs';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    title: string;
    quantity: number;
    unit: string;
    category: string;
    status: string;
    quotes_count: number;
    recipients_count: number;
    deliver_city: string;
    needed_by: string | null;
    notes: string | null;
    expires_at: string;
    created_at: string | null;
};

export default function MyRequests({ rfqs }: { rfqs: Paginated<Row> }) {
    const [viewing, setViewing] = useState<Row | null>(null);

    return (
        <>
            <Head title="My requests" />
            <div className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="My requests"
                        description="Requests for quotation you have sent and the quotes they received."
                    />
                    <Button asChild>
                        <Link href={rfqRoutes.create()}>
                            <Plus aria-hidden="true" />
                            New request
                        </Link>
                    </Button>
                </div>
                {rfqs.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        You have not sent any requests yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Request</TableHead>
                                    <TableHead>Quantity</TableHead>
                                    <TableHead className="text-right">
                                        Quotes
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Closes</TableHead>
                                    <TableHead className="sr-only">
                                        Details
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rfqs.data.map((rfq) => (
                                    <TableRow
                                        key={rfq.id}
                                        className="cursor-pointer"
                                        onClick={() => setViewing(rfq)}
                                    >
                                        <TableCell>
                                            <span className="font-medium">
                                                {rfq.title}
                                            </span>
                                            <div className="text-xs text-muted-foreground">
                                                {rfq.category}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {rfq.quantity.toLocaleString()}{' '}
                                            {rfq.unit}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {rfq.quotes_count}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={rfq.status} />
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(rfq.expires_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View ${rfq.title}`}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setViewing(rfq);
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
                <Pagination page={rfqs} />
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={viewing?.title ?? ''}
                description={viewing?.category}
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
                                  label: 'Quantity',
                                  value: `${viewing.quantity.toLocaleString()} ${viewing.unit}`,
                              },
                              {
                                  label: 'Deliver to',
                                  value: viewing.deliver_city,
                              },
                              {
                                  label: 'Needed by',
                                  value: viewing.needed_by
                                      ? formatDate(viewing.needed_by)
                                      : null,
                              },
                              {
                                  label: 'Sent to',
                                  value: `${viewing.recipients_count} ${viewing.recipients_count === 1 ? 'supplier' : 'suppliers'}`,
                              },
                              {
                                  label: 'Quotes',
                                  value: String(viewing.quotes_count),
                              },
                              {
                                  label: 'Sent',
                                  value: formatDate(viewing.created_at),
                              },
                              {
                                  label: 'Closes',
                                  value: formatDate(viewing.expires_at),
                              },
                              { label: 'Notes', value: viewing.notes },
                          ]
                        : []
                }
                actions={
                    viewing && (
                        <Button asChild>
                            <Link href={rfqRoutes.show(viewing.id)}>
                                {viewing.quotes_count > 0
                                    ? 'Compare quotes'
                                    : 'Open request'}
                            </Link>
                        </Button>
                    )
                }
            />
        </>
    );
}

MyRequests.layout = {
    breadcrumbs: [{ title: 'My requests', href: rfqRoutes.index() }],
};
