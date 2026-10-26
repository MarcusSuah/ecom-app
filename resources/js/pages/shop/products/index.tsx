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
import productRoutes from '@/routes/shop/products';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    name: string;
    category: string;
    price: string;
    stock: number;
    is_active: boolean;
    image_url: string | null;
    stock_status: string;
    stock_label: string;
    units_sold: number;
};

export default function ShopItems({
    products,
    listing,
}: {
    products: Paginated<Row>;
    listing: { listed: number; limit: number | null };
}) {
    return (
        <>
            <Head title="Shop items" />
            <div className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Shop items"
                        description={
                            listing.limit === null
                                ? `${listing.listed} items for sale.`
                                : `${listing.listed} of ${listing.limit} listed items used on your plan.`
                        }
                    />
                    <Button asChild>
                        <Link href={productRoutes.create()}>
                            <Plus aria-hidden="true" />
                            Add item
                        </Link>
                    </Button>
                </div>
                {products.data.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        No items yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead className="text-right">
                                        Price
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Stock
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.data.map((product) => (
                                    <TableRow key={product.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                {product.image_url ? (
                                                    <img
                                                        src={product.image_url}
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
                                                            product.id,
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
                                        <TableCell className="text-right tabular-nums">
                                            {product.price}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            <div>{product.stock}</div>
                                            <StockBadge
                                                status={product.stock_status}
                                                label={product.stock_label}
                                                sold={product.units_sold}
                                                className="justify-end"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    product.is_active
                                                        ? 'active'
                                                        : 'hidden'
                                                }
                                            />
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

ShopItems.layout = {
    breadcrumbs: [{ title: 'Shop items', href: productRoutes.index() }],
};
