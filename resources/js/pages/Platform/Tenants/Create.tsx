import { Form, Head } from '@inertiajs/react';
import TenantController from '@/actions/App/Http/Controllers/Platform/TenantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import tenantRoutes from '@/routes/platform/tenants';
import type { Plan } from '@/types';

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function CreateTenant({
    plans,
    currencies,
    centralDomain,
}: {
    plans: Plan[];
    currencies: string[];
    centralDomain: string;
}) {
    return (
        <>
            <Head title="New marketplace" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <Heading
                    title="New marketplace"
                    description="Creates the marketplace with the standard categories and seller plans, and invites its first admin."
                />
                <Form {...TenantController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="slug">Subdomain</Label>
                                    <div className="flex items-center gap-1">
                                        <Input
                                            id="slug"
                                            name="slug"
                                            required
                                            aria-describedby="slug-help"
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            .{centralDomain}
                                        </span>
                                    </div>
                                    <p
                                        id="slug-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Lowercase letters, numbers and dashes.
                                        Cannot be changed later.
                                    </p>
                                    <InputError message={errors.slug} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="domain">
                                        Custom domain (optional)
                                    </Label>
                                    <Input
                                        id="domain"
                                        name="domain"
                                        placeholder="shop.example.com"
                                    />
                                    <InputError message={errors.domain} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="default_currency">
                                        Default currency
                                    </Label>
                                    <select
                                        id="default_currency"
                                        name="default_currency"
                                        className={selectClass}
                                        defaultValue="RWF"
                                    >
                                        {currencies.map((currency) => (
                                            <option key={currency}>
                                                {currency}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.default_currency}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="support_email">
                                        Support email
                                    </Label>
                                    <Input
                                        id="support_email"
                                        name="support_email"
                                        type="email"
                                    />
                                    <InputError
                                        message={errors.support_email}
                                    />
                                </div>
                            </div>

                            <fieldset className="space-y-4 rounded-md border p-4">
                                <legend className="px-1 text-sm font-medium">
                                    First admin
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    An existing account with this email becomes
                                    the admin. Otherwise an account is created
                                    and sent a link to set a password.
                                </p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="admin_name">Name</Label>
                                        <Input
                                            id="admin_name"
                                            name="admin_name"
                                            required
                                        />
                                        <InputError
                                            message={errors.admin_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="admin_email">
                                            Email
                                        </Label>
                                        <Input
                                            id="admin_email"
                                            name="admin_email"
                                            type="email"
                                            required
                                        />
                                        <InputError
                                            message={errors.admin_email}
                                        />
                                    </div>
                                </div>
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor="plan_id">Platform plan</Label>
                                <select
                                    id="plan_id"
                                    name="plan_id"
                                    className={selectClass}
                                    required
                                >
                                    {plans.map((plan) => (
                                        <option key={plan.id} value={plan.id}>
                                            {plan.name} · {plan.formatted_price}
                                            {plan.trial_days > 0
                                                ? ` · ${plan.trial_days}-day trial`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.plan_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Creating…'
                                    : 'Create marketplace'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateTenant.layout = {
    breadcrumbs: [
        { title: 'Marketplaces', href: tenantRoutes.index() },
        { title: 'New', href: tenantRoutes.create() },
    ],
};
