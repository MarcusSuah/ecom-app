import { router, usePage } from '@inertiajs/react';
import { update as updateCurrency } from '@/routes/currency';
import type { SharedData } from '@/types';

/**
 * Chooses the currency prices are shown in. Remembered for the session and
 * on the account; charges always stay in the listing's own currency.
 */
export function CurrencySwitcher() {
    const { currency } = usePage<SharedData>().props;

    if (!currency || currency.options.length < 2) {
        return null;
    }

    return (
        <label className="flex items-center gap-1.5 text-sm">
            <span className="sr-only">Show prices in</span>
            <select
                value={currency.display}
                onChange={(event) =>
                    router.put(
                        updateCurrency.url(),
                        { currency: event.target.value },
                        { preserveScroll: true, preserveState: true },
                    )
                }
                className="h-8 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                {currency.options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        </label>
    );
}
