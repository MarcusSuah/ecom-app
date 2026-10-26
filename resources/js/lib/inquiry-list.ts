import { useSyncExternalStore } from 'react';

/** What the inquiry list keeps per product: enough to price it without a request. */
export type InquiryItem = {
    product_id: number;
    slug: string;
    name: string;
    supplier: string;
    supplier_slug: string;
    unit: string;
    moq: number;
    currency: string;
    /** Price tiers in minor units, lowest quantity first. */
    tiers: { min_qty: number; unit_price: number }[];
    image_url: string | null;
    quantity: number;
    /** Units in stock; null when made to order (no limit). */
    stock?: number | null;
};

/** At least the MOQ, and never more than is in stock. */
function clamp(
    item: Pick<InquiryItem, 'moq' | 'stock'>,
    quantity: number,
): number {
    const atLeastMoq = Math.max(quantity, item.moq);

    return item.stock == null ? atLeastMoq : Math.min(atLeastMoq, item.stock);
}

const KEY = 'storefront:inquiry-list';
const TOUCHED_KEY = 'storefront:inquiry-list:touched';
/** A list left this long without a change is emptied, like the local order. */
export const IDLE_MS = 10 * 60 * 1000;
const listeners = new Set<() => void>();
let cache: InquiryItem[] | null = null;

function read(): InquiryItem[] {
    if (cache !== null) {
        return cache;
    }

    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(KEY) ?? '[]',
        );
        cache = Array.isArray(parsed) ? (parsed as InquiryItem[]) : [];
    } catch {
        cache = [];
    }

    return cache;
}

function write(items: InquiryItem[]): void {
    cache = items;

    try {
        window.localStorage.setItem(KEY, JSON.stringify(items));
        window.localStorage.setItem(TOUCHED_KEY, String(Date.now()));
    } catch {
        // Private mode or full storage: the list lasts for this visit only.
    }

    listeners.forEach((listener) => listener());
}

const EMPTY: InquiryItem[] = [];

/** Unit price for a quantity: the highest tier it reaches (at least the MOQ). */
export function tierPrice(
    item: Pick<InquiryItem, 'tiers' | 'moq'>,
    quantity: number,
): number {
    const effective = Math.max(quantity, item.moq);
    let price = item.tiers[0]?.unit_price ?? 0;

    for (const tier of item.tiers) {
        if (effective >= tier.min_qty) {
            price = tier.unit_price;
        }
    }

    return price;
}

export const inquiryList = {
    add(item: InquiryItem): void {
        const others = read().filter(
            (existing) => existing.product_id !== item.product_id,
        );
        write([...others, { ...item, quantity: clamp(item, item.quantity) }]);
    },
    setQuantity(productId: number, quantity: number): void {
        write(
            read().map((item) =>
                item.product_id === productId
                    ? { ...item, quantity: clamp(item, quantity) }
                    : item,
            ),
        );
    },
    remove(productId: number): void {
        write(read().filter((item) => item.product_id !== productId));
    },
    /**
     * Empties the list when nothing changed for IDLE_MS; returns whether it did.
     */
    expireIfIdle(): boolean {
        if (read().length === 0) {
            return false;
        }

        let touched = 0;

        try {
            touched = Number(window.localStorage.getItem(TOUCHED_KEY) ?? 0);
        } catch {
            return false;
        }

        if (Date.now() - touched < IDLE_MS) {
            return false;
        }

        write([]);

        return true;
    },
    clear(): void {
        write([]);
    },
};

/**
 * The visitor's inquiry list, kept in this browser (it works for guests
 * too) and shared by every component that shows it.
 */
export function useInquiryList(): InquiryItem[] {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            const onStorage = (event: StorageEvent) => {
                if (event.key === KEY) {
                    cache = null;
                    listener();
                }
            };
            window.addEventListener('storage', onStorage);

            return () => {
                listeners.delete(listener);
                window.removeEventListener('storage', onStorage);
            };
        },
        read,
        () => EMPTY,
    );
}
