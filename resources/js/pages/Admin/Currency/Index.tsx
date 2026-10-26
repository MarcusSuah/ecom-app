import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import currencyRoutes from '@/routes/admin/currency';

type Rate = {
    quote: string;
    shared: { rate: number; source: string; fetched_at: string } | null;
    override: { rate: number; set_at: string } | null;
};

type Change = {
    id: number;
    quote: string;
    scope: string;
    old_rate: number | null;
    new_rate: number | null;
    source: string;
    changed_by: string | null;
    changed_at: string;
};

const sourceLabel = (source: string) =>
    ({
        manual: 'Set by hand',
        reset: 'Back to the daily rate',
        fixed: 'Daily rate (fixed)',
        open_er_api: 'Daily rate (open.er-api.com)',
    })[source] ?? source;

export default function CurrencyIndex({
    base,
    rates,
    history,
}: {
    base: string;
    rates: Rate[];
    history: Change[];
}) {
    return (
        <>
            <Head title="Exchange rates" />
            <div className="max-w-3xl p-4">
                <Heading
                    title="Exchange rates"
                    description="The rate in use converts prices for visitors and reports. Rates can change at any time: the daily rate is fetched every day, and a rate you set here replaces it for this marketplace until you go back to the daily rate. Every change is kept below. Orders are always charged in their own currency."
                />
                <ul className="space-y-4">
                    {rates.map((rate) => {
                        const inUse = rate.override?.rate ?? rate.shared?.rate;

                        return (
                            <li
                                key={rate.quote}
                                className="space-y-4 rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <h3 className="font-semibold">
                                        {base} → {rate.quote}
                                    </h3>
                                    <p className="text-sm">
                                        In use:{' '}
                                        <span className="font-medium tabular-nums">
                                            {inUse !== undefined
                                                ? `1 ${base} = ${inUse.toLocaleString()} ${rate.quote}`
                                                : 'no rate yet'}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            (
                                            {rate.override
                                                ? 'your override'
                                                : 'daily rate'}
                                            )
                                        </span>
                                    </p>
                                </div>
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Daily rate
                                        </dt>
                                        <dd>
                                            {rate.shared
                                                ? `${rate.shared.rate.toLocaleString()} (${rate.shared.source}, ${formatDate(rate.shared.fetched_at)})`
                                                : 'Not fetched yet. Run php artisan currency:fetch-rates.'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Override
                                        </dt>
                                        <dd>
                                            {rate.override
                                                ? `${rate.override.rate.toLocaleString()} since ${formatDate(rate.override.set_at)}`
                                                : 'None'}
                                        </dd>
                                    </div>
                                </dl>
                                <Form
                                    {...currencyRoutes.update.form()}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-wrap items-end gap-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="quote"
                                                value={rate.quote}
                                            />
                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor={`rate-${rate.quote}`}
                                                >
                                                    {rate.quote} per 1 {base}
                                                </Label>
                                                <Input
                                                    id={`rate-${rate.quote}`}
                                                    name="rate"
                                                    inputMode="decimal"
                                                    // Starts from the rate in use, so a small change is quick.
                                                    defaultValue={inUse ?? ''}
                                                    className="w-40"
                                                    aria-invalid={Boolean(
                                                        errors.rate,
                                                    )}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save rate
                                            </Button>
                                            {rate.override && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.delete(
                                                            currencyRoutes.destroy.url(
                                                                rate.quote,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Use the daily rate
                                                </Button>
                                            )}
                                            <InputError
                                                message={
                                                    errors.rate ?? errors.quote
                                                }
                                                className="w-full"
                                            />
                                        </>
                                    )}
                                </Form>
                            </li>
                        );
                    })}
                </ul>

                <section
                    aria-labelledby="rate-history"
                    className="mt-8 space-y-3"
                >
                    <h2 id="rate-history" className="text-lg font-semibold">
                        Rate history
                    </h2>
                    {history.length === 0 ? (
                        <p className="rounded-md border p-6 text-sm text-muted-foreground">
                            No changes recorded yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            When
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Currency
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Applies to
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            From
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            To
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            How
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            By
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {history.map((change) => (
                                        <tr key={change.id}>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {new Date(
                                                    change.changed_at,
                                                ).toLocaleString(undefined, {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })}
                                            </td>
                                            <td className="px-3 py-2">
                                                {base} → {change.quote}
                                            </td>
                                            <td className="px-3 py-2">
                                                {change.scope}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {change.old_rate?.toLocaleString() ??
                                                    '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {change.new_rate?.toLocaleString() ??
                                                    '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {sourceLabel(change.source)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {change.changed_by ?? 'System'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

CurrencyIndex.layout = {
    breadcrumbs: [{ title: 'Exchange rates', href: currencyRoutes.index() }],
};
