import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import AdminCompanyController from '@/actions/App/Http/Controllers/Admin/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { VerifiedBadge } from '@/components/verified-badge';
import { usePermissions } from '@/hooks/use-permissions';
import companyRoutes from '@/routes/admin/companies';
import type { CompanySummary, Paginated } from '@/types';

type Row = CompanySummary & {
    owner: { name: string; email: string };
    plan: string | null;
    can_be_featured: boolean;
    suspended_at: string | null;
    suspended_reason: string | null;
};

type Props = {
    companies: Paginated<Row>;
    filters: { type: string; verified: boolean; search: string };
    sellerPlans: { id: number; name: string }[];
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function CompaniesIndex({
    companies,
    filters,
    sellerPlans,
}: Props) {
    const { can } = usePermissions();
    const [planFor, setPlanFor] = useState<Row | null>(null);
    const [suspending, setSuspending] = useState<Row | null>(null);
    const [reason, setReason] = useState('');

    const applyFilter = (changes: Partial<Props['filters']>) => {
        router.get(
            companyRoutes.index.url(),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Companies" />
            <div className="p-4">
                <Heading
                    title="Companies"
                    description="Suppliers and shops on this marketplace."
                />

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            defaultValue={filters.search}
                            onKeyDown={(event) =>
                                event.key === 'Enter' &&
                                applyFilter({
                                    search: event.currentTarget.value,
                                })
                            }
                            placeholder="Company name"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="type">Type</Label>
                        <select
                            id="type"
                            className={selectClass}
                            value={filters.type}
                            onChange={(event) =>
                                applyFilter({ type: event.target.value })
                            }
                        >
                            <option value="">All</option>
                            <option value="supplier">Suppliers</option>
                            <option value="shop">Shops</option>
                        </select>
                    </div>
                    <label className="flex items-center gap-2 pb-2 text-sm">
                        <Switch
                            checked={filters.verified}
                            onCheckedChange={(checked) =>
                                applyFilter({ verified: checked })
                            }
                        />
                        Verified only
                    </label>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Company</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Verification</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Featured</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {companies.data.map((company) => (
                                <TableRow key={company.id}>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-2 font-medium">
                                            {company.name}
                                            <VerifiedBadge
                                                verified={company.is_verified}
                                            />
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {company.type_label} ·{' '}
                                            {company.city ?? '—'}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div>{company.owner.name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {company.owner.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {company.verification_status ? (
                                            <StatusBadge
                                                status={
                                                    company.verification_status
                                                }
                                            />
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>{company.plan ?? '—'}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Switch
                                                checked={company.is_featured}
                                                // Un-featuring is always allowed; featuring needs a plan with featured listings.
                                                disabled={
                                                    !can('companies.feature') ||
                                                    (!company.is_featured &&
                                                        !company.can_be_featured)
                                                }
                                                aria-label={`Feature ${company.name}`}
                                                aria-describedby={
                                                    !company.can_be_featured
                                                        ? `feature-hint-${company.id}`
                                                        : undefined
                                                }
                                                onCheckedChange={(checked) =>
                                                    router.put(
                                                        companyRoutes.feature.url(
                                                            company.slug,
                                                        ),
                                                        {
                                                            is_featured:
                                                                checked,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                            onError: (errors) =>
                                                                toast.error(
                                                                    errors.is_featured ??
                                                                        'Could not change this company.',
                                                                ),
                                                        },
                                                    )
                                                }
                                            />
                                            {!company.can_be_featured &&
                                                !company.is_featured && (
                                                    <span
                                                        id={`feature-hint-${company.id}`}
                                                        className="text-xs text-muted-foreground"
                                                    >
                                                        Not in plan
                                                    </span>
                                                )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="space-x-2 text-right whitespace-nowrap">
                                        {company.suspended_at && (
                                            <span
                                                className="mr-1 rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300"
                                                title={
                                                    company.suspended_reason ??
                                                    undefined
                                                }
                                            >
                                                Suspended
                                            </span>
                                        )}
                                        {can('companies.manage') && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        companyRoutes.manage.url(
                                                            company.slug,
                                                        ),
                                                    )
                                                }
                                                title="Add products, photos and shop items for this business without signing in as its owner"
                                            >
                                                Manage
                                            </Button>
                                        )}
                                        {can('seller-subscriptions.manage') && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setPlanFor(company)
                                                }
                                            >
                                                Set plan
                                            </Button>
                                        )}
                                        {can('companies.suspend') &&
                                            (company.suspended_at ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.delete(
                                                            companyRoutes.reinstate.url(
                                                                company.slug,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Reinstate
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="text-destructive"
                                                    onClick={() => {
                                                        setReason('');
                                                        setSuspending(company);
                                                    }}
                                                >
                                                    Suspend
                                                </Button>
                                            ))}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <Pagination page={companies} />

                {companies.data.length === 0 && (
                    <p className="mt-4 text-sm text-muted-foreground">
                        No companies match.{' '}
                        <Link
                            href={companyRoutes.index()}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            Clear filters
                        </Link>
                    </p>
                )}
            </div>

            <Dialog
                open={suspending !== null}
                onOpenChange={(open) => !open && setSuspending(null)}
            >
                <DialogContent>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (!suspending) {
                                return;
                            }

                            router.post(
                                companyRoutes.suspend.url(suspending.slug),
                                { reason },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setSuspending(null),
                                    onError: (errors) =>
                                        toast.error(
                                            errors.reason ??
                                                'Could not suspend.',
                                        ),
                                },
                            );
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>
                                Suspend {suspending?.name}
                            </DialogTitle>
                            <DialogDescription>
                                Its products, shop and profile are hidden from
                                the storefront and search until you reinstate
                                it. Open orders are not affected.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="suspend-reason">Reason</Label>
                            <Input
                                id="suspend-reason"
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                maxLength={500}
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSuspending(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" variant="destructive">
                                Suspend
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={planFor !== null}
                onOpenChange={(open) => !open && setPlanFor(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Set plan for {planFor?.name}</DialogTitle>
                        <DialogDescription>
                            Records a payment received outside the platform
                            (bank transfer, cash) and starts the plan now.
                        </DialogDescription>
                    </DialogHeader>
                    {planFor && (
                        <Form
                            {...AdminCompanyController.activatePlan.form(
                                planFor.slug,
                            )}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setPlanFor(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan_id">Plan</Label>
                                        <select
                                            id="plan_id"
                                            name="plan_id"
                                            className={selectClass}
                                            required
                                        >
                                            {sellerPlans.map((plan) => (
                                                <option
                                                    key={plan.id}
                                                    value={plan.id}
                                                >
                                                    {plan.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.plan_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="periods">
                                            Billing periods paid
                                        </Label>
                                        <Input
                                            id="periods"
                                            name="periods"
                                            type="number"
                                            min={1}
                                            max={36}
                                            defaultValue={1}
                                            required
                                        />
                                        <InputError message={errors.periods} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="reference">
                                            Payment reference
                                        </Label>
                                        <Input
                                            id="reference"
                                            name="reference"
                                            placeholder="e.g. bank transfer ID"
                                        />
                                        <InputError
                                            message={errors.reference}
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Record payment & start plan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'Companies', href: companyRoutes.index() }],
};
