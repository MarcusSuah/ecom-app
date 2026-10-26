import { Head, Link, router, usePage } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';
import { ListedPrice } from '@/components/approx-price';
import { ProductCard } from '@/components/product-card';
import { ShopCard } from '@/components/shop-card';
import type { ShopCardData } from '@/components/shop-card';
import { ShowMoreList } from '@/components/storefront/show-more-list';
import { useStorefrontPanels } from '@/components/storefront/storefront-panels';
import {
    RfqForm,
    type RfqFormOptions,
    emptyRfq,
    rfqSteps,
} from '@/components/rfq-form';
import { VerifiedMark } from '@/components/storefront/verified-mark';
import { hueStyle, initials } from '@/lib/hue';
import { home, login } from '@/routes';
import cartRoutes from '@/routes/cart';
import { update as updateLocation } from '@/routes/location';
import { index as productsIndex } from '@/routes/products';
import { create as requestQuote } from '@/routes/rfqs';
import { index as shopsIndex, show as shopShow } from '@/routes/shops';
import { show as supplierShow } from '@/routes/suppliers';
import type {
    CompanySummary,
    CustomerLocation,
    DeliveryEstimate,
    Neighbourhood,
    ProductCard as ProductCardData,
    SharedData,
} from '@/types';

type Supplier = CompanySummary & {
    listed_products: number;
    response_time: string | null;
    top_categories: string[];
};

type LocalItem = {
    id: number;
    name: string;
    category: { name: string; slug: string };
    price: string;
    price_listed: string | null;
    image_url: string | null;
    /** Units left; the quantity cannot go above it. */
    stock: number;
    stock_status: string;
    stock_label: string;
    units_sold: number;
    shop: {
        name: string;
        slug: string;
        is_open: boolean;
        offers_pickup: boolean;
        offers_delivery: boolean;
        base_fee: string;
        delivery: DeliveryEstimate | null;
    };
};

type ProductFilters = {
    verified: boolean;
    max_moq: string;
    sort: string;
    category: string;
};

type Props = {
    stats: {
        verified_suppliers: number;
        listed_products: number;
        countries: number;
    };
    categories: {
        id: number;
        name: string;
        slug: string;
        product_count: number;
        children?: { name: string; slug: string; product_count: number }[];
    }[];
    /** Inertia::scroll() pages: "Show more" appends the next one. */
    products: { data: ProductCardData[] };
    productFilters: ProductFilters;
    localItems: { data: LocalItem[] };
    shops: { data: ShopCardData[] };
    shopFilters: { delivers: boolean };
    location: CustomerLocation | null;
    neighbourhoods: Neighbourhood[];
    roadFactor: number;
    suppliers: { data: Supplier[] };
    rfqForm: RfqFormOptions;
};

const sorts = [
    { value: 'relevance', label: 'Best match' },
    { value: 'price', label: 'Lowest price' },
    { value: 'moq', label: 'Lowest MOQ' },
    { value: 'newest', label: 'Newest' },
];

function SectionHeading({
    title,
    text,
    aside,
}: {
    title: string;
    text?: string;
    aside?: React.ReactNode;
}) {
    return (
        <div className="mb-[18px] flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 className="text-[clamp(24px,3vw,34px)] font-bold">
                    {title}
                </h2>
                {text && (
                    <p className="mt-1.5 max-w-[52ch] text-[var(--sf-muted)]">
                        {text}
                    </p>
                )}
            </div>
            {aside}
        </div>
    );
}

export default function Welcome(props: Props) {
    const {
        stats,
        categories,
        products,
        productFilters,
        localItems,
        shops,
        shopFilters,
        location,
        neighbourhoods,
        roadFactor,
        suppliers,
        rfqForm,
    } = props;

    /** Reload one section, keeping the other section's filters in the URL. */
    const reload = (
        query: Record<string, string | number | undefined>,
        only: string[],
    ) => {
        const current = {
            verified: productFilters.verified ? 1 : undefined,
            max_moq: productFilters.max_moq || undefined,
            sort:
                productFilters.sort !== 'relevance'
                    ? productFilters.sort
                    : undefined,
            category: productFilters.category || undefined,
            delivers: shopFilters.delivers ? 1 : undefined,
        };
        const next = Object.fromEntries(
            Object.entries({ ...current, ...query }).filter(
                ([, value]) => value !== undefined && value !== '',
            ),
        );

        router.get(home.url(), next, {
            only,
            // Filters start the lists again from their first page.
            reset: only,
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Wholesale and local shops" />
            <Hero stats={stats} categories={categories} />
            <LocalSection
                items={localItems.data}
                delivers={shopFilters.delivers}
                location={location}
                neighbourhoods={neighbourhoods}
                roadFactor={roadFactor}
                onDelivers={(value) =>
                    reload({ delivers: value ? 1 : undefined }, [
                        'localItems',
                        'shops',
                        'shopFilters',
                    ])
                }
            />
            <ShopsSection shops={shops.data} />
            <CategoriesSection categories={categories} />
            <ProductsSection
                products={products.data}
                categories={categories}
                filters={productFilters}
                onChange={(query) =>
                    reload(query, ['products', 'productFilters'])
                }
            />
            <SuppliersSection suppliers={suppliers.data} />
            <RfqSection rfqForm={rfqForm} />
        </>
    );
}

function Hero({
    stats,
    categories,
}: {
    stats: Props['stats'];
    categories: Props['categories'];
}) {
    const [product, setProduct] = useState('');
    const [quantity, setQuantity] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            requestQuote.url(),
            Object.fromEntries(
                Object.entries({ title: product.trim(), quantity }).filter(
                    ([, value]) => value !== '',
                ),
            ),
        );
    };

    return (
        <section aria-labelledby="hero-heading" className="pt-2 pb-2">
            <div className="grid items-stretch gap-4 md:grid-cols-[200px_minmax(0,1fr)] lg:grid-cols-[230px_minmax(0,1fr)_300px]">
                <nav
                    aria-label="Categories"
                    className="sf-panel sf-rail flex gap-1 overflow-x-auto p-1.5 md:flex-col md:p-2"
                >
                    {categories.map((category) => (
                        <Link
                            key={category.id}
                            href={productsIndex({
                                query: { category: category.slug },
                            })}
                            className="flex-none whitespace-nowrap md:whitespace-normal"
                        >
                            {category.name}
                            <span className="hidden md:inline">
                                {category.product_count}
                            </span>
                        </Link>
                    ))}
                </nav>

                <div className="sf-banner flex flex-col justify-between gap-7">
                    <div>
                        <p className="sf-eyebrow !text-[var(--sf-bg)] opacity-80">
                            Wholesale, sourced locally and globally
                        </p>
                        <h1
                            id="hero-heading"
                            className="mt-3 max-w-[14ch] text-[clamp(30px,4.2vw,52px)] font-extrabold"
                        >
                            Buy in bulk from suppliers you can check.
                        </h1>
                    </div>
                    <p className="max-w-[44ch] text-base opacity-90">
                        Compare tiered prices, minimum orders and lead times
                        from verified suppliers, then send one inquiry to
                        several suppliers at once.
                    </p>
                    <div className="flex flex-wrap gap-2.5">
                        <a href="#local" className="sf-btn sf-btn-mark">
                            Shop local
                        </a>
                        <a
                            href="#products"
                            className="sf-btn sf-btn-ghost-dark"
                        >
                            Browse wholesale
                        </a>
                        <a href="#rfq" className="sf-btn sf-btn-ghost-dark">
                            Post a request
                        </a>
                    </div>
                    <dl className="flex flex-wrap gap-7">
                        {[
                            {
                                label: 'Verified suppliers',
                                value: stats.verified_suppliers,
                            },
                            {
                                label: 'Listed products',
                                value: stats.listed_products,
                            },
                            { label: 'Countries', value: stats.countries },
                        ].map((stat) => (
                            <div
                                key={stat.label}
                                className="flex flex-col-reverse"
                            >
                                <dt className="sf-mono text-xs tracking-[0.05em] uppercase opacity-75">
                                    {stat.label}
                                </dt>
                                <dd className="sf-display text-[26px] leading-none font-bold">
                                    {stat.value.toLocaleString()}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <form
                    onSubmit={submit}
                    className="sf-panel flex flex-col gap-2.5 p-[18px] md:col-span-2 lg:col-span-1"
                    aria-labelledby="quick-heading"
                >
                    <h3 id="quick-heading" className="text-xl font-bold">
                        Get quotes in 48 hours
                    </h3>
                    <p className="text-[13.5px] text-[var(--sf-muted)]">
                        Tell us what you need. Matching suppliers reply with
                        price and lead time.
                    </p>
                    <label className="sf-label" htmlFor="quick-product">
                        Product
                        <input
                            id="quick-product"
                            className="sf-input"
                            required
                            value={product}
                            onChange={(event) => setProduct(event.target.value)}
                            placeholder="e.g. roasted coffee, 500 kg"
                            maxLength={200}
                        />
                    </label>
                    <label className="sf-label" htmlFor="quick-quantity">
                        Quantity
                        <input
                            id="quick-quantity"
                            className="sf-input"
                            type="number"
                            min={1}
                            required
                            value={quantity}
                            onChange={(event) =>
                                setQuantity(event.target.value)
                            }
                            placeholder="500"
                        />
                    </label>
                    <button
                        type="submit"
                        className="sf-btn sf-btn-primary mt-1"
                    >
                        Request quotes
                    </button>
                </form>
            </div>
        </section>
    );
}

function LocalSection({
    items,
    delivers,
    location,
    neighbourhoods,
    roadFactor,
    onDelivers,
}: {
    items: LocalItem[];
    delivers: boolean;
    location: CustomerLocation | null;
    neighbourhoods: Neighbourhood[];
    roadFactor: number;
    onDelivers: (value: boolean) => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const [chip, setChip] = useState('all');
    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const [needsArea, setNeedsArea] = useState(false);

    const chips = useMemo(
        () => [
            ...new Map(
                items.map((item) => [item.category.slug, item.category.name]),
            ).entries(),
        ],
        [items],
    );
    const visible = items.filter(
        (item) => chip === 'all' || item.category.slug === chip,
    );
    const currentArea =
        neighbourhoods.find((area) => area.name === location?.label)?.name ??
        '';

    const chooseArea = (name: string) => {
        const area = neighbourhoods.find((item) => item.name === name);

        if (area) {
            setNeedsArea(false);
            router.put(
                updateLocation.url(),
                { label: area.name, lat: area.lat, lng: area.lng },
                { preserveScroll: true },
            );
        }
    };

    const toggleDelivers = (checked: boolean) => {
        // Filtering by delivery area needs an area first.
        if (checked && !location) {
            setNeedsArea(true);
            document.getElementById('area')?.focus();

            return;
        }

        onDelivers(checked);
    };

    const { openLocalOrder } = useStorefrontPanels();

    const add = (item: LocalItem) => {
        if (!auth.user) {
            router.visit(login.url());

            return;
        }

        router.post(
            cartRoutes.items.store.url(item.id),
            { quantity: quantities[item.id] ?? 1 },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setQuantities({ ...quantities, [item.id]: 1 });
                    openLocalOrder();
                },
            },
        );
    };

    return (
        <section
            id="local"
            aria-labelledby="local-heading"
            className="scroll-mt-28 pt-10 pb-2"
        >
            <SectionHeading
                title="Local shops"
                text="Order one or more from shops near you. Collect in person or have it delivered, with the fee set by distance."
                aside={
                    <label className="sf-label min-w-[220px]" htmlFor="area">
                        Deliver to
                        <select
                            id="area"
                            className="sf-input"
                            value={currentArea}
                            onChange={(event) => chooseArea(event.target.value)}
                            aria-describedby={
                                needsArea ? 'area-hint' : undefined
                            }
                        >
                            <option value="" disabled>
                                {location ? location.label : 'Choose your area'}
                            </option>
                            {neighbourhoods.map((area) => (
                                <option key={area.name} value={area.name}>
                                    {area.name}
                                </option>
                            ))}
                        </select>
                    </label>
                }
            />
            <div className="sf-filters mb-4">
                <div
                    className="flex min-w-0 flex-[1_1_320px] flex-wrap gap-1.5"
                    role="group"
                    aria-label="Filter local items"
                >
                    <button
                        type="button"
                        className="sf-chip"
                        aria-pressed={chip === 'all'}
                        onClick={() => setChip('all')}
                    >
                        All
                    </button>
                    {chips.map(([slug, name]) => (
                        <button
                            key={slug}
                            type="button"
                            className="sf-chip"
                            aria-pressed={chip === slug}
                            onClick={() => setChip(slug)}
                        >
                            {name}
                        </button>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <label
                        className="inline-flex items-center gap-1.5 text-[13px] font-medium"
                        htmlFor="delivers"
                    >
                        <input
                            id="delivers"
                            type="checkbox"
                            checked={delivers}
                            onChange={(event) =>
                                toggleDelivers(event.target.checked)
                            }
                        />
                        Delivers to my area only
                    </label>
                    <span className="sf-count">
                        {visible.length} of {items.length} items
                    </span>
                </div>
                {needsArea && (
                    <p
                        id="area-hint"
                        role="alert"
                        className="w-full text-[13px] font-medium"
                    >
                        Choose where to deliver above, then filter by shops that
                        deliver there.
                    </p>
                )}
            </div>

            {visible.length === 0 ? (
                <div className="sf-empty">
                    {delivers
                        ? 'No shop delivers to this area yet. Untick the filter to see pickup shops.'
                        : 'No local items yet.'}
                </div>
            ) : (
                <ShowMoreList
                    data="localItems"
                    label="shop items"
                    className="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-3.5"
                >
                    {visible.map((item) => {
                        const delivery = item.shop.delivery;
                        const out = item.stock_status === 'out_of_stock';

                        return (
                            <li key={item.id}>
                                <article className="sf-card h-full">
                                    <Link
                                        href={shopShow(item.shop.slug)}
                                        className="sf-thumb"
                                        style={hueStyle(item.shop.slug)}
                                        aria-label={`${item.name} at ${item.shop.name}`}
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
                                        <span className="sf-tag">
                                            {item.category.name}
                                        </span>
                                        {!item.image_url && (
                                            <span
                                                className="sf-glyph"
                                                aria-hidden="true"
                                            >
                                                {initials(item.category.name)}
                                            </span>
                                        )}
                                    </Link>
                                    <div className="flex flex-1 flex-col gap-2 px-3.5 pt-3 pb-3.5">
                                        <h3 className="text-[16.5px] leading-tight font-semibold tracking-[-0.01em]">
                                            {item.name}
                                        </h3>
                                        <div>
                                            <div className="sf-price">
                                                {item.price}
                                            </div>
                                            <ListedPrice
                                                value={item.price_listed}
                                            />
                                        </div>
                                        <div className="sf-meta">
                                            <Link
                                                href={shopShow(item.shop.slug)}
                                                className="hover:underline"
                                            >
                                                {item.shop.name}
                                            </Link>
                                            <span>
                                                {item.shop.is_open
                                                    ? 'Open now'
                                                    : 'Closed now'}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            {delivery && (
                                                <span className="sf-pill">
                                                    {delivery.distance_km.toFixed(
                                                        1,
                                                    )}{' '}
                                                    km away
                                                </span>
                                            )}
                                            {!item.shop.offers_delivery ? (
                                                <span className="sf-pill sf-pill-no">
                                                    Pickup only
                                                </span>
                                            ) : delivery ? (
                                                delivery.deliverable ? (
                                                    <span className="sf-pill sf-pill-ok">
                                                        Delivery{' '}
                                                        {delivery.formatted_fee}
                                                    </span>
                                                ) : (
                                                    <span className="sf-pill sf-pill-no">
                                                        Pickup only here
                                                    </span>
                                                )
                                            ) : (
                                                <span className="sf-pill">
                                                    Delivery from{' '}
                                                    {item.shop.base_fee}
                                                </span>
                                            )}
                                            <span
                                                className={
                                                    out
                                                        ? 'sf-pill sf-pill-bad'
                                                        : item.stock_status ===
                                                            'low_stock'
                                                          ? 'sf-pill sf-pill-no'
                                                          : 'sf-pill sf-pill-ok'
                                                }
                                            >
                                                {item.stock_label}
                                            </span>
                                            {item.units_sold > 0 && (
                                                <span className="sf-pill">
                                                    {item.units_sold} sold
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-auto flex flex-wrap items-center gap-2">
                                            <div className="sf-step">
                                                <button
                                                    type="button"
                                                    aria-label="Decrease quantity"
                                                    onClick={() =>
                                                        setQuantities({
                                                            ...quantities,
                                                            [item.id]: Math.max(
                                                                1,
                                                                (quantities[
                                                                    item.id
                                                                ] ?? 1) - 1,
                                                            ),
                                                        })
                                                    }
                                                >
                                                    −
                                                </button>
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={
                                                        quantities[item.id] ?? 1
                                                    }
                                                    onChange={(event) =>
                                                        setQuantities({
                                                            ...quantities,
                                                            [item.id]: Math.min(
                                                                Math.max(
                                                                    1,
                                                                    item.stock,
                                                                ),
                                                                Math.max(
                                                                    1,
                                                                    Number.parseInt(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                        15,
                                                                    ) || 1,
                                                                ),
                                                            ),
                                                        })
                                                    }
                                                    aria-label={`Quantity of ${item.name}`}
                                                />
                                                <button
                                                    type="button"
                                                    aria-label="Increase quantity"
                                                    onClick={() =>
                                                        setQuantities({
                                                            ...quantities,
                                                            [item.id]: Math.min(
                                                                Math.max(
                                                                    1,
                                                                    item.stock,
                                                                ),
                                                                (quantities[
                                                                    item.id
                                                                ] ?? 1) + 1,
                                                            ),
                                                        })
                                                    }
                                                >
                                                    +
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                className="sf-btn sf-btn-sm sf-btn-primary"
                                                disabled={out}
                                                onClick={() => add(item)}
                                            >
                                                {out
                                                    ? 'Out of stock'
                                                    : 'Add to order'}
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </li>
                        );
                    })}
                </ShowMoreList>
            )}
            <p className="mt-3.5 max-w-[80ch] text-[13px] text-[var(--sf-muted)]">
                Delivery fee is the shop's base fee plus a rate per kilometre
                after the first kilometre, and it is free above each shop's
                order threshold. Distance is the straight line × {roadFactor} to
                allow for roads.{' '}
                <Link
                    href={shopsIndex()}
                    className="font-medium text-[var(--sf-ink)] underline"
                >
                    See all shops
                </Link>
            </p>
        </section>
    );
}

function ShopsSection({ shops }: { shops: ShopCardData[] }) {
    if (shops.length === 0) {
        return null;
    }

    return (
        <section
            id="shops"
            aria-labelledby="shops-heading"
            className="scroll-mt-28 pt-10 pb-2"
        >
            <SectionHeading
                title="Local shops"
                text="Every shop on the marketplace, nearest first once you choose your area."
            />
            <ShowMoreList
                data="shops"
                label="shops"
                className="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3.5"
            >
                {shops.map((shop) => (
                    <li key={shop.id}>
                        <ShopCard shop={shop} />
                    </li>
                ))}
            </ShowMoreList>
        </section>
    );
}

function CategoriesSection({
    categories,
}: {
    categories: Props['categories'];
}) {
    return (
        <section
            id="categories"
            aria-labelledby="categories-heading"
            className="pt-10 pb-2"
        >
            <SectionHeading
                title="Wholesale categories"
                text="Sourcing categories, each with suppliers checked for registration."
            />
            <ul className="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-3">
                {categories.map((category) => (
                    <li key={category.id}>
                        <Link
                            href={productsIndex({
                                query: { category: category.slug },
                            })}
                            className="sf-cat h-full"
                            style={hueStyle(category.slug)}
                        >
                            <span className="sf-ico" aria-hidden="true">
                                {initials(category.name)}
                            </span>
                            <b className="sf-display text-[17px] leading-tight">
                                {category.name}
                            </b>
                            <span className="sf-mono text-xs text-[var(--sf-muted)]">
                                {category.product_count.toLocaleString()}{' '}
                                {category.product_count === 1
                                    ? 'listing'
                                    : 'listings'}
                            </span>
                        </Link>
                        {(category.children?.length ?? 0) > 0 && (
                            <ul className="mt-1.5 flex flex-wrap gap-1 px-1">
                                {category.children?.map((child) => (
                                    <li key={child.slug}>
                                        <Link
                                            href={productsIndex({
                                                query: { category: child.slug },
                                            })}
                                            className="sf-pill hover:underline"
                                        >
                                            {child.name}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

function ProductsSection({
    products,
    categories,
    filters,
    onChange,
}: {
    products: ProductCardData[];
    categories: Props['categories'];
    filters: ProductFilters;
    onChange: (query: Record<string, string | number | undefined>) => void;
}) {
    const browseAll = productsIndex({
        query: Object.fromEntries(
            Object.entries({
                verified: filters.verified ? 1 : undefined,
                max_moq: filters.max_moq || undefined,
                sort: filters.sort !== 'relevance' ? filters.sort : undefined,
                category: filters.category || undefined,
            }).filter(([, value]) => value !== undefined),
        ),
    });

    return (
        <section
            id="products"
            aria-labelledby="products-heading"
            className="scroll-mt-28 pt-10 pb-2"
        >
            <SectionHeading
                title="Wholesale products"
                text="Unit price drops as your quantity moves through each tier. Open a product to see them all."
                aside={
                    <Link href={browseAll} className="sf-count underline">
                        Browse all products
                    </Link>
                }
            />
            <div className="sf-filters mb-4">
                <div
                    className="flex min-w-0 flex-[1_1_320px] flex-wrap gap-1.5"
                    role="group"
                    aria-label="Filter by category"
                >
                    <button
                        type="button"
                        className="sf-chip"
                        aria-pressed={filters.category === ''}
                        onClick={() => onChange({ category: undefined })}
                    >
                        All
                    </button>
                    {categories.map((category) => (
                        <button
                            key={category.id}
                            type="button"
                            className="sf-chip"
                            aria-pressed={filters.category === category.slug}
                            onClick={() =>
                                onChange({ category: category.slug })
                            }
                        >
                            {category.name}
                        </button>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-3 text-[13px] font-medium">
                    <label
                        className="inline-flex items-center gap-1.5"
                        htmlFor="vonly"
                    >
                        <input
                            id="vonly"
                            type="checkbox"
                            checked={filters.verified}
                            onChange={(event) =>
                                onChange({
                                    verified: event.target.checked
                                        ? 1
                                        : undefined,
                                })
                            }
                        />
                        Verified only
                    </label>
                    <label
                        className="inline-flex items-center gap-1.5"
                        htmlFor="moq"
                    >
                        Max MOQ
                        <select
                            id="moq"
                            value={filters.max_moq}
                            onChange={(event) =>
                                onChange({
                                    max_moq: event.target.value || undefined,
                                })
                            }
                        >
                            <option value="">Any</option>
                            {['10', '100', '500', '1000'].map((value) => (
                                <option key={value} value={value}>
                                    {Number(value).toLocaleString()}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label
                        className="inline-flex items-center gap-1.5"
                        htmlFor="sort"
                    >
                        Sort
                        <select
                            id="sort"
                            value={filters.sort}
                            onChange={(event) =>
                                onChange({
                                    sort:
                                        event.target.value === 'relevance'
                                            ? undefined
                                            : event.target.value,
                                })
                            }
                        >
                            {sorts.map((sort) => (
                                <option key={sort.value} value={sort.value}>
                                    {sort.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </div>
            {products.length === 0 ? (
                <div className="sf-empty">
                    No listings match these filters. Clear a filter or{' '}
                    <a href="#rfq" className="underline">
                        post a request
                    </a>{' '}
                    and suppliers will come to you.
                </div>
            ) : (
                <ShowMoreList
                    data="products"
                    label="products"
                    className="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-3.5"
                >
                    {products.map((product) => (
                        <li key={product.id}>
                            <ProductCard product={product} />
                        </li>
                    ))}
                </ShowMoreList>
            )}
        </section>
    );
}

function SuppliersSection({ suppliers }: { suppliers: Supplier[] }) {
    if (suppliers.length === 0) {
        return null;
    }

    return (
        <section
            id="suppliers"
            aria-labelledby="suppliers-heading"
            className="pt-10 pb-2"
        >
            <SectionHeading
                title="Suppliers"
                text="Each profile shows registration status, response time and what they ship most."
            />
            <ShowMoreList
                data="suppliers"
                label="suppliers"
                className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-3.5"
            >
                {suppliers.map((supplier) => (
                    <li key={supplier.id}>
                        <article
                            className="sf-panel flex h-full min-w-0 flex-col gap-3 p-4"
                            style={hueStyle(supplier.slug)}
                        >
                            <div className="flex items-center gap-3">
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
                                <div className="min-w-0">
                                    <h3 className="text-[17px]">
                                        <Link
                                            href={supplierShow(supplier.slug)}
                                            className="hover:underline"
                                        >
                                            {supplier.name}
                                        </Link>
                                    </h3>
                                    <div className="sf-meta">
                                        {[supplier.city, supplier.country]
                                            .filter(Boolean)
                                            .join(', ')}
                                    </div>
                                </div>
                            </div>
                            <div>
                                {supplier.is_verified ? (
                                    <VerifiedMark label="Verified supplier" />
                                ) : (
                                    <span className="sf-meta">
                                        {supplier.verification_status ===
                                        'pending'
                                            ? 'Verification pending'
                                            : 'Not verified yet'}
                                    </span>
                                )}
                            </div>
                            <dl className="sf-dl grid grid-cols-2 gap-x-3 gap-y-2">
                                <div>
                                    <dt>On the marketplace since</dt>
                                    <dd>
                                        {supplier.created_at
                                            ? new Date(
                                                  supplier.created_at,
                                              ).getFullYear()
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Reply time</dt>
                                    <dd>{supplier.response_time ?? 'New'}</dd>
                                </div>
                                <div>
                                    <dt>Listings</dt>
                                    <dd>{supplier.listed_products}</dd>
                                </div>
                                <div>
                                    <dt>Country</dt>
                                    <dd>{supplier.country}</dd>
                                </div>
                            </dl>
                            {supplier.top_categories.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    <span className="sr-only">
                                        Ships most:{' '}
                                    </span>
                                    {supplier.top_categories.map((category) => (
                                        <span
                                            key={category}
                                            className="rounded bg-[var(--sf-surface-2)] px-2 py-0.5 text-xs"
                                        >
                                            {category}
                                        </span>
                                    ))}
                                </div>
                            )}
                            <Link
                                href={supplierShow(supplier.slug)}
                                className="sf-btn mt-auto"
                            >
                                View supplier
                            </Link>
                        </article>
                    </li>
                ))}
            </ShowMoreList>
        </section>
    );
}

function RfqSection({ rfqForm }: { rfqForm: RfqFormOptions }) {
    return (
        <section
            id="rfq"
            aria-labelledby="rfq-heading"
            className="sf-band mt-12 grid scroll-mt-28 gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]"
        >
            <div>
                <p className="sf-eyebrow !text-[var(--sf-bg)] opacity-75">
                    Request for quotation
                </p>
                <h2
                    id="rfq-heading"
                    className="mt-2.5 text-[clamp(26px,3.4vw,40px)] font-extrabold"
                >
                    One request, several quotes.
                </h2>
                <p className="mt-3 max-w-[40ch] opacity-85">
                    Describe the order once. Suppliers who fit your category and
                    quantity respond with a price per tier, lead time and sample
                    terms.
                </p>
                <ol className="mt-5 grid list-decimal gap-2 pl-5 opacity-90">
                    {rfqSteps.map((step) => (
                        <li key={step.title}>{step.text}</li>
                    ))}
                </ol>
                <p className="mt-5 text-sm opacity-75">
                    No account needed to send a request.
                </p>
            </div>
            <div className="sf-band-form">
                <RfqForm
                    defaults={emptyRfq}
                    categories={rfqForm.categories}
                    units={rfqForm.units}
                />
            </div>
        </section>
    );
}
