import { useEffect, useRef } from 'react';

/** Wait after the last keystroke before searching. */
export const LIVE_SEARCH_DELAY_MS = 350;

/**
 * Runs `search` shortly after the visitor stops typing, so results follow
 * the text without pressing Enter. It does not run for the first value
 * (the one the page was loaded with).
 */
export function useLiveSearch(
    value: string,
    search: (value: string) => void,
    delay = LIVE_SEARCH_DELAY_MS,
): void {
    const first = useRef(true);
    const latest = useRef(search);

    useEffect(() => {
        latest.current = search;
    });

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = window.setTimeout(() => latest.current(value), delay);

        return () => window.clearTimeout(timer);
    }, [value, delay]);
}
