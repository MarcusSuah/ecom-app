import { router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

type PageLinks = {
    from: number | null;
    to: number | null;
    total: number;
    first_page_url: string | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    last_page_url: string | null;
};

/**
 * "Showing 1 to 15 of 40" with first / previous / next / last buttons, as on
 * the user management table.
 */
export function DataTableFooter({
    page,
    noun,
}: {
    page: PageLinks;
    noun: string;
}) {
    const go = (url: string | null) =>
        url &&
        router.get(url, {}, { preserveState: true, preserveScroll: true });

    const buttons = [
        {
            label: 'Go to first page',
            url: page.first_page_url,
            disabled: !page.prev_page_url,
            icon: ChevronsLeft,
            wide: true,
        },
        {
            label: 'Go to previous page',
            url: page.prev_page_url,
            disabled: !page.prev_page_url,
            icon: ChevronLeft,
            wide: false,
        },
        {
            label: 'Go to next page',
            url: page.next_page_url,
            disabled: !page.next_page_url,
            icon: ChevronRight,
            wide: false,
        },
        {
            label: 'Go to last page',
            url: page.last_page_url,
            disabled: !page.next_page_url,
            icon: ChevronsRight,
            wide: true,
        },
    ];

    return (
        <div className="mt-6 flex items-center justify-between px-2">
            <div className="flex-1 text-sm text-muted-foreground">
                Showing {page.from ?? 0} to {page.to ?? 0} of {page.total}{' '}
                {noun}.
            </div>
            <div className="flex items-center space-x-2">
                {buttons.map((button) => (
                    <Button
                        key={button.label}
                        variant="outline"
                        size="icon"
                        className={
                            button.wide ? 'hidden size-8 lg:flex' : 'size-8'
                        }
                        onClick={() => go(button.url)}
                        disabled={button.disabled}
                    >
                        <span className="sr-only">{button.label}</span>
                        <button.icon className="h-4 w-4" />
                    </Button>
                ))}
            </div>
        </div>
    );
}
