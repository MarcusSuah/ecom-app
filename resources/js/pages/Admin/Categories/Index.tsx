import { Form, Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Textarea } from '@/components/ui/textarea';
import categoryRoutes from '@/routes/admin/categories';
import type { Paginated } from '@/types';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    parent_id: number | null;
    parent_name: string | null;
    children_count: number;
};

/** Select value for "create a parent that is not listed". */
const NEW_PARENT = '__new';

type Props = {
    categories: Paginated<Category>;
    parentOptions: { id: number; name: string }[];
};

export default function CategoriesIndex({ categories, parentOptions }: Props) {
    const [editing, setEditing] = useState<Category | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Category | null>(null);
    const current = editing === 'new' ? null : editing;
    const [parentChoice, setParentChoice] = useState('');

    const open = (category: Category | 'new') => {
        setParentChoice(
            category === 'new' || category.parent_id === null
                ? ''
                : String(category.parent_id),
        );
        setEditing(category);
    };

    return (
        <>
            <Head title="Categories" />
            <div className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Categories"
                        description="Shown to buyers in this order. Sub-categories sit under a top-level category."
                    />
                    <Button onClick={() => open('new')}>
                        <Plus aria-hidden="true" />
                        Add category
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Parent</TableHead>
                                <TableHead className="text-right">
                                    Order
                                </TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.data.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {category.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            /{category.slug}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {category.parent_name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {category.sort_order}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => open(category)}
                                            aria-label={`Edit ${category.name}`}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                setDeleting(category)
                                            }
                                            aria-label={`Delete ${category.name}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <Pagination page={categories} />
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {current ? `Edit ${current.name}` : 'Add category'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={current?.id ?? 'new'}
                        {...(current
                            ? CategoryController.update.form(current.id)
                            : CategoryController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(null)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-name">Name</Label>
                                    <Input
                                        id="category-name"
                                        name="name"
                                        required
                                        defaultValue={current?.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-parent">
                                        Parent
                                    </Label>
                                    <select
                                        id="category-parent"
                                        value={parentChoice}
                                        onChange={(event) =>
                                            setParentChoice(event.target.value)
                                        }
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        <option value="">
                                            None (top level)
                                        </option>
                                        {parentOptions
                                            .filter(
                                                (option) =>
                                                    option.id !== current?.id,
                                            )
                                            .map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.name}
                                                </option>
                                            ))}
                                        <option value={NEW_PARENT}>
                                            + Add a new parent…
                                        </option>
                                    </select>
                                    {parentChoice === NEW_PARENT ? (
                                        <>
                                            <Label
                                                htmlFor="new-parent-name"
                                                className="sr-only"
                                            >
                                                New parent name
                                            </Label>
                                            <Input
                                                id="new-parent-name"
                                                name="new_parent_name"
                                                required
                                                placeholder="Name of the new parent category"
                                                autoFocus
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                It is created as a top-level
                                                category when you save.
                                            </p>
                                        </>
                                    ) : (
                                        <input
                                            type="hidden"
                                            name="parent_id"
                                            value={parentChoice}
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors.parent_id ??
                                            errors.new_parent_name
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-order">
                                        Sort order
                                    </Label>
                                    <Input
                                        id="category-order"
                                        name="sort_order"
                                        type="number"
                                        min={0}
                                        required
                                        defaultValue={current?.sort_order ?? 0}
                                    />
                                    <InputError message={errors.sort_order} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="category-description"
                                        name="description"
                                        rows={3}
                                        defaultValue={
                                            current?.description ?? ''
                                        }
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.name}?`}
                description="This cannot be undone."
                confirmLabel="Delete"
                variant="destructive"
                onConfirm={() => {
                    if (deleting) {
                        router.delete(categoryRoutes.destroy.url(deleting.id), {
                            preserveScroll: true,
                        });
                    }
                    setDeleting(null);
                }}
            />
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: categoryRoutes.index() }],
};
