import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { GalleryPicker } from '@/components/gallery-picker';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show as productShow } from '@/routes/products';
import productRoutes from '@/routes/supplier/products';
import type { ProductStatus } from '@/types';

type Tier = { min_qty: number | string; unit_price: string };

type ExistingProduct = {
    id: number;
    slug: string;
    status: ProductStatus;
    name: string;
    category_id: number;
    description: string | null;
    unit: string;
    moq: number;
    lead_time_min_days: number;
    lead_time_max_days: number;
    currency: string;
    origin_country: string;
    sample_available: boolean;
    sample_price: string;
    tiers: Tier[];
    images: { id: number; url: string; thumbnail_url: string }[];
    stock: string;
    stock_status: string;
    stock_label: string;
    units_sold: number;
};

type Props = {
    product: ExistingProduct | null;
    categories: { id: number; name: string }[];
    units: { value: string; label: string }[];
    currencies: string[];
    maxTiers: number;
    maxImages: number;
    defaults: { currency: string };
    /** Where Cancel goes: the product list, or the product page for staff. */
    backUrl: string;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-invalid:border-destructive';

export default function ProductForm({
    product,
    categories,
    units,
    currencies,
    maxTiers,
    maxImages,
    defaults,
    backUrl,
}: Props) {
    const form = useForm({
        name: product?.name ?? '',
        category_id: product?.category_id ? String(product.category_id) : '',
        description: product?.description ?? '',
        unit: product?.unit ?? 'piece',
        moq: product ? String(product.moq) : '',
        lead_time_min_days: product ? String(product.lead_time_min_days) : '7',
        lead_time_max_days: product ? String(product.lead_time_max_days) : '14',
        currency: product?.currency ?? defaults.currency,
        origin_country: product?.origin_country ?? 'RW',
        sample_available: product?.sample_available ?? false,
        sample_price: product?.sample_price ?? '',
        tiers: product?.tiers.length
            ? product.tiers
            : ([{ min_qty: '', unit_price: '' }] as Tier[]),
        stock: product?.stock ?? '',
        // Photos are sent with the form; see GalleryPicker.
        images: [] as File[],
        remove_images: [] as number[],
        cover: null as string | null,
    });
    const { data, setData, errors, processing } = form;
    const [confirmDelete, setConfirmDelete] = useState(false);
    const tierErrors = errors as Record<string, string | undefined>;
    // Status changes are separate requests; their errors land on the page.
    const pageErrors = usePage().props.errors as Record<
        string,
        string | undefined
    >;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () =>
                form.setData((current) => ({
                    ...current,
                    images: [],
                    remove_images: [],
                    cover: null,
                })),
        };
        // Files need multipart, which PHP only reads on POST, so the update is spoofed.
        form.transform((current) =>
            product && current.images.length > 0
                ? { ...current, _method: 'put' }
                : current,
        );

        if (!product) {
            form.post(productRoutes.store.url(), {
                ...options,
                forceFormData: data.images.length > 0,
            });
        } else if (data.images.length > 0) {
            form.post(productRoutes.update.url(product.slug), {
                ...options,
                forceFormData: true,
            });
        } else {
            form.put(productRoutes.update.url(product.slug), options);
        }
    };

    const setTier = (index: number, key: keyof Tier, value: string) =>
        setData(
            'tiers',
            data.tiers.map((tier, i) =>
                i === index ? { ...tier, [key]: value } : tier,
            ),
        );

    const addTier = () =>
        setData('tiers', [...data.tiers, { min_qty: '', unit_price: '' }]);

    const removeTier = (index: number) =>
        setData(
            'tiers',
            data.tiers.filter((_, i) => i !== index),
        );

    const changeStatus = (status: ProductStatus) =>
        product &&
        router.patch(
            productRoutes.status.url(product.slug),
            { status },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={product ? `Edit ${product.name}` : 'Add product'} />
            <div className="mx-auto w-full max-w-4xl space-y-8 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={product ? product.name : 'Add product'}
                        description="Buyers see your tier prices, MOQ, lead time and sample terms."
                    />
                    {product && (
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                status={
                                    product.status === 'active'
                                        ? 'listed'
                                        : product.status
                                }
                            />
                            {product.status !== 'active' && (
                                <Button onClick={() => changeStatus('active')}>
                                    Publish
                                </Button>
                            )}
                            {product.status === 'active' && (
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href={productShow(product.slug)}>
                                            View listing
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => changeStatus('draft')}
                                    >
                                        Unpublish
                                    </Button>
                                </>
                            )}
                            {product.status !== 'archived' && (
                                <Button
                                    variant="outline"
                                    onClick={() => changeStatus('archived')}
                                >
                                    Archive
                                </Button>
                            )}
                            {product.status === 'draft' && (
                                <Button
                                    variant="ghost"
                                    onClick={() => setConfirmDelete(true)}
                                    aria-label="Delete draft"
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </div>
                    )}
                </div>
                <InputError message={pageErrors.status ?? pageErrors.product} />

                <form onSubmit={submit} className="space-y-8" noValidate>
                    <section
                        aria-labelledby="details-heading"
                        className="space-y-4"
                    >
                        <h2
                            id="details-heading"
                            className="text-lg font-semibold"
                        >
                            Details
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Product name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(errors.name)}
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Category</Label>
                                <select
                                    id="category_id"
                                    className={selectClass}
                                    value={data.category_id}
                                    onChange={(event) =>
                                        setData(
                                            'category_id',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.category_id)}
                                >
                                    <option value="">Choose…</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="origin_country">
                                    Country of origin
                                </Label>
                                <Input
                                    id="origin_country"
                                    maxLength={2}
                                    value={data.origin_country}
                                    onChange={(event) =>
                                        setData(
                                            'origin_country',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                <InputError message={errors.origin_country} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                rows={5}
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>
                    </section>

                    <section
                        aria-labelledby="ordering-heading"
                        className="space-y-4"
                    >
                        <h2
                            id="ordering-heading"
                            className="text-lg font-semibold"
                        >
                            Ordering
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <div className="grid gap-2">
                                <Label htmlFor="unit">Unit</Label>
                                <select
                                    id="unit"
                                    className={selectClass}
                                    value={data.unit}
                                    onChange={(event) =>
                                        setData('unit', event.target.value)
                                    }
                                >
                                    {units.map((unit) => (
                                        <option
                                            key={unit.value}
                                            value={unit.value}
                                        >
                                            {unit.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="moq">Minimum order (MOQ)</Label>
                                <Input
                                    id="moq"
                                    type="number"
                                    min={1}
                                    value={data.moq}
                                    onChange={(event) =>
                                        setData('moq', event.target.value)
                                    }
                                    aria-invalid={Boolean(errors.moq)}
                                />
                                <InputError message={errors.moq} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="stock">
                                    Stock ready to ship{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="stock"
                                    type="number"
                                    min={0}
                                    value={data.stock}
                                    onChange={(event) =>
                                        setData('stock', event.target.value)
                                    }
                                    placeholder="Made to order"
                                    aria-invalid={Boolean(errors.stock)}
                                    aria-describedby="stock-help"
                                />
                                <p
                                    id="stock-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    Leave empty if you produce on order. When
                                    set, orders reduce it and the product shows
                                    out of stock once it cannot fill a minimum
                                    order.
                                </p>
                                <InputError message={errors.stock} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="lead_time_min_days">
                                    Lead time from (days)
                                </Label>
                                <Input
                                    id="lead_time_min_days"
                                    type="number"
                                    min={0}
                                    value={data.lead_time_min_days}
                                    onChange={(event) =>
                                        setData(
                                            'lead_time_min_days',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.lead_time_min_days}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="lead_time_max_days">
                                    Lead time to (days)
                                </Label>
                                <Input
                                    id="lead_time_max_days"
                                    type="number"
                                    min={0}
                                    value={data.lead_time_max_days}
                                    onChange={(event) =>
                                        setData(
                                            'lead_time_max_days',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        errors.lead_time_max_days,
                                    )}
                                />
                                <InputError
                                    message={errors.lead_time_max_days}
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap items-end gap-4">
                            <label className="flex items-center gap-2 pb-2 text-sm">
                                <Checkbox
                                    checked={data.sample_available}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'sample_available',
                                            checked === true,
                                        )
                                    }
                                />
                                Samples available
                            </label>
                            {data.sample_available && (
                                <div className="grid gap-2">
                                    <Label htmlFor="sample_price">
                                        Sample price ({data.currency})
                                    </Label>
                                    <Input
                                        id="sample_price"
                                        inputMode="decimal"
                                        value={data.sample_price}
                                        onChange={(event) =>
                                            setData(
                                                'sample_price',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.sample_price,
                                        )}
                                    />
                                    <InputError message={errors.sample_price} />
                                </div>
                            )}
                        </div>
                    </section>

                    <section
                        aria-labelledby="tiers-heading"
                        className="space-y-4"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h2
                                    id="tiers-heading"
                                    className="text-lg font-semibold"
                                >
                                    Price tiers
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    The first tier starts at your MOQ. Each next
                                    tier starts at a larger quantity and costs
                                    the same or less per unit.
                                </p>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="currency">Currency</Label>
                                <select
                                    id="currency"
                                    className={selectClass}
                                    value={data.currency}
                                    onChange={(event) =>
                                        setData('currency', event.target.value)
                                    }
                                >
                                    {currencies.map((currency) => (
                                        <option key={currency}>
                                            {currency}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <InputError message={tierErrors.tiers} />
                        <ol className="space-y-3">
                            {data.tiers.map((tier, index) => (
                                <li
                                    key={index}
                                    className="grid grid-cols-[1fr_1fr_auto] items-start gap-3 rounded-md border p-3"
                                >
                                    <div className="grid gap-1">
                                        <Label htmlFor={`tier-${index}-qty`}>
                                            From quantity
                                        </Label>
                                        <Input
                                            id={`tier-${index}-qty`}
                                            type="number"
                                            min={1}
                                            value={tier.min_qty}
                                            onChange={(event) =>
                                                setTier(
                                                    index,
                                                    'min_qty',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                tierErrors[
                                                    `tiers.${index}.min_qty`
                                                ],
                                            )}
                                            aria-describedby={`tier-${index}-qty-error`}
                                        />
                                        <InputError
                                            id={`tier-${index}-qty-error`}
                                            message={
                                                tierErrors[
                                                    `tiers.${index}.min_qty`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor={`tier-${index}-price`}>
                                            Unit price ({data.currency})
                                        </Label>
                                        <Input
                                            id={`tier-${index}-price`}
                                            inputMode="decimal"
                                            value={tier.unit_price}
                                            onChange={(event) =>
                                                setTier(
                                                    index,
                                                    'unit_price',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                tierErrors[
                                                    `tiers.${index}.unit_price`
                                                ],
                                            )}
                                            aria-describedby={`tier-${index}-price-error`}
                                        />
                                        <InputError
                                            id={`tier-${index}-price-error`}
                                            message={
                                                tierErrors[
                                                    `tiers.${index}.unit_price`
                                                ]
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="mt-6"
                                        disabled={data.tiers.length === 1}
                                        onClick={() => removeTier(index)}
                                        aria-label={`Remove tier ${index + 1}`}
                                    >
                                        <X />
                                    </Button>
                                </li>
                            ))}
                        </ol>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addTier}
                            disabled={data.tiers.length >= maxTiers}
                        >
                            <Plus aria-hidden="true" />
                            Add tier
                        </Button>
                    </section>

                    <section
                        aria-labelledby="photos-heading"
                        className="space-y-3"
                    >
                        <h2
                            id="photos-heading"
                            className="text-lg font-semibold"
                        >
                            Photos
                        </h2>
                        <GalleryPicker
                            existing={product?.images ?? []}
                            value={{
                                files: data.images,
                                remove: data.remove_images,
                                cover: data.cover,
                            }}
                            onChange={(gallery) =>
                                setData((current) => ({
                                    ...current,
                                    images: gallery.files,
                                    remove_images: gallery.remove,
                                    cover: gallery.cover,
                                }))
                            }
                            max={maxImages}
                            errors={
                                errors as Record<string, string | undefined>
                            }
                        />
                    </section>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Saving…'
                                : product
                                  ? 'Save changes'
                                  : 'Save draft'}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={backUrl}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>

            {product && (
                <ConfirmDialog
                    open={confirmDelete}
                    onOpenChange={setConfirmDelete}
                    title="Delete this draft?"
                    description="The product and its photos are removed. This cannot be undone."
                    confirmLabel="Delete"
                    variant="destructive"
                    onConfirm={() =>
                        router.delete(productRoutes.destroy.url(product.slug))
                    }
                />
            )}
        </>
    );
}

ProductForm.layout = {
    breadcrumbs: [{ title: 'My products', href: productRoutes.index() }],
};
