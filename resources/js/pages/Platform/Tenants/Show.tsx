import { Form, Head } from '@inertiajs/react';
import TenantController from '@/actions/App/Http/Controllers/Platform/TenantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import tenantRoutes from '@/routes/platform/tenants';
import type { BillingOverview } from '@/types';

type Props = BillingOverview & {
    tenant: {
        id: number;
        name: string;
        slug: string;
        domain: string | null;
        status: string;
        default_currency: string;
        support_email: string | null;
        created_at: string | null;
    };
    counts: { suppliers: number; shops: number };
    statuses: string[];
    currencies: string[];
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function TenantShow({
    tenant,
    counts,
    statuses,
    currencies,
    subscription,
    plans,
    invoices,
}: Props) {
    return (
        <>
            <Head title={tenant.name} />
            <div className="grid gap-6 p-4 lg:grid-cols-2">
                <div className="space-y-6">
                    <Heading
                        title={tenant.name}
                        description={`${counts.suppliers} suppliers · ${counts.shops} shops · since ${formatDate(tenant.created_at)}`}
                    />
                    <Form
                        {...TenantController.update.form(tenant.id)}
                        options={{ preserveScroll: true }}
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
                                        defaultValue={tenant.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="domain">
                                        Custom domain
                                    </Label>
                                    <Input
                                        id="domain"
                                        name="domain"
                                        defaultValue={tenant.domain ?? ''}
                                    />
                                    <InputError message={errors.domain} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="default_currency">
                                            Default currency
                                        </Label>
                                        <select
                                            id="default_currency"
                                            name="default_currency"
                                            className={selectClass}
                                            defaultValue={
                                                tenant.default_currency
                                            }
                                        >
                                            {currencies.map((currency) => (
                                                <option key={currency}>
                                                    {currency}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <select
                                            id="status"
                                            name="status"
                                            className={selectClass}
                                            defaultValue={tenant.status}
                                        >
                                            {statuses.map((status) => (
                                                <option
                                                    key={status}
                                                    value={status}
                                                    className="capitalize"
                                                >
                                                    {status}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="support_email">
                                        Support email
                                    </Label>
                                    <Input
                                        id="support_email"
                                        name="support_email"
                                        type="email"
                                        defaultValue={
                                            tenant.support_email ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.support_email}
                                    />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {subscription
                                    ? subscription.plan.name
                                    : 'No current plan'}
                                {subscription && (
                                    <StatusBadge status={subscription.status} />
                                )}
                            </CardTitle>
                            <CardDescription>
                                {subscription
                                    ? `Period ends ${formatDate(subscription.current_period_ends_at)}`
                                    : 'The marketplace is closed to visitors until it has a plan.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...TenantController.activatePlan.form(
                                    tenant.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium">
                                            Record an offline payment
                                        </p>
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="plan_id">
                                                    Plan
                                                </Label>
                                                <select
                                                    id="plan_id"
                                                    name="plan_id"
                                                    className={selectClass}
                                                >
                                                    {plans.map((plan) => (
                                                        <option
                                                            key={plan.id}
                                                            value={plan.id}
                                                        >
                                                            {plan.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="periods">
                                                    Periods
                                                </Label>
                                                <Input
                                                    id="periods"
                                                    name="periods"
                                                    type="number"
                                                    min={1}
                                                    max={36}
                                                    defaultValue={1}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="reference">
                                                    Reference
                                                </Label>
                                                <Input
                                                    id="reference"
                                                    name="reference"
                                                />
                                            </div>
                                        </div>
                                        <InputError
                                            message={
                                                errors.plan_id ??
                                                errors.periods ??
                                                errors.reference
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Record payment & start plan
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Invoices
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {invoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No invoices yet.
                                </p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {invoices.map((invoice) => (
                                        <li
                                            key={invoice.id}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                                        >
                                            <span className="font-mono text-xs">
                                                {invoice.number}
                                            </span>
                                            <span>{invoice.amount}</span>
                                            <StatusBadge
                                                status={invoice.status}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

TenantShow.layout = {
    breadcrumbs: [{ title: 'Marketplaces', href: tenantRoutes.index() }],
};
