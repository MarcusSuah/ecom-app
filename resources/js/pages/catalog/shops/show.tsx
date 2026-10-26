import { Head, Link, router, usePage } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { useState } from 'react';
import { ListedPrice } from '@/components/approx-price';
import InputError from '@/components/input-error';
import { OpenChatButton } from '@/components/open-chat-button';
import {
    ProductGallery,
    useStorefrontPanels,
} from '@/components/storefront/storefront-panels';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { VerifiedBadge } from '@/components/verified-badge';
import { hueStyle, initials } from '@/lib/hue';
import { login } from '@/routes';
import cartRoutes from '@/routes/cart';
import { index as shopsIndex } from '@/routes/shops';
import type {
    CustomerLocation,
    DeliveryEstimate,
    Paginated,
    SharedData,
} from '@/types';

type Item = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    price: string;
    price_listed: string | null;
    stock: number;
    category_slug: string;
    images: { id: number; url: string; thumbnail_url: string }[];
    stock_status: string;
    stock_label: string;
    units_sold: number;
    image_url: string | null;
};

type Props = {
    shop: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        logo_url: string | null;
        is_verified: boolean;
        address: string;
        landmark: string | null;
        phone: string | null;
        offers_pickup: boolean;
        offers_delivery: boolean;
        is_open: boolean;
        opening_hours: Record<
            string,
            { open: string; close: string } | null
        > | null;
        base_fee: string;
        per_km_rate: string;
        free_threshold: string | null;
        delivery_radius_km: number;
        delivery: DeliveryEstimate | null;
    };
    products: Paginated<Item>;
    location: CustomerLocation | null;
    canOrder: boolean;
};

const dayNames: Record<string, string> = {
    mon: 'Mon',
    tue: 'Tue',
    wed: 'Wed',
    thu: 'Thu',
    fri: 'Fri',
    sat: 'Sat',
    sun: 'Sun',
};

export default function ShopShow({
    shop,
    products,
    location,
    canOrder,
}: Props) {
    const { auth, errors } = usePage<
        SharedData & { errors: Record<string, string> }
    >().props;
    const [quantities, setQuantities] = useState<Record<number, string>>({});
    const [viewing, setViewing] = useState<Item | null>(null);

    const { openLocalOrder } = useStorefrontPanels();

    const add = (item: Item) => {
        if (!auth.user) {
            router.visit(login());

            return;
        }

        router.post(
            cartRoutes.items.store.url(item.id),
            { quantity: Number(quantities[item.id] ?? 1) },
            { preserveScroll: true, onSuccess: () => openLocalOrder() },
        );
    };

    return (
        <>
            <Head title={shop.name} />
            <nav
                aria-label="Breadcrumb"
                className="mb-4 text-sm text-muted-foreground"
            >
                <Link href={shopsIndex()} className="hover:underline">
                    Local shops
                </Link>
            </nav>
            <div className="grid gap-8 lg:grid-cols-[1fr_20rem]">
                <section aria-labelledby="items-heading" className="space-y-4">
                    <header className="flex items-start gap-4">
                        {shop.logo_url ? (
                            <img
                                src={shop.logo_url}
                                alt=""
                                className="size-16 rounded-lg border object-cover"
                            />
                        ) : (
                            <span className="flex size-16 items-center justify-center rounded-lg border text-muted-foreground">
                                <Store className="size-8" aria-hidden="true" />
                            </span>
                        )}
                        <div>
                            <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold">
                                {shop.name}
                                <VerifiedBadge verified={shop.is_verified} />
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {shop.address}
                                {shop.landmark
                                    ? ` · near ${shop.landmark}`
                                    : ''}
                            </p>
                            <Badge variant="outline" className="mt-1">
                                {shop.is_open ? 'Open now' : 'Closed now'}
                            </Badge>
                        </div>
                    </header>
                    {shop.description && (
                        <p className="text-sm whitespace-pre-line">
                            {shop.description}
                        </p>
                    )}
                    <InputError message={errors.quantity} />

                    <h2 id="items-heading" className="text-lg font-semibold">
                        Items ({products.total})
                    </h2>
                    {products.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            This shop has no items yet.
                        </p>
                    ) : (
                        <ul className="grid grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-3.5">
                            {products.data.map((item) => (
                                <li key={item.id}>
                                    <ShopItemCard
                                        item={item}
                                        canOrder={canOrder}
                                        quantity={quantities[item.id] ?? '1'}
                                        onQuantity={(value) =>
                                            setQuantities({
                                                ...quantities,
                                                [item.id]: value,
                                            })
                                        }
                                        onAdd={() => add(item)}
                                        onOpen={() => setViewing(item)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination page={products} />
                </section>

                <aside className="space-y-4 text-sm">
                    <section
                        aria-labelledby="delivery-heading"
                        className="space-y-2 rounded-lg border p-4"
                    >
                        <h2 id="delivery-heading" className="font-semibold">
                            Pickup and delivery
                        </h2>
                        <ul className="space-y-1">
                            <li>
                                {shop.offers_pickup
                                    ? 'Pickup available'
                                    : 'No pickup'}
                            </li>
                            {shop.offers_delivery ? (
                                <>
                                    <li>
                                        Delivery within{' '}
                                        {shop.delivery_radius_km} km:{' '}
                                        {shop.base_fee} + {shop.per_km_rate} per
                                        km after the first
                                    </li>
                                    {shop.free_threshold && (
                                        <li>
                                            Free delivery on orders from{' '}
                                            {shop.free_threshold}
                                        </li>
                                    )}
                                </>
                            ) : (
                                <li>No delivery</li>
                            )}
                        </ul>
                        {shop.delivery && location && shop.offers_delivery && (
                            <p className="rounded-md bg-muted p-2">
                                To {location.label} (
                                {shop.delivery.distance_km.toFixed(1)} km):{' '}
                                <span className="font-medium">
                                    {shop.delivery.deliverable
                                        ? shop.delivery.formatted_fee
                                        : 'out of delivery range'}
                                </span>
                            </p>
                        )}
                    </section>
                    {shop.opening_hours && (
                        <section
                            aria-labelledby="hours-heading"
                            className="rounded-lg border p-4"
                        >
                            <h2
                                id="hours-heading"
                                className="mb-2 font-semibold"
                            >
                                Opening hours
                            </h2>
                            <dl className="grid grid-cols-[3rem_1fr] gap-y-1">
                                {Object.entries(dayNames).map(([key, name]) => (
                                    <div key={key} className="contents">
                                        <dt className="text-muted-foreground">
                                            {name}
                                        </dt>
                                        <dd>
                                            {shop.opening_hours?.[key]
                                                ? `${shop.opening_hours[key]?.open}–${shop.opening_hours[key]?.close}`
                                                : 'Closed'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    )}
                    {canOrder && (
                        <OpenChatButton
                            company={shop.slug}
                            label="Message the shop"
                            className="w-full"
                        />
                    )}
                </aside>
            </div>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="storefront sm:max-w-2xl">
                    {viewing && <ShopItemDetail item={viewing} />}
                </DialogContent>
            </Dialog>
        </>
    );
}

const stockPill: Record<string, string> = {
    in_stock: 'sf-pill sf-pill-ok',
    low_stock: 'sf-pill sf-pill-no',
    out_of_stock: 'sf-pill sf-pill-bad',
};

function ShopItemCard({
    item,
    canOrder,
    quantity,
    onQuantity,
    onAdd,
    onOpen,
}: {
    item: Item;
    canOrder: boolean;
    quantity: string;
    onQuantity: (value: string) => void;
    onAdd: () => void;
    onOpen: () => void;
}) {
    const out = item.stock_status === 'out_of_stock';

    return (
        <article className="sf-card h-full">
            <button
                type="button"
                className="sf-thumb text-left"
                style={hueStyle(item.category_slug)}
                onClick={onOpen}
                aria-label={`View ${item.name}`}
            >
                {item.image_url && (
                    <img
                        src={item.image_url}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        width={400}
                        height={300}
                    />
                )}
                <span className="sf-tag">{item.category}</span>
                {!item.image_url && (
                    <span className="sf-glyph" aria-hidden="true">
                        {initials(item.category)}
                    </span>
                )}
            </button>
            <div className="flex flex-1 flex-col gap-2 px-3.5 pt-3 pb-3.5">
                <h3 className="text-[16px] leading-tight font-semibold">
                    <button
                        type="button"
                        onClick={onOpen}
                        className="text-left hover:underline"
                    >
                        {item.name}
                    </button>
                </h3>
                <div>
                    <div className="sf-price">{item.price}</div>
                    <ListedPrice value={item.price_listed} />
                </div>
                <div className="flex flex-wrap gap-1.5">
                    <span className={stockPill[item.stock_status] ?? 'sf-pill'}>
                        {item.stock_label}
                    </span>
                    {item.units_sold > 0 && (
                        <span className="sf-pill">{item.units_sold} sold</span>
                    )}
                    {item.images.length > 1 && (
                        <span className="sf-pill">
                            {item.images.length} photos
                        </span>
                    )}
                </div>
                {canOrder && (
                    <div className="mt-auto flex items-center gap-2 pt-1">
                        <Input
                            type="number"
                            min={1}
                            max={Math.max(1, item.stock)}
                            aria-label={`Quantity of ${item.name}`}
                            value={quantity}
                            onChange={(event) =>
                                onQuantity(
                                    String(
                                        Math.min(
                                            Math.max(1, item.stock),
                                            Math.max(
                                                1,
                                                Number.parseInt(
                                                    event.target.value,
                                                    10,
                                                ) || 1,
                                            ),
                                        ),
                                    ),
                                )
                            }
                            className="w-20"
                            disabled={out}
                        />
                        <button
                            type="button"
                            className="sf-btn sf-btn-sm sf-btn-primary flex-1"
                            onClick={onAdd}
                            disabled={out}
                        >
                            {out ? 'Out of stock' : 'Add to order'}
                        </button>
                    </div>
                )}
            </div>
        </article>
    );
}

/** The item with all its photos: the featured one first, the rest as thumbnails. */
function ShopItemDetail({ item }: { item: Item }) {
    const photos =
        item.images.length > 0
            ? item.images
            : item.image_url
              ? [{ id: 0, url: item.image_url, thumbnail_url: item.image_url }]
              : [];
    const [active, setActive] = useState(0);

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <ProductGallery
                images={photos}
                active={active}
                onSelect={setActive}
                name={item.name}
                hueKey={item.category_slug}
                category={item.category}
            />
            <div className="space-y-3">
                <DialogHeader>
                    <DialogTitle className="sf-display text-2xl">
                        {item.name}
                    </DialogTitle>
                    <DialogDescription>{item.category}</DialogDescription>
                </DialogHeader>
                <div>
                    <div className="sf-price">{item.price}</div>
                    <ListedPrice value={item.price_listed} />
                </div>
                <div className="flex flex-wrap gap-1.5">
                    <span className={stockPill[item.stock_status] ?? 'sf-pill'}>
                        {item.stock_label}
                    </span>
                    {item.units_sold > 0 && (
                        <span className="sf-pill">{item.units_sold} sold</span>
                    )}
                </div>
                {item.description && (
                    <p className="text-sm whitespace-pre-line text-[var(--sf-muted)]">
                        {item.description}
                    </p>
                )}
            </div>
        </div>
    );
}
