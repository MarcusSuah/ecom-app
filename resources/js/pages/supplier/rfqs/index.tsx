import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import supplierRfqs from '@/routes/supplier/rfqs';
import type { Paginated } from '@/types';

type Row = {
    rfq_id: number;
    title: string;
    quantity: number;
    unit: string;
    category: string;
    deliver_city: string;
    rfq_status: string;
    expires_at: string;
    is_new: boolean;
    declined: boolean;
    quote_status: string | null;
};

export default function SupplierRfqInbox({
    requests,
    quotesThisMonth,
    monthlyLimit,
}: {
    requests: Paginated<Row>;
    quotesThisMonth: number;
    monthlyLimit: number | null;
}) {
    return (
        <>
            <Head title="Requests for quotation" />
            <div className="p-4">
                <Heading
                    title="Requests for quotation"
                    description={
                        monthlyLimit === null
                            ? `${quotesThisMonth} quotes sent this month.`
                            : `${quotesThisMonth} of ${monthlyLimit} quotes sent this month on your plan.`
                    }
                />
                {requests.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No requests yet. Buyers' requests that match your
                        verified listings appear here.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Request</TableHead>
                                    <TableHead>Quantity</TableHead>
                                    <TableHead>Deliver to</TableHead>
                                    <TableHead>Your reply</TableHead>
                                    <TableHead>Closes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requests.data.map((request) => (
                                    <TableRow
                                        key={request.rfq_id}
                                        className={
                                            request.is_new
                                                ? 'font-medium'
                                                : undefined
                                        }
                                    >
                                        <TableCell>
                                            <Link
                                                href={supplierRfqs.show(
                                                    request.rfq_id,
                                                )}
                                                className="text-primary hover:underline"
                                            >
                                                {request.title}
                                            </Link>
                                            {request.is_new && (
                                                <Badge className="ml-2">
                                                    New
                                                </Badge>
                                            )}
                                            <div className="text-xs font-normal text-muted-foreground">
                                                {request.category}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {request.quantity.toLocaleString()}{' '}
                                            {request.unit}
                                        </TableCell>
                                        <TableCell>
                                            {request.deliver_city}
                                        </TableCell>
                                        <TableCell>
                                            {request.quote_status ? (
                                                <StatusBadge
                                                    status={
                                                        request.quote_status
                                                    }
                                                />
                                            ) : request.declined ? (
                                                <StatusBadge status="declined" />
                                            ) : request.rfq_status !==
                                              'open' ? (
                                                <StatusBadge
                                                    status={request.rfq_status}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Not replied
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(request.expires_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination page={requests} />
            </div>
        </>
    );
}

SupplierRfqInbox.layout = {
    breadcrumbs: [
        { title: 'Requests for quotation', href: supplierRfqs.index() },
    ],
};
