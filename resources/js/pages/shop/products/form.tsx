import { Form, Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    emptyGallery,
    type GalleryImage,
    GalleryPicker,
    type GalleryValue,
} from '@/components/gallery-picker';
import { StockBadge } from '@/components/stock-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import productRoutes from '@/routes/shop/products';

type Props = {
    product: {
        id: number;
        name: string;
        category_id: number;
        description: string;
        price: string;
        stock: number;
        is_active: boolean;
        image_url: string | null;
        images: GalleryImage[];
        stock_status: string;
        stock_label: string;
        units_sold: number;
    } | null;
    currency: string | null;
    categories: { id: number; name: string }[];
    maxImages: number;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function ShopItemForm({
    product,
    currency,
    categories,
    maxImages,
}: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [gallery, setGallery] = useState<GalleryValue>(emptyGallery);

    return (
        <>
            <Head title={product ? `Edit ${product.name}` : 'Add item'} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Heading title={product ? product.name : 'Add item'} />
                        {product && (
                            <div className="-mt-6 mb-6">
                                <StockBadge
                                    status={product.stock_status}
                                    label={product.stock_label}
                                    sold={product.units_sold}
                                />
                            </div>
                        )}
                    </div>
                    {product && (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Delete item"
                            onClick={() => setConfirmDelete(true)}
                        >
                            <Trash2 />
                        </Button>
                    )}
                </div>
                <Form
                    {...(product
                        ? productRoutes.update.form(product.id)
                        : productRoutes.store.form())}
                    encType="multipart/form-data"
                    transform={(data) => ({
                        ...data,
                        is_active:
                            data.is_active === 'on' || data.is_active === true,
                        images: gallery.files,
                        remove_images: gallery.remove,
                        cover: gallery.cover,
                    })}
                    onSuccess={() => setGallery(emptyGallery)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={product?.name}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Category</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    className={selectClass}
                                    defaultValue={product?.category_id ?? ''}
                                    required
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
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="price">
                                        Price ({currency})
                                    </Label>
                                    <Input
                                        id="price"
                                        name="price"
                                        inputMode="decimal"
                                        required
                                        defaultValue={product?.price}
                                    />
                                    <InputError message={errors.price} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="stock">In stock</Label>
                                    <Input
                                        id="stock"
                                        name="stock"
                                        type="number"
                                        min={0}
                                        required
                                        defaultValue={product?.stock ?? 0}
                                    />
                                    <InputError message={errors.stock} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    defaultValue={product?.description}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <fieldset className="grid gap-2">
                                <legend className="mb-2 text-sm font-medium">
                                    Photos
                                </legend>
                                <GalleryPicker
                                    existing={product?.images ?? []}
                                    value={gallery}
                                    onChange={setGallery}
                                    max={maxImages}
                                    errors={errors}
                                />
                            </fieldset>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    name="is_active"
                                    defaultChecked={product?.is_active ?? true}
                                />
                                For sale
                            </label>
                            <InputError message={errors.is_active} />
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save item'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
            {product && (
                <ConfirmDialog
                    open={confirmDelete}
                    onOpenChange={setConfirmDelete}
                    title={`Delete ${product.name}?`}
                    description="Past orders keep their own copy of the item."
                    confirmLabel="Delete"
                    variant="destructive"
                    onConfirm={() =>
                        router.delete(productRoutes.destroy.url(product.id))
                    }
                />
            )}
        </>
    );
}

ShopItemForm.layout = {
    breadcrumbs: [{ title: 'Shop items', href: productRoutes.index() }],
};
