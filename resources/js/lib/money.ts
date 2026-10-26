import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { SharedData } from '@/types';

/** Same output as App\Support\Money::format, e.g. "RWF 1,500" or "USD 10.35". */
export function formatMinor(
    amount: number,
    currency: string,
    subunits: number,
): string {
    const decimals = subunits === 1 ? 0 : Math.round(Math.log10(subunits));

    return `${currency.toUpperCase()} ${(amount / subunits).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        },
    )}`;
}

/**
 * Formats stored minor units in the visitor's chosen currency (the
 * switcher in the header), converted the way the server converts. Amounts
 * without a known rate stay in their own currency. `listed` gives the
 * listing's own price, which is what the buyer is charged, when it differs.
 */
export function useMoney() {
    const { currency } = usePage<SharedData>().props;
    const display = currency?.display ?? null;

    const format = useCallback(
        (amount: number, from: string): string => {
            const subunits = currency?.subunits ?? {};
            const rate = display ? currency?.rates?.[from.toUpperCase()] : null;

            if (!display || rate == null || display === from.toUpperCase()) {
                return formatMinor(
                    amount,
                    from,
                    subunits[from.toUpperCase()] ?? 100,
                );
            }

            const major =
                (amount / (subunits[from.toUpperCase()] ?? 100)) * rate;
            const target = subunits[display] ?? 100;

            return formatMinor(Math.round(major * target), display, target);
        },
        [currency, display],
    );

    const listed = useCallback(
        (amount: number, from: string): string | null => {
            const rate = display ? currency?.rates?.[from.toUpperCase()] : null;

            if (!display || rate == null || display === from.toUpperCase()) {
                return null;
            }

            return formatMinor(
                amount,
                from,
                currency?.subunits?.[from.toUpperCase()] ?? 100,
            );
        },
        [currency, display],
    );

    return { display, format, listed };
}
