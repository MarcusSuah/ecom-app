import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import tenantRoutes from '@/routes/platform/tenants';
import type { Paginated } from '@/types';

type TenantRow = {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    status: string;
    companies_count: number;
    plan: string | null;
};

export default function TenantsIndex({
    tenants,
    filters,
    centralDomain,
}: {
    tenants: Paginated<TenantRow>;
    filters: { search: string };
    centralDomain: string;
}) {
    return (
        <>
            <Head title="Marketplaces" />
            <div className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Marketplaces"
                        description="Every marketplace (tenant) running on the platform."
                    />
                    <Button asChild>
                        <Link href={tenantRoutes.create()}>
                            <Plus aria-hidden="true" />
                            New marketplace
                        </Link>
                    </Button>
                </div>

                <div className="mb-4 grid max-w-sm gap-1">
                    <Label htmlFor="search">Search</Label>
                    <Input
                        id="search"
                        defaultValue={filters.search}
                        placeholder="Name, subdomain or domain"
                        onKeyDown={(event) =>
                            event.key === 'Enter' &&
                            router.get(
                                tenantRoutes.index.url(),
                                { search: event.currentTarget.value },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Marketplace</TableHead>
                                <TableHead>Address</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead className="text-right">
                                    Sellers
                                </TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tenants.data.map((tenant) => (
                                <TableRow key={tenant.id}>
                                    <TableCell>
                                        <Link
                                            href={tenantRoutes.show(tenant.id)}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {tenant.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        <div>
                                            {tenant.slug}.{centralDomain}
                                        </div>
                                        {tenant.domain && (
                                            <div className="text-muted-foreground">
                                                {tenant.domain}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {tenant.plan ?? (
                                            <span className="text-red-600">
                                                None
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {tenant.companies_count}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={tenant.status} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <Pagination page={tenants} />
            </div>
        </>
    );
}

TenantsIndex.layout = {
    breadcrumbs: [{ title: 'Marketplaces', href: tenantRoutes.index() }],
};
