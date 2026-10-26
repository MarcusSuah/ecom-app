import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Trash2,
} from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import type { FormEvent, ReactNode } from 'react';
import { CartGroups } from '@/components/cart/cart-groups';
import type { CartGroup } from '@/components/cart/cart-groups';
import InputError from '@/components/input-error';
import { LocationPicker } from '@/components/location-picker';
import { OpenChatButton } from '@/components/open-chat-button';
import { StockBadge } from '@/components/stock-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { hueStyle, initials } from '@/lib/hue';
import { inquiryList, tierPrice, useInquiryList } from '@/lib/inquiry-list';
import type { InquiryItem } from '@/lib/inquiry-list';
import { useMoney } from '@/lib/money';
import { checkout, login } from '@/routes';
import cartRoutes from '@/routes/cart';
import { store as storeInquiry } from '@/routes/inquiries';
import productRoutes from '@/routes/products';
import type { CustomerLocation, Neighbourhood, SharedData } from '@/types';

type Panels = {
    /** Product details over the current page. */
    openProduct: (slug: string) => void;
    openInquiryList: () => void;
    openLocalOrder: () => void;
};

const PanelsContext = createContext<Panels | null>(null);

/**
 * Opens the storefront's product modal and drawers. Outside the storefront
 * (no provider) it falls back to the full pages.
 */
export function useStorefrontPanels(): Panels {
    const panels = useContext(PanelsContext);

    return (
        panels ?? {
            openProduct: (slug) => router.visit(productRoutes.show.url(slug)),
            openInquiryList: () => router.visit(productRoutes.index.url()),
            openLocalOrder: () => router.visit(cartRoutes.show.url()),
        }
    );
}

/**
 * Keeps shoppers on the page they are browsing: product details open in a
 * modal, and the inquiry list (wholesale) and local order (cart) in drawers,
 * like the storefront template.
 */
export function StorefrontPanelsProvider({
    children,
}: {
    children: ReactNode;
}) {
    const [productSlug, setProductSlug] = useState<string | null>(null);
    const [inquiryOpen, setInquiryOpen] = useState(false);
    const [localOrderOpen, setLocalOrderOpen] = useState(false);
    const { auth } = usePage<SharedData>().props;

    useEffect(
        () =>
            router.on('navigate', () => {
                setProductSlug(null);
                setInquiryOpen(false);
                setLocalOrderOpen(false);
            }),
        [],
    );

    const panels = useMemo<Panels>(
        () => ({
            openProduct: (slug) => setProductSlug(slug),
            openInquiryList: () => {
                setProductSlug(null);
                setInquiryOpen(true);
            },
            openLocalOrder: () => {
                if (!auth.user) {
                    router.visit(login());

                    return;
                }

                setLocalOrderOpen(true);
            },
        }),
        [auth.user],
    );

    return (
        <PanelsContext.Provider value={panels}>
            {children}
            <ProductModal
                slug={productSlug}
                onClose={() => setProductSlug(null)}
                onAdded={() => {
                    setProductSlug(null);
                    setInquiryOpen(true);
                }}
            />
            <InquiryDrawer open={inquiryOpen} onOpenChange={setInquiryOpen} />
            {auth.user && (
                <LocalOrderDrawer
                    open={localOrderOpen}
                    onOpenChange={setLocalOrderOpen}
                />
            )}
        </PanelsContext.Provider>
    );
}

/**
 * The product's photos: the chosen one large (arrows to step through),
 * every photo as a thumbnail underneath to pick from.
 */
export function ProductGallery({
    images,
    active,
    onSelect,
    name,
    hueKey,
    category,
}: {
    images: { id: number; url: string; thumbnail_url: string }[];
    active: number;
    onSelect: (index: number) => void;
    name: string;
    hueKey: string;
    category: string;
}) {
    const current = images[active];
    const step = (by: number) =>
        onSelect((active + by + images.length) % images.length);

    return (
        <div className="flex flex-col gap-3 bg-[var(--sf-surface-2)] p-4">
            <div
                className="sf-thumb relative aspect-[4/3] overflow-hidden rounded-lg"
                style={hueStyle(hueKey)}
            >
                {current ? (
                    <img
                        src={current.url}
                        alt={`${name}, photo ${active + 1} of ${images.length}`}
                    />
                ) : (
                    <span className="sf-glyph">{initials(category)}</span>
                )}
                <span className="sf-tag">{category}</span>
                {images.length > 1 && (
                    <>
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            aria-label="Previous photo"
                            className="absolute top-1/2 left-2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[var(--sf-ink)] shadow hover:bg-white"
                        >
                            <ChevronLeft
                                className="size-5"
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            aria-label="Next photo"
                            className="absolute top-1/2 right-2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[var(--sf-ink)] shadow hover:bg-white"
                        >
                            <ChevronRight
                                className="size-5"
                                aria-hidden="true"
                            />
                        </button>
                        <span className="sf-mono absolute top-2 right-2 z-10 rounded bg-black/60 px-2 py-0.5 text-xs text-white">
                            {active + 1} / {images.length}
                        </span>
                    </>
                )}
            </div>
            {images.length > 1 && (
                <ul
                    className="grid grid-cols-5 gap-2"
                    aria-label="Choose a photo"
                >
                    {images.map((picture, index) => (
                        <li key={picture.id}>
                            <button
                                type="button"
                                onClick={() => onSelect(index)}
                                aria-label={`Show photo ${index + 1}`}
                                aria-pressed={index === active}
                                className="block aspect-square w-full overflow-hidden rounded-md border-2 border-transparent opacity-70 transition hover:opacity-100 aria-pressed:border-[var(--sf-accent)] aria-pressed:opacity-100"
                            >
                                <img
                                    src={picture.thumbnail_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return (await response.json()) as T;
}

type QuickView = {
    url: string;
    product: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        unit: string;
        unit_label: string;
        moq: number;
        lead_time: string;
        currency: string;
        origin_country: string | null;
        sample_available: boolean;
        sample_price: string | null;
        stock_status: string;
        stock: number | null;
        stock_label: string;
        units_sold: number;
        category: { name: string; slug: string; parent: string | null };
        tiers: { min_qty: number; unit_price: number }[];
        images: { id: number; url: string; thumbnail_url: string }[];
    };
    supplier: {
        name: string;
        slug: string;
        city: string | null;
        country: string | null;
        is_verified: boolean;
    };
};

function ProductModal({
    slug,
    onClose,
    onAdded,
}: {
    slug: string | null;
    onClose: () => void;
    onAdded: () => void;
}) {
    const { format, listed } = useMoney();
    const [data, setData] = useState<QuickView | null>(null);
    const [failed, setFailed] = useState(false);
    const [quantity, setQuantity] = useState('');
    const [image, setImage] = useState(0);

    useEffect(() => {
        if (!slug) {
            return;
        }

        setData(null);
        setFailed(false);
        setImage(0);
        getJson<QuickView>(productRoutes.quickView.url(slug))
            .then((result) => {
                setData(result);
                setQuantity(String(result.product.moq));
            })
            .catch(() => setFailed(true));
    }, [slug]);

    const product = data?.product;
    const parsed = Number.parseInt(quantity, 10) || 0;
    // At least the MOQ and at most what is in stock (no limit when made to order).
    const effective = product
        ? Math.min(
              Math.max(parsed, product.moq),
              product.stock ?? Number.POSITIVE_INFINITY,
          )
        : 0;
    const unitPrice = product ? tierPrice(product, effective) : 0;
    const activeTier = product
        ? product.tiers.reduce(
              (found, tier, index) =>
                  effective >= tier.min_qty ? index : found,
              -1,
          )
        : -1;
    const canOrder = product ? product.stock_status !== 'out_of_stock' : false;

    const add = () => {
        if (!data || !product) {
            return;
        }

        inquiryList.add({
            product_id: product.id,
            slug: product.slug,
            name: product.name,
            supplier: data.supplier.name,
            supplier_slug: data.supplier.slug,
            unit: product.unit,
            moq: product.moq,
            currency: product.currency,
            tiers: product.tiers.map((tier) => ({
                min_qty: tier.min_qty,
                unit_price: tier.unit_price,
            })),
            image_url: product.images[0]?.thumbnail_url ?? null,
            quantity: effective,
            stock: product.stock,
        });
        onAdded();
    };

    return (
        <Dialog
            open={slug !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="storefront max-h-[92vh] overflow-y-auto p-0 sm:max-w-4xl">
                {!data ? (
                    <div className="flex min-h-60 flex-col items-center justify-center gap-2 p-8 text-sm text-muted-foreground">
                        <DialogTitle className="sr-only">
                            Product details
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            Loading the product
                        </DialogDescription>
                        {failed ? (
                            'This product could not be loaded.'
                        ) : (
                            <>
                                <Spinner /> Loading…
                            </>
                        )}
                    </div>
                ) : (
                    product && (
                        <div className="grid md:grid-cols-2">
                            <ProductGallery
                                images={product.images}
                                active={image}
                                onSelect={setImage}
                                name={product.name}
                                hueKey={product.category.slug}
                                category={product.category.name}
                            />
                            <div className="space-y-4 p-6">
                                <div className="space-y-1 pr-8">
                                    <DialogTitle className="text-2xl font-bold">
                                        {product.name}
                                    </DialogTitle>
                                    <DialogDescription className="flex flex-wrap gap-x-3 text-sm">
                                        <span>{data.supplier.name}</span>
                                        {(data.supplier.city ||
                                            data.supplier.country) && (
                                            <span>
                                                {[
                                                    data.supplier.city,
                                                    data.supplier.country?.toUpperCase(),
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </span>
                                        )}
                                        {data.supplier.is_verified && (
                                            <span className="inline-flex items-center gap-1 font-medium text-[var(--sf-good)]">
                                                <BadgeCheck
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Verified
                                            </span>
                                        )}
                                    </DialogDescription>
                                </div>

                                <StockBadge
                                    status={product.stock_status}
                                    label={product.stock_label}
                                    sold={product.units_sold}
                                />

                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left font-mono text-xs tracking-wide text-muted-foreground uppercase">
                                            <th className="py-2 font-normal">
                                                Quantity
                                            </th>
                                            <th className="py-2 font-normal">
                                                Price per {product.unit}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {product.tiers.map((tier, index) => {
                                            const next =
                                                product.tiers[index + 1];

                                            return (
                                                <tr
                                                    key={tier.min_qty}
                                                    className={
                                                        index === activeTier
                                                            ? 'bg-amber-100 font-semibold dark:bg-amber-950'
                                                            : 'border-b'
                                                    }
                                                    aria-current={
                                                        index === activeTier
                                                            ? 'true'
                                                            : undefined
                                                    }
                                                >
                                                    <td className="px-2 py-2">
                                                        {tier.min_qty.toLocaleString()}
                                                        {next
                                                            ? ` to ${(next.min_qty - 1).toLocaleString()}`
                                                            : '+'}
                                                    </td>
                                                    <td className="px-2 py-2 tabular-nums">
                                                        {format(
                                                            tier.unit_price,
                                                            product.currency,
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>

                                <div className="flex flex-wrap items-end justify-between gap-4">
                                    <div className="grid gap-1">
                                        <Label htmlFor="quick-quantity">
                                            Quantity ({product.unit})
                                        </Label>
                                        <Input
                                            id="quick-quantity"
                                            type="number"
                                            inputMode="numeric"
                                            min={product.moq}
                                            max={product.stock ?? undefined}
                                            value={quantity}
                                            onChange={(event) =>
                                                setQuantity(event.target.value)
                                            }
                                            className="w-36"
                                        />
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm text-muted-foreground">
                                            Estimated total
                                        </p>
                                        <p
                                            className="text-2xl font-bold tabular-nums"
                                            aria-live="polite"
                                        >
                                            {format(
                                                unitPrice * effective,
                                                product.currency,
                                            )}
                                        </p>
                                        {listed(
                                            unitPrice * effective,
                                            product.currency,
                                        ) && (
                                            <p className="text-xs text-muted-foreground">
                                                Charged{' '}
                                                {listed(
                                                    unitPrice * effective,
                                                    product.currency,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Minimum order is{' '}
                                    {product.moq.toLocaleString()}{' '}
                                    {product.unit}.
                                    {parsed > 0 && parsed < product.moq
                                        ? ' Smaller quantities are raised to the minimum.'
                                        : ''}
                                    {product.stock !== null &&
                                    parsed > product.stock
                                        ? ` Only ${product.stock.toLocaleString()} ${product.unit} in stock, so the total uses that.`
                                        : ''}{' '}
                                    Freight and duties are quoted by the
                                    supplier.
                                </p>

                                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <dt className="text-muted-foreground">
                                        Category
                                    </dt>
                                    <dd className="font-medium">
                                        {product.category.parent
                                            ? `${product.category.parent} › `
                                            : ''}
                                        {product.category.name}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Lead time
                                    </dt>
                                    <dd className="font-medium">
                                        {product.lead_time}
                                    </dd>
                                    {product.origin_country && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Origin
                                            </dt>
                                            <dd className="font-medium">
                                                {product.origin_country.toUpperCase()}
                                            </dd>
                                        </>
                                    )}
                                    <dt className="text-muted-foreground">
                                        Samples
                                    </dt>
                                    <dd className="font-medium">
                                        {product.sample_available
                                            ? (product.sample_price ??
                                              'Available')
                                            : 'Not offered'}
                                    </dd>
                                </dl>
                                {product.description && (
                                    <p className="line-clamp-4 text-sm whitespace-pre-line text-muted-foreground">
                                        {product.description}
                                    </p>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        onClick={add}
                                        disabled={!canOrder}
                                    >
                                        {canOrder
                                            ? 'Add to inquiry list'
                                            : 'Out of stock'}
                                    </Button>
                                    <OpenChatButton
                                        company={data.supplier.slug}
                                        productId={product.id}
                                        label="Contact supplier"
                                    />
                                    <Button variant="ghost" asChild>
                                        <Link href={data.url} onClick={onClose}>
                                            <ExternalLink aria-hidden="true" />
                                            Full page
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )
                )}
            </DialogContent>
        </Dialog>
    );
}

function InquiryDrawer({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const items = useInquiryList();
    const { auth } = usePage<SharedData>().props;
    const { format } = useMoney();
    const [contact, setContact] = useState({
        deliver_city: '',
        notes: '',
        guest_name: '',
        guest_email: '',
        guest_phone: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    useEffect(() => {
        if (auth.user?.phone) {
            setContact((current) =>
                current.guest_phone
                    ? current
                    : { ...current, guest_phone: auth.user?.phone ?? '' },
            );
        }
    }, [auth.user?.phone]);

    const totals = items.reduce<Record<string, number>>((sums, item) => {
        sums[item.currency] =
            (sums[item.currency] ?? 0) +
            tierPrice(item, item.quantity) * item.quantity;

        return sums;
    }, {});
    const suppliers = new Set(items.map((item) => item.supplier_slug)).size;

    const send = (event: FormEvent) => {
        event.preventDefault();
        setSending(true);
        router.post(
            storeInquiry.url(),
            {
                ...contact,
                items: items.map((item) => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    inquiryList.clear();
                    setErrors({});
                    onOpenChange(false);
                },
                onError: (response) => setErrors(response),
                onFinish: () => setSending(false),
            },
        );
    };

    const field = (
        name: keyof typeof contact,
        label: string,
        props: Record<string, unknown> = {},
    ) => (
        <div className="grid gap-1">
            <Label htmlFor={`inquiry-${name}`}>{label}</Label>
            <Input
                id={`inquiry-${name}`}
                value={contact[name]}
                onChange={(event) =>
                    setContact({ ...contact, [name]: event.target.value })
                }
                aria-invalid={Boolean(errors[name])}
                required
                {...props}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="storefront flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-5">
                    <SheetTitle className="text-2xl font-bold">
                        Inquiry list
                    </SheetTitle>
                    <SheetDescription>
                        One request goes to each supplier. They reply with final
                        prices and lead times.
                    </SheetDescription>
                </SheetHeader>
                <form onSubmit={send} className="flex min-h-0 flex-1 flex-col">
                    <div className="flex-1 space-y-4 overflow-y-auto p-5">
                        {items.length === 0 ? (
                            <p className="py-6 text-sm text-muted-foreground">
                                Your list is empty. Open a wholesale product and
                                add it to send one inquiry to each supplier.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {items.map((item) => (
                                    <InquiryLine
                                        key={item.product_id}
                                        item={item}
                                    />
                                ))}
                            </ul>
                        )}
                        <InputError
                            message={
                                errors.items ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('items.'),
                                )?.[1]
                            }
                        />

                        {items.length > 0 && (
                            <fieldset className="grid gap-3 rounded-md border p-4">
                                <legend className="px-1 text-sm font-medium">
                                    Delivery and contact
                                </legend>
                                {field('deliver_city', 'Deliver to (city)', {
                                    autoComplete: 'address-level2',
                                })}
                                {!auth.user &&
                                    field('guest_name', 'Your name', {
                                        autoComplete: 'name',
                                    })}
                                {!auth.user &&
                                    field('guest_email', 'Email', {
                                        type: 'email',
                                        autoComplete: 'email',
                                    })}
                                {field('guest_phone', 'Phone', {
                                    type: 'tel',
                                    autoComplete: 'tel',
                                })}
                                <div className="grid gap-1">
                                    <Label htmlFor="inquiry-notes">
                                        Notes for suppliers (optional)
                                    </Label>
                                    <Textarea
                                        id="inquiry-notes"
                                        rows={2}
                                        value={contact.notes}
                                        onChange={(event) =>
                                            setContact({
                                                ...contact,
                                                notes: event.target.value,
                                            })
                                        }
                                        placeholder="Packaging, quality, delivery date…"
                                    />
                                </div>
                            </fieldset>
                        )}
                    </div>
                    <SheetFooter className="gap-2 border-t p-5">
                        <div className="flex justify-between text-lg font-bold">
                            <span>Estimated total</span>
                            <span className="tabular-nums">
                                {Object.entries(totals)
                                    .map(([currency, amount]) =>
                                        format(amount, currency),
                                    )
                                    .join(' + ') || format(0, 'USD')}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Quantities below a product’s minimum are raised to
                            the minimum. Suppliers confirm final price, freight
                            and lead time.
                        </p>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={items.length === 0 || sending}
                        >
                            {sending
                                ? 'Sending…'
                                : `Send inquiry to ${suppliers} ${suppliers === 1 ? 'supplier' : 'suppliers'}`}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function InquiryLine({ item }: { item: InquiryItem }) {
    const { format } = useMoney();
    const [draft, setDraft] = useState(String(item.quantity));
    const unit = tierPrice(item, item.quantity);

    useEffect(() => setDraft(String(item.quantity)), [item.quantity]);

    return (
        <li className="flex gap-3 py-4 text-sm">
            {item.image_url ? (
                <img
                    src={item.image_url}
                    alt=""
                    className="size-14 shrink-0 rounded-md object-cover"
                />
            ) : (
                <span
                    aria-hidden="true"
                    className="sf-thumb size-14 shrink-0 rounded-md"
                    style={hueStyle(item.supplier_slug)}
                />
            )}
            <div className="min-w-0 flex-1 space-y-2">
                <div>
                    <p className="font-semibold">{item.name}</p>
                    <p className="text-muted-foreground">
                        {item.supplier} · {format(unit, item.currency)} /{' '}
                        {item.unit}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={item.moq}
                        max={item.stock ?? undefined}
                        value={draft}
                        aria-label={`Quantity of ${item.name}`}
                        className="h-9 w-28"
                        onChange={(event) => {
                            setDraft(event.target.value);
                            const value = Number.parseInt(
                                event.target.value,
                                10,
                            );

                            if (Number.isFinite(value) && value >= item.moq) {
                                inquiryList.setQuantity(item.product_id, value);
                            }
                        }}
                        onBlur={() =>
                            inquiryList.setQuantity(
                                item.product_id,
                                Number.parseInt(draft, 10) || item.moq,
                            )
                        }
                    />
                    <button
                        type="button"
                        className="inline-flex items-center gap-1 text-muted-foreground underline hover:text-foreground"
                        onClick={() => inquiryList.remove(item.product_id)}
                    >
                        <Trash2 className="size-3.5" aria-hidden="true" />
                        Remove
                    </button>
                </div>
            </div>
            <p className="font-semibold tabular-nums">
                {format(unit * item.quantity, item.currency)}
            </p>
        </li>
    );
}

type CartContents = {
    groups: CartGroup[];
    location: CustomerLocation | null;
    savedAddresses: { id: number; label: string }[];
    neighbourhoods: Neighbourhood[];
};

function LocalOrderDrawer({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [contents, setContents] = useState<CartContents | null>(null);
    const [placing, setPlacing] = useState(false);
    const page = usePage<SharedData>().props;
    const errors = page.errors as Record<string, string | undefined>;
    // Changes when items are added elsewhere or the idle cart is emptied.
    const cartCount = page.cartCount;

    const reload = useCallback(() => {
        getJson<CartContents>(cartRoutes.summary.url())
            .then(setContents)
            .catch(() =>
                setContents({
                    groups: [],
                    location: null,
                    savedAddresses: [],
                    neighbourhoods: [],
                }),
            );
    }, []);

    useEffect(() => {
        if (open) {
            reload();
        }
    }, [open, reload, cartCount]);

    const groups = contents?.groups ?? [];
    const hasProblems = groups.some((group) => group.problems.length > 0);
    const needsLocation = groups.some(
        (group) => group.fulfilment === 'delivery',
    );

    const place = () => {
        setPlacing(true);
        router.post(
            checkout.url(),
            {},
            {
                onFinish: () => setPlacing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="storefront flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-5">
                    <SheetTitle className="text-2xl font-bold">
                        Local order
                    </SheetTitle>
                    <SheetDescription>
                        One order per shop, picked up or delivered by distance.
                    </SheetDescription>
                </SheetHeader>
                <div className="flex-1 space-y-4 overflow-y-auto p-5">
                    {contents === null ? (
                        <div className="flex items-center gap-2 py-10 text-sm text-muted-foreground">
                            <Spinner /> Loading your order…
                        </div>
                    ) : groups.length === 0 ? (
                        <p className="py-6 text-sm text-muted-foreground">
                            Your order is empty. Add items from a local shop.
                        </p>
                    ) : (
                        <>
                            <CartGroups
                                groups={groups}
                                onSaved={reload}
                                compact
                            />
                            {needsLocation && (
                                <LocationPicker
                                    location={contents.location}
                                    neighbourhoods={contents.neighbourhoods}
                                    savedAddresses={contents.savedAddresses}
                                    onSaved={reload}
                                />
                            )}
                        </>
                    )}
                    {errors.cart && (
                        <p
                            role="alert"
                            className="text-sm text-red-600 dark:text-red-400"
                        >
                            {errors.cart}
                        </p>
                    )}
                </div>
                <SheetFooter className="gap-2 border-t p-5">
                    {groups.length > 1 && (
                        <p className="text-sm text-muted-foreground">
                            You will place {groups.length} orders, one per shop.
                        </p>
                    )}
                    <Button
                        size="lg"
                        onClick={place}
                        disabled={groups.length === 0 || hasProblems || placing}
                    >
                        {placing ? 'Placing…' : 'Place order'}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
