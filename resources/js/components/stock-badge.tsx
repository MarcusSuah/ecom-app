import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    in_stock:
        'border-emerald-600/40 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    low_stock:
        'border-amber-500/50 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    out_of_stock:
        'border-red-500/40 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    made_to_order:
        'border-sky-500/40 bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
};

/**
 * Stock state from the server (StockStatus) and, once anything sold, how
 * many units have been sold.
 */
export function StockBadge({
    status,
    label,
    sold,
    className,
}: {
    status: string;
    label: string;
    sold?: number;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex flex-wrap items-center gap-1',
                className,
            )}
        >
            <Badge variant="outline" className={tones[status] ?? ''}>
                {label}
            </Badge>
            {sold !== undefined && sold > 0 && (
                <Badge
                    variant="outline"
                    className="border-violet-500/40 bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300"
                >
                    {sold.toLocaleString()} sold
                </Badge>
            )}
        </span>
    );
}
