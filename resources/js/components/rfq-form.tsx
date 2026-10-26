import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as storeRfq } from '@/routes/rfqs';
import type { SharedData } from '@/types';

export type RfqFormDefaults = {
    title: string;
    category_id: string;
    product_id: number | null;
    quantity: string;
    unit: string;
    deliver_city: string;
    needed_by: string;
    notes: string;
    guest_name: string;
    guest_email: string;
    guest_phone: string;
};

export type RfqFormOptions = {
    categories: { id: number; name: string }[];
    units: { value: string; label: string }[];
};

export const emptyRfq: RfqFormDefaults = {
    title: '',
    category_id: '',
    product_id: null,
    quantity: '',
    unit: 'piece',
    deliver_city: '',
    needed_by: '',
    notes: '',
    guest_name: '',
    guest_email: '',
    guest_phone: '',
};

export const rfqSteps = [
    {
        title: 'Describe what you need',
        text: 'Product, quantity, where to deliver and by when.',
    },
    {
        title: 'Verified suppliers reply',
        text: 'Matching suppliers send tier prices, lead times and sample terms.',
    },
    {
        title: 'Compare and choose',
        text: 'See the quotes side by side, chat with suppliers and accept the best one.',
    },
];

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-invalid:border-destructive';

/**
 * The request for quotes form. Guests send it too, with contact details
 * instead of an account.
 */
export function RfqForm({
    defaults,
    categories,
    units,
}: { defaults: RfqFormDefaults } & RfqFormOptions) {
    const { auth } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors } = useForm({
        ...defaults,
        guest_phone: defaults.guest_phone || (auth.user?.phone ?? ''),
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(storeRfq.url());
    };

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <div className="grid gap-2">
                <Label htmlFor="title">What you need</Label>
                <Input
                    id="title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="e.g. Maize flour, 25 kg bags"
                    aria-invalid={Boolean(errors.title)}
                    aria-describedby={errors.title ? 'title-error' : undefined}
                />
                <InputError id="title-error" message={errors.title} />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2 sm:col-span-1">
                    <Label htmlFor="category_id">Category</Label>
                    <select
                        id="category_id"
                        className={selectClass}
                        value={data.category_id}
                        onChange={(event) =>
                            setData('category_id', event.target.value)
                        }
                        aria-invalid={Boolean(errors.category_id)}
                    >
                        <option value="">Choose…</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.category_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="quantity">Quantity</Label>
                    <Input
                        id="quantity"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        value={data.quantity}
                        onChange={(event) =>
                            setData('quantity', event.target.value)
                        }
                        aria-invalid={Boolean(errors.quantity)}
                    />
                    <InputError message={errors.quantity} />
                </div>
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
                            <option key={unit.value} value={unit.value}>
                                {unit.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="deliver_city">Deliver to</Label>
                    <Input
                        id="deliver_city"
                        value={data.deliver_city}
                        onChange={(event) =>
                            setData('deliver_city', event.target.value)
                        }
                        placeholder="City"
                        autoComplete="address-level2"
                        aria-invalid={Boolean(errors.deliver_city)}
                    />
                    <InputError message={errors.deliver_city} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="needed_by">Needed by</Label>
                    <Input
                        id="needed_by"
                        type="date"
                        value={data.needed_by}
                        onChange={(event) =>
                            setData('needed_by', event.target.value)
                        }
                        aria-invalid={Boolean(errors.needed_by)}
                    />
                    <InputError message={errors.needed_by} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="notes">Notes</Label>
                <Textarea
                    id="notes"
                    rows={4}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                    placeholder="Packaging, quality, certifications…"
                />
                <InputError message={errors.notes} />
            </div>
            <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-3">
                <legend className="px-1 text-sm font-medium">
                    Your contact details
                </legend>
                <p className="text-sm text-muted-foreground sm:col-span-3">
                    {auth.user
                        ? 'Suppliers reply to these details. Name and email come from your account.'
                        : 'No account needed. We email you each quote and a link to compare them; sign in later only to accept one.'}
                </p>
                <div className="grid gap-2">
                    <Label htmlFor="guest_name">Your name</Label>
                    <Input
                        id="guest_name"
                        value={auth.user ? auth.user.name : data.guest_name}
                        onChange={(event) =>
                            setData('guest_name', event.target.value)
                        }
                        readOnly={Boolean(auth.user)}
                        required
                        autoComplete="name"
                        aria-invalid={Boolean(errors.guest_name)}
                    />
                    <InputError message={errors.guest_name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="guest_email">Email</Label>
                    <Input
                        id="guest_email"
                        type="email"
                        value={auth.user ? auth.user.email : data.guest_email}
                        onChange={(event) =>
                            setData('guest_email', event.target.value)
                        }
                        readOnly={Boolean(auth.user)}
                        required
                        autoComplete="email"
                        aria-invalid={Boolean(errors.guest_email)}
                    />
                    <InputError message={errors.guest_email} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="guest_phone">Phone</Label>
                    <Input
                        id="guest_phone"
                        type="tel"
                        required
                        value={data.guest_phone}
                        onChange={(event) =>
                            setData('guest_phone', event.target.value)
                        }
                        autoComplete="tel"
                        aria-invalid={Boolean(errors.guest_phone)}
                    />
                    <InputError message={errors.guest_phone} />
                </div>
            </fieldset>
            <Button type="submit" disabled={processing}>
                {processing ? 'Sending…' : 'Send request'}
            </Button>
        </form>
    );
}

export function RfqSteps() {
    return (
        <ol className="space-y-4">
            {rfqSteps.map((step, index) => (
                <li key={step.title} className="flex gap-3 text-sm">
                    <span
                        className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                        aria-hidden="true"
                    >
                        {index + 1}
                    </span>
                    <span>
                        <span className="block font-medium">{step.title}</span>
                        <span className="text-muted-foreground">
                            {step.text}
                        </span>
                    </span>
                </li>
            ))}
        </ol>
    );
}
