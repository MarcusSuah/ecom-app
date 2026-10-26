import { Form, Head } from '@inertiajs/react';
import { Globe, Mail, Percent, Settings, Share2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MarketplaceLogoField } from '@/components/marketplace-logo-field';
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
import { Textarea } from '@/components/ui/textarea';
import settingsRoutes from '@/routes/admin/settings';

type SettingsValues = {
    name: string;
    support_email: string | null;
    default_currency: string;
    tagline: string | null;
    about: string | null;
    support_phone: string | null;
    whatsapp_number: string | null;
    address: string | null;
    business_hours: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    x_url: string | null;
    linkedin_url: string | null;
    require_account_approval: string;
    vat_percent: string;
    logo_url: string | null;
};

type Props = {
    settings: SettingsValues;
    currencies: string[];
    marketplace: { slug: string; domain: string | null };
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function SettingsIndex({
    settings,
    currencies,
    marketplace,
}: Props) {
    return (
        <>
            <Head title="Settings" />
            <div className="max-w-4xl p-4 md:p-6">
                <Heading
                    title="Marketplace settings"
                    description="Shown to visitors in the storefront header, footer and contact page."
                />

                <Form
                    {...settingsRoutes.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        General
                                    </CardTitle>
                                    <CardDescription>
                                        Address:{' '}
                                        {marketplace.domain ??
                                            `${marketplace.slug} subdomain`}
                                        . The platform team changes the address.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2 sm:col-span-2">
                                        <span className="text-sm font-medium">
                                            Logo
                                        </span>
                                        <MarketplaceLogoField
                                            logoUrl={settings.logo_url}
                                        />
                                    </div>
                                    <Field
                                        id="name"
                                        label="Marketplace name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            defaultValue={settings.name}
                                        />
                                    </Field>
                                    <Field
                                        id="default_currency"
                                        label="Default currency"
                                        hint="Prices are shown in this currency unless a visitor picks another."
                                        error={errors.default_currency}
                                    >
                                        <select
                                            id="default_currency"
                                            name="default_currency"
                                            defaultValue={
                                                settings.default_currency
                                            }
                                            className={selectClass}
                                        >
                                            {currencies.map((currency) => (
                                                <option
                                                    key={currency}
                                                    value={currency}
                                                >
                                                    {currency}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field
                                            id="tagline"
                                            label="Tagline"
                                            hint="One line under the logo and in the footer."
                                            error={errors.tagline}
                                        >
                                            <Input
                                                id="tagline"
                                                name="tagline"
                                                maxLength={200}
                                                defaultValue={
                                                    settings.tagline ?? ''
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <Field
                                            id="about"
                                            label="About the marketplace"
                                            error={errors.about}
                                        >
                                            <Textarea
                                                id="about"
                                                name="about"
                                                rows={3}
                                                maxLength={2000}
                                                defaultValue={
                                                    settings.about ?? ''
                                                }
                                            />
                                        </Field>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Mail
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        Contact
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        id="support_email"
                                        label="Support email"
                                        error={errors.support_email}
                                    >
                                        <Input
                                            id="support_email"
                                            name="support_email"
                                            type="email"
                                            defaultValue={
                                                settings.support_email ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id="support_phone"
                                        label="Support phone"
                                        error={errors.support_phone}
                                    >
                                        <Input
                                            id="support_phone"
                                            name="support_phone"
                                            type="tel"
                                            defaultValue={
                                                settings.support_phone ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id="whatsapp_number"
                                        label="WhatsApp number"
                                        error={errors.whatsapp_number}
                                    >
                                        <Input
                                            id="whatsapp_number"
                                            name="whatsapp_number"
                                            type="tel"
                                            defaultValue={
                                                settings.whatsapp_number ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        id="business_hours"
                                        label="Business hours"
                                        error={errors.business_hours}
                                    >
                                        <Input
                                            id="business_hours"
                                            name="business_hours"
                                            defaultValue={
                                                settings.business_hours ?? ''
                                            }
                                        />
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field
                                            id="address"
                                            label="Address"
                                            error={errors.address}
                                        >
                                            <Input
                                                id="address"
                                                name="address"
                                                defaultValue={
                                                    settings.address ?? ''
                                                }
                                            />
                                        </Field>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Share2
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        Social links
                                    </CardTitle>
                                    <CardDescription>
                                        Full links starting with https://. Empty
                                        ones are hidden.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    {(
                                        [
                                            ['facebook_url', 'Facebook'],
                                            ['instagram_url', 'Instagram'],
                                            ['x_url', 'X (Twitter)'],
                                            ['linkedin_url', 'LinkedIn'],
                                        ] as const
                                    ).map(([key, label]) => (
                                        <Field
                                            key={key}
                                            id={key}
                                            label={label}
                                            error={errors[key]}
                                        >
                                            <div className="relative">
                                                <Globe
                                                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <Input
                                                    id={key}
                                                    name={key}
                                                    type="url"
                                                    className="pl-9"
                                                    placeholder="https://"
                                                    defaultValue={
                                                        settings[key] ?? ''
                                                    }
                                                />
                                            </div>
                                        </Field>
                                    ))}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Percent
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        Accounts and tax
                                    </CardTitle>
                                    <CardDescription>
                                        Who may use the site, and the VAT shown
                                        in reports.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        id="require_account_approval"
                                        label="New accounts"
                                        hint="Approve accounts under Users. Until then people can browse but not sign in to their account pages."
                                        error={errors.require_account_approval}
                                    >
                                        <select
                                            id="require_account_approval"
                                            name="require_account_approval"
                                            className={selectClass}
                                            defaultValue={
                                                settings.require_account_approval
                                            }
                                        >
                                            <option value="1">
                                                Wait for an admin to approve
                                            </option>
                                            <option value="0">
                                                Can use the site straight away
                                            </option>
                                        </select>
                                    </Field>
                                    <Field
                                        id="vat_percent"
                                        label="VAT (%)"
                                        hint="Included in prices. Reports show the VAT part of sales at this rate."
                                        error={errors.vat_percent}
                                    >
                                        <Input
                                            id="vat_percent"
                                            name="vat_percent"
                                            type="number"
                                            min={0}
                                            max={50}
                                            step="0.01"
                                            defaultValue={settings.vat_percent}
                                            required
                                        />
                                    </Field>
                                </CardContent>
                            </Card>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save settings'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SettingsIndex.layout = {
    breadcrumbs: [{ title: 'Settings', href: settingsRoutes.edit() }],
};
