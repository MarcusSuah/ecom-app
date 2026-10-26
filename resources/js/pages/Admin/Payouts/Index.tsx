import { Head, Link, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { DetailDialog } from '@/components/detail-dialog';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import payoutRoutes from '@/routes/admin/payouts';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    company: string;
    contact: string | null;
    order: string;
    gross: string;
    commission: string;
    net: string;
    status: string;
    reference: string | null;
    paid_at: string | null;
    company_email: string | null;
    company_phone: string | null;
    buyer: string;
    order_completed_at: string | null;
    created_at: string | null;
};

export default function PayoutsIndex({
    payouts,
    filters,
}: {
    payouts: Paginated<Row>;
    filters: { status: string };
}) {
    const [references, setReferences] = useState<Record<number, string>>({});
    const [viewing, setViewing] = useState<Row | null>(null);

    const markPaid = (payout: Row, onSuccess?: () => void) =>
        router.post(
            payoutRoutes.paid.url(payout.id),
            { reference: references[payout.id] },
            { preserveScroll: true, onSuccess },
        );

    return (
        <>
            <Head title="Payouts" />
            <div className="p-4">
                <Heading
                    title="Payouts"
                    description="Pay sellers by bank transfer or Mobile Money, then record the reference here."
                />
                <nav aria-label="Filter by status" className="mb-4 flex gap-2">
                    {['pending', 'paid'].map((status) => (
                        <Link
                            key={status}
                            href={payoutRoutes.index({ query: { status } })}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm capitalize hover:bg-accent',
                                filters.status === status &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            aria-current={
                                filters.status === status ? 'page' : undefined
                            }
                        >
                            {status}
                        </Link>
                    ))}
                </nav>
                {payouts.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        Nothing here.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Seller</TableHead>
                                    <TableHead>Order</TableHead>
                                    <TableHead className="text-right">
                                        Gross
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Commission
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Net to pay
                                    </TableHead>
                                    <TableHead>
                                        {filters.status === 'pending'
                                            ? 'Record payment'
                                            : 'Paid'}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Details
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payouts.data.map((payout) => (
                                    <TableRow
                                        key={payout.id}
                                        className="cursor-pointer"
                                        onClick={() => setViewing(payout)}
                                    >
                                        <TableCell>
                                            <div className="font-medium">
                                                {payout.company}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {payout.contact}
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {payout.order}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {payout.gross}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {payout.commission}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {payout.net}
                                        </TableCell>
                                        <TableCell>
                                            {payout.status === 'pending' ? (
                                                <div
                                                    className="flex gap-2"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <Input
                                                        aria-label={`Transfer reference for ${payout.company}`}
                                                        placeholder="Reference"
                                                        value={
                                                            references[
                                                                payout.id
                                                            ] ?? ''
                                                        }
                                                        onChange={(event) =>
                                                            setReferences({
                                                                ...references,
                                                                [payout.id]:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                        className="w-36"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        disabled={
                                                            !references[
                                                                payout.id
                                                            ]
                                                        }
                                                        onClick={() =>
                                                            markPaid(payout)
                                                        }
                                                    >
                                                        Mark paid
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-sm">
                                                    {formatDate(payout.paid_at)}{' '}
                                                    · {payout.reference}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="icon"
                                                variant="outline"
                                                title="View details"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setViewing(payout);
                                                }}
                                            >
                                                <Eye className="h-4 w-4" />
                                                <span className="sr-only">
                                                    View payout for{' '}
                                                    {payout.order}
                                                </span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination page={payouts} />
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={`Payout to ${viewing?.company ?? ''}`}
                description={viewing ? `Order ${viewing.order}` : undefined}
                rows={
                    viewing
                        ? [
                              {
                                  label: 'Status',
                                  value: (
                                      <StatusBadge status={viewing.status} />
                                  ),
                              },
                              { label: 'Seller', value: viewing.company },
                              {
                                  label: 'Seller email',
                                  value: viewing.company_email,
                              },
                              {
                                  label: 'Seller phone',
                                  value: viewing.company_phone,
                              },
                              { label: 'Buyer', value: viewing.buyer },
                              {
                                  label: 'Order completed',
                                  value: formatDate(viewing.order_completed_at),
                              },
                              { label: 'Gross', value: viewing.gross },
                              {
                                  label: 'Commission',
                                  value: viewing.commission,
                              },
                              {
                                  label: 'Net to pay',
                                  value: <strong>{viewing.net}</strong>,
                              },
                              {
                                  label: 'Recorded',
                                  value: formatDate(viewing.created_at),
                              },
                              {
                                  label: 'Paid',
                                  value: viewing.paid_at
                                      ? `${formatDate(viewing.paid_at)} · ${viewing.reference}`
                                      : null,
                              },
                          ]
                        : []
                }
            >
                {viewing?.status === 'pending' && (
                    <div className="flex gap-2">
                        <Input
                            aria-label="Transfer reference"
                            placeholder="Transfer reference"
                            value={references[viewing.id] ?? ''}
                            onChange={(event) =>
                                setReferences({
                                    ...references,
                                    [viewing.id]: event.target.value,
                                })
                            }
                        />
                        <Button
                            disabled={!references[viewing.id]}
                            onClick={() =>
                                markPaid(viewing, () => setViewing(null))
                            }
                        >
                            Mark paid
                        </Button>
                    </div>
                )}
            </DetailDialog>
        </>
    );
}

PayoutsIndex.layout = {
    breadcrumbs: [{ title: 'Payouts', href: payoutRoutes.index() }],
};
