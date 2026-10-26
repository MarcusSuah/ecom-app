import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    pending:
        'border-amber-500/40 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    approved:
        'border-emerald-600/40 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    active: 'border-emerald-600/40 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    paid: 'border-emerald-600/40 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    trialing:
        'border-sky-500/40 bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    rejected:
        'border-red-500/40 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    failed: 'border-red-500/40 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    past_due:
        'border-red-500/40 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    suspended:
        'border-red-500/40 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
};

/**
 * A coloured label for any status value (verification, subscription,
 * invoice, marketplace).
 */
export function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={cn('capitalize', tones[status])}>
            {status.replace('_', ' ')}
        </Badge>
    );
}
