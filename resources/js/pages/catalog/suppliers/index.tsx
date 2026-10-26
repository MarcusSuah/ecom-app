import { Head, Link, router } from '@inertiajs/react';
import { useLiveSearch } from '@/hooks/use-live-search';
import { Star } from 'lucide-react';
import { hueStyle, initials } from '@/lib/hue';
import { type FormEvent, useState } from 'react';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { VerifiedBadge } from '@/components/verified-badge';
import {
    index as suppliersIndex,
    show as supplierShow,
} from '@/routes/suppliers';
import type { CompanySummary, Paginated } from '@/types';

type SupplierRow = CompanySummary & { listed_products: number };

export default function SuppliersIndex({
    suppliers,
    filters,
}: {
    suppliers: Paginated<SupplierRow>;
    filters: { q: string; verified: boolean };
}) {
    const [search, setSearch] = useState(filters.q);

    const apply = (next: { q: string; verified: boolean }) =>
        router.get(
            suppliersIndex.url(),
            {
                ...(next.q ? { q: next.q } : {}),
                ...(next.verified ? { verified: 1 } : {}),
            },
            { preserveState: true, replace: true },
        );

    useLiveSearch(search, (q) => apply({ ...filters, q }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ ...filters, q: search });
    };

    return (
        <>
            <Head title="Suppliers" />
            <h1 className="mb-4 text-2xl font-semibold">Suppliers</h1>
            <div className="mb-6 flex flex-wrap items-center gap-4">
                <form
                    onSubmit={submit}
                    role="search"
                    className="flex flex-1 gap-2"
                >
                    <Label htmlFor="q" className="sr-only">
                        Search suppliers
                    </Label>
                    <Input
                        id="q"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Supplier name"
                        className="min-w-0 flex-1"
                    />
                    <Button type="submit">Search</Button>
                </form>
                <label className="flex items-center gap-2 text-sm">
                    <Switch
                        checked={filters.verified}
                        onCheckedChange={(checked) =>
                            apply({ q: search, verified: checked })
                        }
                    />
                    Verified only
                </label>
            </div>

            {suppliers.data.length === 0 ? (
                <p className="rounded-lg border p-8 text-center text-muted-foreground">
                    No suppliers match.
                </p>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {suppliers.data.map((supplier) => (
                        <li
                            key={supplier.id}
                            className="sf-panel flex gap-3 p-4 focus-within:ring-2 focus-within:ring-ring"
                            style={hueStyle(supplier.slug)}
                        >
                            <span className="sf-avatar" aria-hidden="true">
                                {supplier.logo_url ? (
                                    <img
                                        src={supplier.logo_url}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    initials(supplier.name)
                                )}
                            </span>
                            <div className="min-w-0 space-y-1 text-sm">
                                <Link
                                    href={supplierShow(supplier.slug)}
                                    className="font-medium hover:underline"
                                >
                                    {supplier.name}
                                </Link>
                                <p className="text-muted-foreground">
                                    {[supplier.city, supplier.country]
                                        .filter(Boolean)
                                        .join(', ')}{' '}
                                    · {supplier.listed_products} products
                                </p>
                                <div className="flex flex-wrap gap-1">
                                    <VerifiedBadge
                                        verified={supplier.is_verified}
                                    />
                                    {supplier.is_featured && (
                                        <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs">
                                            <Star
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            Featured
                                        </span>
                                    )}
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Pagination page={suppliers} />
        </>
    );
}
