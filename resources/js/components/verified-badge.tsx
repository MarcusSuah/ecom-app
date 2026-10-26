import { BadgeCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Shown only for companies whose latest verification is approved. The
 * server decides `verified`; this component never infers it.
 */
export function VerifiedBadge({
    verified,
    className,
}: {
    verified: boolean | null | undefined;
    className?: string;
}) {
    if (!verified) {
        return null;
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                'border-emerald-600/40 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                className,
            )}
        >
            <BadgeCheck aria-hidden="true" />
            Verified
        </Badge>
    );
}
