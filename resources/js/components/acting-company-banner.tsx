import { Link, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { destroy as stopManaging } from '@/routes/acting-company';
import type { SharedData } from '@/types';

/**
 * Reminds a super admin that the seller screens belong to another
 * business, with a way back to their own account.
 */
export function ActingCompanyBanner() {
    const { auth } = usePage<SharedData>().props;

    if (!auth.actingCompany) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-3 border-b border-amber-500/40 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
        >
            <p className="flex items-center gap-2">
                <Building2 className="size-4" aria-hidden="true" />
                You are managing <strong>{auth.actingCompany.name}</strong>.
                Products, photos and shop items you save are added to it.
            </p>
            <Button size="sm" variant="outline" asChild>
                <Link href={stopManaging()} method="delete" as="button">
                    Stop managing
                </Link>
            </Button>
        </div>
    );
}
