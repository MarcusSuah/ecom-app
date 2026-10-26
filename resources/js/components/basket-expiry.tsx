import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { inquiryList } from '@/lib/inquiry-list';
import type { SharedData } from '@/types';

const CHECK_EVERY_MS = 30 * 1000;

/**
 * Empties the local order and the inquiry list after 10 minutes without a
 * change, then refreshes the page so the header shows 0. The server drops
 * the cart items itself (ManageCart::expireIfIdle); this only refreshes.
 */
export function BasketExpiry() {
    const { cartExpiresAt } = usePage<SharedData>().props;

    useEffect(() => {
        if (!cartExpiresAt) {
            return;
        }

        const wait = Math.max(
            1000,
            new Date(cartExpiresAt).getTime() - Date.now() + 1000,
        );
        const timer = window.setTimeout(() => router.reload(), wait);

        return () => window.clearTimeout(timer);
    }, [cartExpiresAt]);

    useEffect(() => {
        const check = () => {
            if (inquiryList.expireIfIdle()) {
                router.reload();
            }
        };

        check();
        const timer = window.setInterval(check, CHECK_EVERY_MS);

        return () => window.clearInterval(timer);
    }, []);

    return null;
}
