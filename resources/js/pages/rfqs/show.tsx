import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { document as quoteDocument } from '@/routes/quotes';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { OpenChatButton } from '@/components/open-chat-button';
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
import { VerifiedBadge } from '@/components/verified-badge';
import { formatDate } from '@/lib/format';
import { show as orderShow } from '@/routes/orders';
import { show as productShow } from '@/routes/products';
import rfqRoutes from '@/routes/rfqs';
import { show as supplierShow } from '@/routes/suppliers';

type QuoteRow = {
    id: number;
    status: string;
    currency: string;
    formatted_unit_price: string | null;
    formatted_total: string | null;
    lead_time: string;
    sample_terms: string | null;
    valid_until: string;
    message: string | null;
    order_id: number | null;
    tiers: { min_qty: number; formatted_unit_price: string }[];
    supplier: {
        name: string;
        slug: string;
        is_verified: boolean;
        response_time: string | null;
    };
};

type Props = {
    rfq: {
        id: number;
        title: string;
        quantity: number;
        unit: string;
        category: string;
        product: { name: string; slug: string } | null;
        deliver_city: string;
        needed_by: string | null;
        notes: string | null;
        status: string;
        expires_at: string;
        recipients_count: number;
    };
    quotes: QuoteRow[];
};

export default function RfqComparison({ rfq, quotes }: Props) {
    const [accepting, setAccepting] = useState<QuoteRow | null>(null);
    const [closing, setClosing] = useState(false);
    const errors = usePage().props.errors as Record<string, string | undefined>;
    const isOpen = rfq.status === 'open';

    return (
        <>
            <Head title={rfq.title} />
            <div className="space-y-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="flex flex-wrap items-center gap-2 text-xl font-semibold">
                            {rfq.title}
                            <StatusBadge status={rfq.status} />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {rfq.quantity.toLocaleString()} {rfq.unit} ·{' '}
                            {rfq.category} · deliver to {rfq.deliver_city}
                            {rfq.needed_by
                                ? ` by ${formatDate(rfq.needed_by)}`
                                : ''}{' '}
                            · closes {formatDate(rfq.expires_at)}
                        </p>
                        {rfq.product && (
                            <p className="text-sm">
                                Started from{' '}
                                <Link
                                    href={productShow(rfq.product.slug)}
                                    className="text-primary hover:underline"
                                >
                                    {rfq.product.name}
                                </Link>
                            </p>
                        )}
                        {rfq.notes && (
                            <p className="max-w-2xl text-sm whitespace-pre-line">
                                {rfq.notes}
                            </p>
                        )}
                    </div>
                    {isOpen && (
                        <Button
                            variant="outline"
                            onClick={() => setClosing(true)}
                        >
                            Close request
                        </Button>
                    )}
                </header>
                <InputError message={errors.quote ?? errors.rfq} />

                <section aria-labelledby="quotes-heading" className="space-y-3">
                    <h2 id="quotes-heading" className="text-lg font-semibold">
                        Quotes ({quotes.length}){' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            from {rfq.recipients_count} suppliers asked
                        </span>
                    </h2>
                    {quotes.length === 0 ? (
                        <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                            {rfq.recipients_count === 0
                                ? 'No verified supplier matched this request yet.'
                                : 'No quotes yet. Suppliers have been notified.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <caption className="sr-only">
                                    Quotes compared at your quantity of{' '}
                                    {rfq.quantity}
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Supplier</TableHead>
                                        <TableHead className="text-right">
                                            Unit price at{' '}
                                            {rfq.quantity.toLocaleString()}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                        <TableHead>Lead time</TableHead>
                                        <TableHead>Samples</TableHead>
                                        <TableHead>Responds</TableHead>
                                        <TableHead>Valid until</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {quotes.map((quote) => (
                                        <TableRow
                                            key={quote.id}
                                            className={
                                                quote.status === 'accepted'
                                                    ? 'bg-emerald-50 dark:bg-emerald-950/40'
                                                    : undefined
                                            }
                                        >
                                            <TableCell>
                                                <Link
                                                    href={supplierShow(
                                                        quote.supplier.slug,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {quote.supplier.name}
                                                </Link>
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    <VerifiedBadge
                                                        verified={
                                                            quote.supplier
                                                                .is_verified
                                                        }
                                                    />
                                                    {quote.status !==
                                                        'submitted' && (
                                                        <StatusBadge
                                                            status={
                                                                quote.status
                                                            }
                                                        />
                                                    )}
                                                </div>
                                                <details className="mt-1 text-xs">
                                                    <summary className="cursor-pointer text-muted-foreground">
                                                        All tiers
                                                    </summary>
                                                    <ul>
                                                        {quote.tiers.map(
                                                            (tier) => (
                                                                <li
                                                                    key={
                                                                        tier.min_qty
                                                                    }
                                                                >
                                                                    {tier.min_qty.toLocaleString()}
                                                                    + :{' '}
                                                                    {
                                                                        tier.formatted_unit_price
                                                                    }
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                    {quote.message && (
                                                        <p className="mt-1 whitespace-pre-line">
                                                            {quote.message}
                                                        </p>
                                                    )}
                                                </details>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {quote.formatted_unit_price ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {quote.formatted_total ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {quote.lead_time}
                                            </TableCell>
                                            <TableCell className="max-w-48 text-sm">
                                                {quote.sample_terms ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {quote.supplier.response_time ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(quote.valid_until)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <OpenChatButton
                                                        company={
                                                            quote.supplier.slug
                                                        }
                                                        rfqId={rfq.id}
                                                        label="Chat"
                                                    />
                                                    {isOpen &&
                                                        quote.status ===
                                                            'submitted' && (
                                                            <Button
                                                                onClick={() =>
                                                                    setAccepting(
                                                                        quote,
                                                                    )
                                                                }
                                                            >
                                                                Accept
                                                            </Button>
                                                        )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={quoteDocument.url(
                                                                quote.id,
                                                            )}
                                                            aria-label={`Download the quote from ${quote.supplier.name} as PDF`}
                                                        >
                                                            <FileDown aria-hidden="true" />
                                                            PDF
                                                        </a>
                                                    </Button>
                                                    {quote.status ===
                                                        'accepted' &&
                                                        (quote.order_id ? (
                                                            <Button
                                                                asChild
                                                                variant="outline"
                                                            >
                                                                <Link
                                                                    href={orderShow(
                                                                        quote.order_id,
                                                                    )}
                                                                >
                                                                    View order
                                                                </Link>
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                onClick={() =>
                                                                    router.post(
                                                                        rfqRoutes.quotes.order.url(
                                                                            {
                                                                                rfq: rfq.id,
                                                                                quote: quote.id,
                                                                            },
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                Place order
                                                            </Button>
                                                        ))}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </section>
            </div>

            <ConfirmDialog
                open={accepting !== null}
                onOpenChange={(open) => !open && setAccepting(null)}
                title={`Accept ${accepting?.supplier.name}'s quote?`}
                description="The request is awarded to this supplier and the other quotes are declined. This cannot be undone."
                confirmLabel="Accept quote"
                onConfirm={() => {
                    if (accepting) {
                        router.post(
                            rfqRoutes.quotes.accept.url({
                                rfq: rfq.id,
                                quote: accepting.id,
                            }),
                            {},
                            { preserveScroll: true },
                        );
                    }
                    setAccepting(null);
                }}
            />
            <ConfirmDialog
                open={closing}
                onOpenChange={setClosing}
                title="Close this request?"
                description="Suppliers can no longer quote, and the quotes you received are declined."
                confirmLabel="Close request"
                variant="destructive"
                onConfirm={() => {
                    router.post(
                        rfqRoutes.close.url(rfq.id),
                        {},
                        { preserveScroll: true },
                    );
                    setClosing(false);
                }}
            />
        </>
    );
}

RfqComparison.layout = {
    breadcrumbs: [{ title: 'My requests', href: rfqRoutes.index() }],
};
