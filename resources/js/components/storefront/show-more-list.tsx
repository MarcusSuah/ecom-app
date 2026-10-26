import { InfiniteScroll } from '@inertiajs/react';
import { useRef } from 'react';
import type { ReactNode } from 'react';

/**
 * A storefront grid that grows with "Show more": the next page of the
 * Inertia::scroll() prop named `data` is appended below, so every listing
 * can be reached from the home page.
 */
export function ShowMoreList({
    data,
    label,
    className,
    children,
}: {
    /** The scroll prop's name, e.g. "products". */
    data: string;
    /** What the button shows more of, e.g. "products". */
    label: string;
    className: string;
    children: ReactNode;
}) {
    const items = useRef<HTMLUListElement>(null);

    return (
        <InfiniteScroll
            data={data}
            manual
            preserveUrl
            itemsElement={items}
            next={({ loading, fetch, hasMore }) =>
                hasMore && (
                    <div className="mt-5 flex justify-center">
                        <button
                            type="button"
                            className="sf-btn"
                            onClick={fetch}
                            disabled={loading}
                        >
                            {loading ? 'Loading…' : `Show more ${label}`}
                        </button>
                    </div>
                )
            }
        >
            <ul ref={items} className={className}>
                {children}
            </ul>
        </InfiniteScroll>
    );
}
