import { cn } from '@/lib/utils';

/**
 * A converted price in the visitor's chosen currency, e.g. "≈ USD 10.35".
 * Used for plan prices, which are billed in the plan's own currency.
 */
export function ApproxPrice({
    value,
    className,
}: {
    value: string | null | undefined;
    className?: string;
}) {
    if (!value) {
        return null;
    }

    return (
        <span
            className={cn('text-xs text-muted-foreground', className)}
            title="Approximate, at today's rate. You pay in the listed currency."
        >
            {value} <span className="sr-only">(approximate)</span>
            <span aria-hidden="true">approx.</span>
        </span>
    );
}

/**
 * The listing's own price under a converted one, e.g. "Charged RWF 15,000":
 * prices follow the currency switcher, but buyers pay in the seller's currency.
 */
export function ListedPrice({
    value,
    className,
}: {
    value: string | null | undefined;
    className?: string;
}) {
    if (!value) {
        return null;
    }

    return (
        <span
            className={cn('text-xs text-muted-foreground', className)}
            title="Converted at today's rate. You pay in the seller's currency."
        >
            Charged {value}
        </span>
    );
}
