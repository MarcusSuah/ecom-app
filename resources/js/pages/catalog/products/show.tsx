import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Package } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ListedPrice } from '@/components/approx-price';
import { useMoney } from '@/lib/money';
import { StockBadge } from '@/components/stock-badge';
import InputError from '@/components/input-error';
import { OpenChatButton } from '@/components/open-chat-button';
import { Badge } from '@/components/ui/badge';
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
import { VerifiedBadge } from '@/components/verified-badge';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import {
    index as productsIndex,
    order as orderProduct,
} from '@/routes/products';
import { create as requestQuote } from '@/routes/rfqs';
import { edit as editProduct } from '@/routes/supplier/products';
import { show as supplierShow } from '@/routes/suppliers';
import type { SharedData } from '@/types';

type Tier = {
    min_qty: number;
    unit_price: number;
    formatted_unit_price: string;
    listed_unit_price: string | null;
};

type Props = {
    product: {
        id: number;
        name: string;
        slug: string;
        status: string;
        description: string | null;
        unit: string;
        unit_label: string;
        moq: number;
        lead_time: string;
        currency: string;
        origin_country: string;
        stock: number | null;
        stock_status: string;
        stock_label: string;
        units_sold: number;
        sample_available: boolean;
        sample_price: string | null;
        category: { name: string; slug: string; parent: string | null };
        tiers: Tier[];
        currency_subunits: number;
        /** The visitor's currency when it differs from the listing's, for approximate totals. */
        display: { currency: string; subunits: number; rate: number } | null;
        images: { id: number; url: string; thumbnail_url: string }[];
    };
    supplier: {
        name: string;
        slug: string;
        logo_url: string | null;
        city: string | null;
        country: string;
        is_verified: boolean;
        member_since: string | null;
        listed_products: number;
    };
    canEdit: boolean;
};

/**
 * Same rule as App\Actions\Products\PriceForQuantity: the tier with the
 * highest starting quantity at or below the requested quantity.
 */
function tierFor(tiers: Tier[], quantity: number): Tier | null {
    return (
        [...tiers].reverse().find((tier) => tier.min_qty <= quantity) ?? null
    );
}

export default function ProductShow({ product, supplier, canEdit }: Props) {
    const { auth, paymentProtectionLabel, errors } = usePage<
        SharedData & {
            paymentProtectionLabel: string;
            errors: Record<string, string | undefined>;
        }
    >().props;
    const [quantity, setQuantity] = useState(String(product.moq));
    const [deliverTo, setDeliverTo] = useState('');
    const [activeImage, setActiveImage] = useState(0);
    const parsedQuantity = Number.parseInt(quantity, 10);
    const tier = useMemo(
        () =>
            Number.isFinite(parsedQuantity)
                ? tierFor(product.tiers, parsedQuantity)
                : null,
        [product.tiers, parsedQuantity],
    );

    const { format, listed } = useMoney();
    /** Live totals in the visitor's currency, converted like the server does. */
    const money = (minor: number) => format(minor, product.currency);
    /** The supplier's own price, which is what the buyer is charged. */
    const approx = (minor: number) => listed(minor, product.currency);

    const belowMoq =
        Number.isFinite(parsedQuantity) && parsedQuantity < product.moq;

    return (
        <>
            <Head title={product.name} />
            <nav
                aria-label="Breadcrumb"
                className="mb-4 text-sm text-muted-foreground"
            >
                <Link href={productsIndex()} className="hover:underline">
                    Products
                </Link>{' '}
                /{' '}
                <Link
                    href={productsIndex({
                        query: { category: product.category.slug },
                    })}
                    className="hover:underline"
                >
                    {product.category.name}
                </Link>
            </nav>

            {product.status !== 'active' && (
                <p className="mb-4 rounded-md border border-amber-500/40 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    This product is {product.status} and only visible to you.
                </p>
            )}

            <div className="grid gap-8 lg:grid-cols-[1fr_24rem]">
                <div className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <div className="aspect-square overflow-hidden rounded-lg border bg-muted">
                                {product.images[activeImage] ? (
                                    <img
                                        src={product.images[activeImage].url}
                                        alt={product.name}
                                        className="size-full object-contain"
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                        <Package
                                            className="size-16"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}
                            </div>
                            {product.images.length > 1 && (
                                <div className="flex flex-wrap gap-2">
                                    {product.images.map((image, index) => (
                                        <button
                                            key={image.id}
                                            type="button"
                                            onClick={() =>
                                                setActiveImage(index)
                                            }
                                            className={cn(
                                                'size-16 overflow-hidden rounded-md border',
                                                index === activeImage &&
                                                    'ring-2 ring-primary',
                                            )}
                                            aria-label={`Show photo ${index + 1}`}
                                            aria-pressed={index === activeImage}
                                        >
                                            <img
                                                src={image.thumbnail_url}
                                                alt=""
                                                className="size-full object-cover"
                                            />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="space-y-4">
                            <h1 className="text-2xl font-semibold">
                                {product.name}
                            </h1>
                            <StockBadge
                                status={product.stock_status}
                                label={product.stock_label}
                                sold={product.units_sold}
                            />
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Minimum order
                                    </dt>
                                    <dd className="font-medium">
                                        {product.moq.toLocaleString()}{' '}
                                        {product.unit}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Lead time
                                    </dt>
                                    <dd className="font-medium">
                                        {product.lead_time}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Origin
                                    </dt>
                                    <dd className="font-medium">
                                        {product.origin_country}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Samples
                                    </dt>
                                    <dd className="font-medium">
                                        {product.sample_available
                                            ? (product.sample_price ??
                                              'Available')
                                            : 'Not available'}
                                    </dd>
                                </div>
                            </dl>

                            <div className="overflow-hidden rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>
                                                Quantity ({product.unit})
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Unit price
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {product.tiers.map((row, index) => {
                                            const next =
                                                product.tiers[index + 1];

                                            return (
                                                <TableRow
                                                    key={row.min_qty}
                                                    className={cn(
                                                        tier?.min_qty ===
                                                            row.min_qty &&
                                                            'bg-primary/5 font-medium',
                                                    )}
                                                >
                                                    <TableCell>
                                                        {next
                                                            ? `${row.min_qty.toLocaleString()} – ${(next.min_qty - 1).toLocaleString()}`
                                                            : `${row.min_qty.toLocaleString()}+`}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.formatted_unit_price
                                                        }
                                                        <ListedPrice
                                                            value={
                                                                row.listed_unit_price
                                                            }
                                                            className="block"
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>

                    {product.description && (
                        <section aria-labelledby="description-heading">
                            <h2
                                id="description-heading"
                                className="mb-2 text-lg font-semibold"
                            >
                                Description
                            </h2>
                            <p className="text-sm leading-relaxed whitespace-pre-line">
                                {product.description}
                            </p>
                        </section>
                    )}
                </div>

                <aside className="space-y-4">
                    <section
                        aria-labelledby="calculator-heading"
                        className="space-y-3 rounded-lg border p-4"
                    >
                        <h2 id="calculator-heading" className="font-semibold">
                            Price for your quantity
                        </h2>
                        <div className="grid gap-1.5">
                            <Label htmlFor="quantity">
                                Quantity ({product.unit_label.toLowerCase()})
                            </Label>
                            <Input
                                id="quantity"
                                type="number"
                                min={product.moq}
                                value={quantity}
                                onChange={(event) =>
                                    setQuantity(event.target.value)
                                }
                                aria-describedby="quantity-result"
                            />
                        </div>
                        <div
                            id="quantity-result"
                            aria-live="polite"
                            className="text-sm"
                        >
                            {belowMoq ? (
                                <p className="text-red-600 dark:text-red-400">
                                    The minimum order is{' '}
                                    {product.moq.toLocaleString()}{' '}
                                    {product.unit}.
                                </p>
                            ) : tier ? (
                                <dl className="space-y-1">
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Unit price
                                        </dt>
                                        <dd className="tabular-nums">
                                            {money(tier.unit_price)}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between text-base font-semibold">
                                        <dt>Total</dt>
                                        <dd className="tabular-nums">
                                            {money(
                                                tier.unit_price *
                                                    parsedQuantity,
                                            )}
                                        </dd>
                                    </div>
                                    {approx(tier.unit_price) && (
                                        <div className="flex justify-end">
                                            <ListedPrice
                                                value={approx(
                                                    tier.unit_price *
                                                        parsedQuantity,
                                                )}
                                            />
                                        </div>
                                    )}
                                </dl>
                            ) : (
                                <p className="text-muted-foreground">
                                    Enter a quantity.
                                </p>
                            )}
                        </div>
                        {!canEdit && product.status === 'active' && (
                            <div className="space-y-2 border-t pt-3">
                                <Label htmlFor="deliver_to">Deliver to</Label>
                                <Input
                                    id="deliver_to"
                                    value={deliverTo}
                                    onChange={(event) =>
                                        setDeliverTo(event.target.value)
                                    }
                                    placeholder="City"
                                />
                                <InputError
                                    message={
                                        errors.quantity ?? errors.deliver_to
                                    }
                                />
                                <Button
                                    className="w-full"
                                    disabled={
                                        belowMoq ||
                                        !tier ||
                                        !deliverTo ||
                                        product.stock_status ===
                                            'out_of_stock' ||
                                        (product.stock !== null &&
                                            parsedQuantity > product.stock)
                                    }
                                    onClick={() =>
                                        auth.user
                                            ? router.post(
                                                  orderProduct.url(
                                                      product.slug,
                                                  ),
                                                  {
                                                      quantity: parsedQuantity,
                                                      deliver_to: deliverTo,
                                                  },
                                              )
                                            : router.visit(login())
                                    }
                                >
                                    {product.stock_status === 'out_of_stock'
                                        ? 'Out of stock'
                                        : 'Order now at this price'}
                                </Button>
                                {product.stock !== null &&
                                    product.stock_status !== 'out_of_stock' &&
                                    parsedQuantity > product.stock && (
                                        <p className="text-xs text-red-600 dark:text-red-400">
                                            Only{' '}
                                            {product.stock.toLocaleString()}{' '}
                                            {product.unit} are in stock. Request
                                            a quote for more.
                                        </p>
                                    )}
                                <p className="text-xs text-muted-foreground">
                                    {paymentProtectionLabel}: your payment is
                                    held until you confirm delivery.
                                </p>
                            </div>
                        )}
                        <Button
                            asChild
                            variant={canEdit ? 'default' : 'outline'}
                            className="w-full"
                        >
                            <Link
                                href={requestQuote({
                                    query: {
                                        product: product.id,
                                        quantity:
                                            belowMoq || !tier
                                                ? product.moq
                                                : parsedQuantity,
                                    },
                                })}
                            >
                                Request a quote
                            </Link>
                        </Button>
                        {canEdit ? (
                            <Button
                                asChild
                                variant="outline"
                                className="w-full"
                            >
                                <Link href={editProduct(product.slug)}>
                                    Edit product
                                </Link>
                            </Button>
                        ) : (
                            <OpenChatButton
                                company={supplier.slug}
                                productId={product.id}
                                label="Chat with supplier"
                                className="w-full"
                            />
                        )}
                    </section>

                    <section
                        aria-labelledby="supplier-heading"
                        className="space-y-3 rounded-lg border p-4 text-sm"
                    >
                        <h2 id="supplier-heading" className="sr-only">
                            Supplier
                        </h2>
                        <div className="flex items-center gap-3">
                            {supplier.logo_url ? (
                                <img
                                    src={supplier.logo_url}
                                    alt=""
                                    className="size-10 rounded-md border object-cover"
                                />
                            ) : (
                                <span className="flex size-10 items-center justify-center rounded-md border text-muted-foreground">
                                    <Building2
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                            )}
                            <div>
                                <Link
                                    href={supplierShow(supplier.slug)}
                                    className="font-medium hover:underline"
                                >
                                    {supplier.name}
                                </Link>
                                <p className="text-muted-foreground">
                                    {[supplier.city, supplier.country]
                                        .filter(Boolean)
                                        .join(', ')}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <VerifiedBadge verified={supplier.is_verified} />
                            <Badge variant="outline">
                                {supplier.listed_products} products
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">
                            Member since {formatDate(supplier.member_since)}
                        </p>
                    </section>
                </aside>
            </div>
        </>
    );
}
