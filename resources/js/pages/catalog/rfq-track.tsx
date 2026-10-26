import { Head, Link, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
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
import { login, register } from '@/routes';
import { show as rfqShow } from '@/routes/rfqs';
import { show as supplierShow } from '@/routes/suppliers';
import type { SharedData } from '@/types';

type QuoteRow = {
    id: number;
    status: string;
    formatted_unit_price: string | null;
    formatted_total: string | null;
    lead_time: string;
    sample_terms: string | null;
    valid_until: string;
    message: string | null;
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
        deliver_city: string;
        needed_by: string | null;
        notes: string | null;
        status: string;
        expires_at: string;
        recipients_count: number;
    };
    quotes: QuoteRow[];
    guest: { name: string | null; email: string | null; claimed: boolean };
};

/**
 * A guest's request and its quotes, opened from the emailed link. Read-only:
 * accepting a quote and ordering need an account with the same email.
 */
export default function RfqTrack({ rfq, quotes, guest }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title={rfq.title} />
            <div className="space-y-6">
                <header className="space-y-2">
                    <p className="font-mono text-xs tracking-wider text-muted-foreground uppercase">
                        Your request for quotes
                    </p>
                    <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold">
                        {rfq.title}
                        <StatusBadge status={rfq.status} />
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {rfq.quantity.toLocaleString()} {rfq.unit} ·{' '}
                        {rfq.category} · deliver to {rfq.deliver_city}
                        {rfq.needed_by
                            ? ` by ${formatDate(rfq.needed_by)}`
                            : ''}{' '}
                        · open until {formatDate(rfq.expires_at)}
                    </p>
                    {rfq.notes && (
                        <p className="max-w-2xl text-sm whitespace-pre-line">
                            {rfq.notes}
                        </p>
                    )}
                </header>

                <section
                    aria-labelledby="account-heading"
                    className="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-muted/50 p-4"
                >
                    <div className="flex items-start gap-3">
                        <Mail
                            className="mt-0.5 size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <div className="text-sm">
                            <h2 id="account-heading" className="font-semibold">
                                {guest.claimed
                                    ? 'This request is in your account'
                                    : 'Ready to choose a supplier?'}
                            </h2>
                            <p className="text-muted-foreground">
                                {guest.claimed
                                    ? 'Open it from your account to accept a quote, chat with suppliers and order.'
                                    : `Sign in or create an account with ${guest.email ?? 'your email'} to accept a quote, chat and order. The request joins your account automatically.`}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {guest.claimed || auth.user ? (
                            <Button asChild>
                                <Link
                                    href={auth.user ? rfqShow(rfq.id) : login()}
                                >
                                    Open in my account
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="outline">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </section>

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
                                ? 'No verified supplier matches this request yet. We keep it open and email you when a quote arrives.'
                                : 'No quotes yet. Suppliers have been told; we email you as soon as one replies.'}
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
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {quotes.map((quote) => (
                                        <TableRow key={quote.id}>
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
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
