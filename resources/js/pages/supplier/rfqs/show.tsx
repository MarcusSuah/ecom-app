import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { document as quoteDocument } from '@/routes/quotes';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { type EditableTier, TierEditor } from '@/components/tier-editor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { show as productShow } from '@/routes/products';
import supplierRfqs from '@/routes/supplier/rfqs';

type QuoteData = {
    id: number;
    status: string;
    currency: string;
    lead_time_min_days: number;
    lead_time_max_days: number;
    sample_terms: string;
    valid_until: string;
    message: string;
    tiers: EditableTier[];
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
        is_open: boolean;
        expires_at: string;
    };
    declined: boolean;
    quote: QuoteData | null;
    defaults: { currency: string; valid_until: string };
    currencies: string[];
    maxTiers: number;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function SupplierRfq({
    rfq,
    declined,
    quote,
    defaults,
    currencies,
    maxTiers,
}: Props) {
    const [declining, setDeclining] = useState(false);
    const pageErrors = usePage().props.errors as Record<
        string,
        string | undefined
    >;
    const form = useForm({
        currency: quote?.currency ?? defaults.currency,
        lead_time_min_days: String(quote?.lead_time_min_days ?? 7),
        lead_time_max_days: String(quote?.lead_time_max_days ?? 14),
        sample_terms: quote?.sample_terms ?? '',
        valid_until: quote?.valid_until ?? defaults.valid_until,
        message: quote?.message ?? '',
        tiers: quote?.tiers.length
            ? quote.tiers
            : ([
                  { min_qty: String(rfq.quantity), unit_price: '' },
              ] as EditableTier[]),
    });
    const { data, setData, errors, processing } = form;
    const canEdit =
        rfq.is_open &&
        (quote === null ||
            quote.status === 'submitted' ||
            quote.status === 'withdrawn');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(supplierRfqs.quote.url(rfq.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title={rfq.title} />
            <div className="mx-auto grid w-full max-w-5xl gap-8 p-4 lg:grid-cols-[1fr_20rem]">
                <section aria-labelledby="quote-heading" className="space-y-6">
                    <header className="space-y-1">
                        <h1 className="flex flex-wrap items-center gap-2 text-xl font-semibold">
                            {rfq.title}
                            {rfq.status !== 'open' && (
                                <StatusBadge status={rfq.status} />
                            )}
                            {quote && <StatusBadge status={quote.status} />}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {rfq.quantity.toLocaleString()} {rfq.unit} ·{' '}
                            {rfq.category} · deliver to {rfq.deliver_city}
                            {rfq.needed_by
                                ? ` by ${formatDate(rfq.needed_by)}`
                                : ''}
                        </p>
                    </header>
                    <InputError
                        message={
                            pageErrors.rfq ??
                            pageErrors.quote ??
                            pageErrors.reason
                        }
                    />

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2
                            id="quote-heading"
                            className="text-lg font-semibold"
                        >
                            {quote ? 'Your quote' : 'Send a quote'}
                        </h2>
                        {quote && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={quoteDocument.url(quote.id)}>
                                    <FileDown aria-hidden="true" />
                                    Quote PDF
                                </a>
                            </Button>
                        )}
                    </div>
                    {!canEdit && (
                        <p className="text-sm text-muted-foreground">
                            This request is closed; your quote can no longer be
                            changed.
                        </p>
                    )}

                    <form onSubmit={submit} className="space-y-6" noValidate>
                        <fieldset disabled={!canEdit} className="space-y-6">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-end justify-between gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        Your first tier is your minimum order
                                        and must start at or below{' '}
                                        {rfq.quantity.toLocaleString()}.
                                    </p>
                                    <div className="grid gap-1">
                                        <Label htmlFor="currency">
                                            Currency
                                        </Label>
                                        <select
                                            id="currency"
                                            className={selectClass}
                                            value={data.currency}
                                            onChange={(event) =>
                                                setData(
                                                    'currency',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {currencies.map((currency) => (
                                                <option key={currency}>
                                                    {currency}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <TierEditor
                                    tiers={data.tiers}
                                    onChange={(tiers) =>
                                        setData('tiers', tiers)
                                    }
                                    errors={
                                        errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    }
                                    currency={data.currency}
                                    maxTiers={maxTiers}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="lead_time_min_days">
                                        Lead time from (days)
                                    </Label>
                                    <Input
                                        id="lead_time_min_days"
                                        type="number"
                                        min={0}
                                        value={data.lead_time_min_days}
                                        onChange={(event) =>
                                            setData(
                                                'lead_time_min_days',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.lead_time_min_days}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="lead_time_max_days">
                                        Lead time to (days)
                                    </Label>
                                    <Input
                                        id="lead_time_max_days"
                                        type="number"
                                        min={0}
                                        value={data.lead_time_max_days}
                                        onChange={(event) =>
                                            setData(
                                                'lead_time_max_days',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.lead_time_max_days,
                                        )}
                                    />
                                    <InputError
                                        message={errors.lead_time_max_days}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="valid_until">
                                        Valid until
                                    </Label>
                                    <Input
                                        id="valid_until"
                                        type="date"
                                        value={data.valid_until}
                                        onChange={(event) =>
                                            setData(
                                                'valid_until',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.valid_until,
                                        )}
                                    />
                                    <InputError message={errors.valid_until} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sample_terms">
                                    Sample terms
                                </Label>
                                <Input
                                    id="sample_terms"
                                    value={data.sample_terms}
                                    onChange={(event) =>
                                        setData(
                                            'sample_terms',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. 2 free samples, buyer pays shipping"
                                />
                                <InputError message={errors.sample_terms} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="message">
                                    Message to the buyer
                                </Label>
                                <Textarea
                                    id="message"
                                    rows={4}
                                    value={data.message}
                                    onChange={(event) =>
                                        setData('message', event.target.value)
                                    }
                                />
                                <InputError message={errors.message} />
                            </div>
                        </fieldset>
                        {canEdit && (
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Sending…'
                                        : quote?.status === 'submitted'
                                          ? 'Update quote'
                                          : 'Send quote'}
                                </Button>
                                {quote?.status === 'submitted' && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                supplierRfqs.quote.withdraw.url(
                                                    rfq.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Withdraw quote
                                    </Button>
                                )}
                                {quote?.status !== 'submitted' && !declined && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setDeclining(true)}
                                    >
                                        Decline request
                                    </Button>
                                )}
                            </div>
                        )}
                    </form>
                </section>

                <aside className="space-y-3 rounded-lg border p-4 text-sm">
                    <h2 className="font-semibold">Request details</h2>
                    <dl className="space-y-2">
                        <div>
                            <dt className="text-muted-foreground">Closes</dt>
                            <dd>{formatDate(rfq.expires_at)}</dd>
                        </div>
                        {rfq.product && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Started from your product
                                </dt>
                                <dd>
                                    <Link
                                        href={productShow(rfq.product.slug)}
                                        className="text-primary hover:underline"
                                    >
                                        {rfq.product.name}
                                    </Link>
                                </dd>
                            </div>
                        )}
                        {rfq.notes && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Buyer's notes
                                </dt>
                                <dd className="whitespace-pre-line">
                                    {rfq.notes}
                                </dd>
                            </div>
                        )}
                    </dl>
                    {declined && (
                        <p className="rounded-md border p-2 text-muted-foreground">
                            You declined this request. Sending a quote reopens
                            it.
                        </p>
                    )}
                </aside>
            </div>

            <Dialog open={declining} onOpenChange={setDeclining}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Decline this request?</DialogTitle>
                        <DialogDescription>
                            The buyer will not see a quote from you.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...supplierRfqs.decline.form(rfq.id)}
                        className="space-y-4"
                    >
                        {({
                            processing: declineProcessing,
                            errors: declineErrors,
                        }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Reason</Label>
                                    <Textarea
                                        id="reason"
                                        name="reason"
                                        rows={3}
                                        required
                                        placeholder="e.g. Quantity too small, out of stock"
                                    />
                                    <InputError
                                        message={declineErrors.reason}
                                    />
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={declineProcessing}
                                    >
                                        Decline
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

SupplierRfq.layout = {
    breadcrumbs: [
        { title: 'Requests for quotation', href: supplierRfqs.index() },
    ],
};
