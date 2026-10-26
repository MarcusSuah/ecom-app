import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type ReportCurrency = { code: string | null; options: string[] };

export type RateUsed = { label: string; source: string; since: string };

/**
 * Show the report in each order's own currency, or converted into one
 * currency (RWF or USD) at the rate in use.
 */
export function ReportCurrencyPicker({
    value,
    url,
    query,
    allowOwn = true,
}: {
    value: ReportCurrency;
    url: string;
    query: Record<string, string | number>;
    /** Offer "Each order's currency" (not for platform totals, always converted). */
    allowOwn?: boolean;
}) {
    const options = [
        ...(allowOwn ? [{ code: null, label: 'As charged' }] : []),
        ...value.options.map((code) => ({ code, label: code })),
    ];

    return (
        <div
            role="group"
            aria-label="Report currency"
            className="inline-flex rounded-md border p-0.5"
        >
            {options.map((option) => (
                <button
                    key={option.code ?? 'own'}
                    type="button"
                    aria-pressed={value.code === option.code}
                    onClick={() =>
                        router.get(
                            url,
                            {
                                ...query,
                                ...(option.code
                                    ? { currency: option.code }
                                    : {}),
                            },
                            {
                                preserveScroll: true,
                                preserveState: true,
                                replace: true,
                            },
                        )
                    }
                    className={cn(
                        'rounded px-3 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        value.code === option.code &&
                            'bg-primary text-primary-foreground hover:text-primary-foreground',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

/** The exact rates a converted report used, for accountability. */
export function RatesUsed({ rates }: { rates: RateUsed[] }) {
    if (rates.length === 0) {
        return null;
    }

    return (
        <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm">
            <span className="font-medium">Converted at: </span>
            {rates.map((rate, index) => (
                <span key={rate.label}>
                    {index > 0 && '; '}
                    {rate.label} ({rate.source}, since{' '}
                    {new Date(rate.since).toLocaleString(undefined, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    })}
                    )
                </span>
            ))}
            . The full history is under Exchange rates.
        </p>
    );
}
