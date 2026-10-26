import { Badge } from '@/components/ui/badge';

const STYLES: Record<string, string> = {
    pending:
        'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400',
    active: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-400',
    inactive:
        'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-400',
    rejected:
        'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-400',
};

const LABELS: Record<string, string> = {
    pending: 'Pending',
    active: 'Active',
    inactive: 'Inactive',
    rejected: 'Rejected',
};

export function UserStatusBadge({ status }: { status: keyof typeof LABELS }) {
    return (
        <Badge variant="outline" className={STYLES[status]}>
            {LABELS[status]}
        </Badge>
    );
}
