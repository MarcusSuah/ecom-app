import { Form, Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatLimit } from '@/lib/format';
import type { Plan } from '@/types';

type PlanRow = Plan & { price_input: string; subscriptions_count: number };

type Props = {
    title: string;
    description: string;
    audience: 'seller' | 'marketplace';
    plans: PlanRow[];
    limitDefinitions: { key: string; label: string; is_switch: boolean }[];
    currencies: string[];
    defaultCurrency: string;
    /** `base` + "/{id}" is the update/delete URL for a plan. */
    routes: { store: string; base: string };
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

/**
 * Shared by the marketplace's seller plans and the platform's marketplace plans.
 */
export default function PlansIndex({
    title,
    description,
    plans,
    limitDefinitions,
    currencies,
    defaultCurrency,
    routes,
}: Props) {
    const [editing, setEditing] = useState<PlanRow | 'new' | null>(null);
    const [deleting, setDeleting] = useState<PlanRow | null>(null);
    const current = editing === 'new' ? null : editing;
    const limitValue = (key: string) =>
        current?.limits.find((limit) => limit.key === key)?.value;

    return (
        <>
            <Head title={title} />
            <div className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={title} description={description} />
                    <Button onClick={() => setEditing('new')}>
                        <Plus aria-hidden="true" />
                        Add plan
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Plan</TableHead>
                                <TableHead>Price</TableHead>
                                <TableHead>Limits</TableHead>
                                <TableHead className="text-right">
                                    Subscribers
                                </TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {plans.map((plan) => (
                                <TableRow key={plan.id}>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-2 font-medium">
                                            {plan.name}
                                            {plan.is_default && (
                                                <StatusBadge status="default" />
                                            )}
                                            {!plan.is_active && (
                                                <StatusBadge status="inactive" />
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {plan.description}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {plan.formatted_price}
                                        {plan.trial_days > 0 && (
                                            <div className="text-xs text-muted-foreground">
                                                {plan.trial_days}-day trial
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <ul className="text-xs">
                                            {plan.limits.map((limit) => (
                                                <li key={limit.key}>
                                                    {limit.label}:{' '}
                                                    {formatLimit(limit)}
                                                </li>
                                            ))}
                                        </ul>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {plan.subscriptions_count}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setEditing(plan)}
                                            aria-label={`Edit ${plan.name}`}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            disabled={
                                                plan.subscriptions_count > 0
                                            }
                                            onClick={() => setDeleting(plan)}
                                            aria-label={`Delete ${plan.name}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {current ? `Edit ${current.name}` : 'Add plan'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={current?.id ?? 'new'}
                        action={
                            current
                                ? `${routes.base}/${current.id}`
                                : routes.store
                        }
                        method={current ? 'put' : 'post'}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(null)}
                        transform={(data) => ({
                            ...data,
                            is_default:
                                data.is_default === 'on' ||
                                data.is_default === true,
                            is_active:
                                data.is_active === 'on' ||
                                data.is_active === true,
                        })}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-name">Name</Label>
                                    <Input
                                        id="plan-name"
                                        name="name"
                                        required
                                        defaultValue={current?.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="plan-description"
                                        name="description"
                                        rows={2}
                                        defaultValue={
                                            current?.description ?? ''
                                        }
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan-price">
                                            Price
                                        </Label>
                                        <Input
                                            id="plan-price"
                                            name="price"
                                            inputMode="decimal"
                                            required
                                            defaultValue={
                                                current?.price_input ?? '0'
                                            }
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan-currency">
                                            Currency
                                        </Label>
                                        <select
                                            id="plan-currency"
                                            name="currency"
                                            className={selectClass}
                                            defaultValue={
                                                current?.currency ??
                                                defaultCurrency
                                            }
                                        >
                                            {currencies.map((currency) => (
                                                <option key={currency}>
                                                    {currency}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.currency} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan-interval">
                                            Billed every
                                        </Label>
                                        <select
                                            id="plan-interval"
                                            name="interval"
                                            className={selectClass}
                                            defaultValue={
                                                current?.interval ?? 'month'
                                            }
                                        >
                                            <option value="month">Month</option>
                                            <option value="year">Year</option>
                                        </select>
                                        <InputError message={errors.interval} />
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan-trial">
                                            Trial days
                                        </Label>
                                        <Input
                                            id="plan-trial"
                                            name="trial_days"
                                            type="number"
                                            min={0}
                                            max={90}
                                            required
                                            defaultValue={
                                                current?.trial_days ?? 0
                                            }
                                        />
                                        <InputError
                                            message={errors.trial_days}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan-order">
                                            Sort order
                                        </Label>
                                        <Input
                                            id="plan-order"
                                            name="sort_order"
                                            type="number"
                                            min={0}
                                            required
                                            defaultValue={
                                                current?.sort_order ?? 0
                                            }
                                        />
                                        <InputError
                                            message={errors.sort_order}
                                        />
                                    </div>
                                </div>

                                <fieldset className="space-y-3 rounded-md border p-3">
                                    <legend className="px-1 text-sm font-medium">
                                        Limits
                                    </legend>
                                    {limitDefinitions.map((definition) =>
                                        definition.is_switch ? (
                                            <label
                                                key={definition.key}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    name={`limits[${definition.key}]`}
                                                    value="1"
                                                    defaultChecked={Boolean(
                                                        limitValue(
                                                            definition.key,
                                                        ),
                                                    )}
                                                />
                                                {definition.label}
                                            </label>
                                        ) : (
                                            <div
                                                key={definition.key}
                                                className="grid gap-1"
                                            >
                                                <Label
                                                    htmlFor={`limit-${definition.key}`}
                                                >
                                                    {definition.label}
                                                </Label>
                                                <Input
                                                    id={`limit-${definition.key}`}
                                                    name={`limits[${definition.key}]`}
                                                    type="number"
                                                    min={0}
                                                    placeholder="Unlimited"
                                                    defaultValue={
                                                        (limitValue(
                                                            definition.key,
                                                        ) as
                                                            | number
                                                            | null
                                                            | undefined) ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `limits.${definition.key}`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ),
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Leave a number empty for unlimited.
                                    </p>
                                </fieldset>

                                <div className="flex flex-wrap gap-6">
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_active"
                                            defaultChecked={
                                                current?.is_active ?? true
                                            }
                                        />
                                        Available to subscribe
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_default"
                                            defaultChecked={
                                                current?.is_default ?? false
                                            }
                                        />
                                        Default plan
                                    </label>
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save plan'}
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
                description="Plans with subscribers cannot be deleted; mark them unavailable instead."
                confirmLabel="Delete"
                variant="destructive"
                onConfirm={() => {
                    if (deleting) {
                        router.delete(`${routes.base}/${deleting.id}`, {
                            preserveScroll: true,
                        });
                    }
                    setDeleting(null);
                }}
            />
        </>
    );
}
