import { Head, Link } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { StockBadge } from '@/components/stock-badge';
import { Button } from '@/components/ui/button';
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
import productRoutes from '@/routes/supplier/products';
import type { Paginated, ProductStatus } from '@/types';

type Row = {
    id: number;
    name: string;
    slug: string;
    status: ProductStatus;
    category: string;
    moq: number;
    unit: string;
    from_price: string | null;
    thumbnail_url: string | null;
    stock_status: string;
    stock_label: string;
    units_sold: number;
    updated_at: string | null;
};

const statusFilters: { value: string; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Listed' },
    { value: 'draft', label: 'Drafts' },
    { value: 'archived', label: 'Archived' },
];

export default function SupplierProducts({
    products,
    filters,
    listing,
}: {
    products: Paginated<Row>;
    filters: { status: string };
    listing: { listed: number; limit: number | null };
}) {
    return (
        <>
            <Head title="My products" />
            <div className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="My products"
                        description={
                            listing.limit === null
                                ? `${listing.listed} listed. Your plan has no listing limit.`
                                : `${listing.listed} of ${listing.limit} listed products used on your plan.`
                        }
                    />
                    <Button asChild>
                        <Link href={productRoutes.create()}>
                            <Plus aria-hidden="true" />
                            Add product
                        </Link>
                    </Button>
                </div>

                <nav
                    aria-label="Filter by status"
                    className="mb-4 flex flex-wrap gap-2"
                >
                    {statusFilters.map((status) => (
                        <Link
                            key={status.value}
                            href={productRoutes.index({
                                query: status.value
                                    ? { status: status.value }
                                    : {},
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

                {products.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No products here yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>From</TableHead>
                                    <TableHead>MOQ</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Updated</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.data.map((product) => (
                                    <TableRow key={product.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                {product.thumbnail_url ? (
                                                    <img
                                                        src={
                                                            product.thumbnail_url
                                                        }
                                                        alt=""
                                                        className="size-10 rounded border object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex size-10 items-center justify-center rounded border text-muted-foreground">
                                                        <Package
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </span>
                                                )}
                                                <div>
                                                    <Link
                                                        href={productRoutes.edit(
                                                            product.slug,
                                                        )}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {product.name}
                                                    </Link>
                                                    <div className="text-xs text-muted-foreground">
                                                        {product.category}
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {product.from_price ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {product.moq.toLocaleString()}{' '}
                                            {product.unit}
                                        </TableCell>
                                        <TableCell>
                                            <StockBadge
                                                status={product.stock_status}
                                                label={product.stock_label}
                                                sold={product.units_sold}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    product.status === 'active'
                                                        ? 'listed'
                                                        : product.status
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(product.updated_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination page={products} />
            </div>
        </>
    );
}

SupplierProducts.layout = {
    breadcrumbs: [{ title: 'My products', href: productRoutes.index() }],
};
