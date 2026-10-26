import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-2 pt-4 text-sm"
        >
            <p className="text-muted-foreground">
                Showing {page.from}–{page.to} of {page.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'rounded-md border px-3 py-1 hover:bg-accent',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            aria-current={link.active ? 'page' : undefined}
                        >
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        </Link>
                    ) : (
                        <span
                            key={index}
                            className="rounded-md border px-3 py-1 text-muted-foreground opacity-50"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </nav>
    );
}
