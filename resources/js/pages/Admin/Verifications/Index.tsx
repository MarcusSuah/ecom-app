import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import verificationRoutes from '@/routes/admin/verifications';
import type { Paginated, Verification, VerificationStatus } from '@/types';

type Row = Verification & {
    company: {
        name: string;
        type: string;
        city: string | null;
        country: string;
    };
};

const statuses: { value: VerificationStatus; label: string }[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

export default function VerificationsIndex({
    verifications,
    filters,
}: {
    verifications: Paginated<Row>;
    filters: { status: VerificationStatus };
}) {
    return (
        <>
            <Head title="Verifications" />
            <div className="p-4">
                <Heading
                    title="Verifications"
                    description="Review company applications and their documents."
                />

                <nav aria-label="Filter by status" className="mb-4 flex gap-2">
                    {statuses.map((status) => (
                        <Link
                            key={status.value}
                            href={verificationRoutes.index({
                                query: { status: status.value },
                            })}
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm hover:bg-accent',
                                filters.status === status.value &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            aria-current={
                                filters.status === status.value
                                    ? 'page'
                                    : undefined
                            }
                        >
                            {status.label}
                        </Link>
                    ))}
                </nav>

                {verifications.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing here.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Company</TableHead>
                                    <TableHead>Registration no.</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Review
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {verifications.data.map((verification) => (
                                    <TableRow key={verification.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {verification.company.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {verification.company.type} ·{' '}
                                                {verification.company.city ??
                                                    '—'}
                                                , {verification.company.country}
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {verification.registration_number}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(
                                                verification.created_at,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={verification.status}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Link
                                                href={verificationRoutes.show(
                                                    verification.id,
                                                )}
                                                className="text-sm text-primary underline-offset-4 hover:underline"
                                            >
                                                {verification.status ===
                                                'pending'
                                                    ? 'Review'
                                                    : 'View'}
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination page={verifications} />
            </div>
        </>
    );
}

VerificationsIndex.layout = {
    breadcrumbs: [{ title: 'Verifications', href: verificationRoutes.index() }],
};
