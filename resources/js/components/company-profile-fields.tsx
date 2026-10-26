import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * Fields shared by the create and edit forms.
 */
export function CompanyProfileFields({
    errors,
    defaults,
}: {
    errors: Record<string, string | undefined>;
    defaults?: {
        name?: string;
        description?: string | null;
        country?: string;
        city?: string | null;
        phone?: string | null;
        email?: string | null;
    };
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Company name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={defaults?.name}
                    autoComplete="organization"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">About the company</Label>
                <Textarea
                    id="description"
                    name="description"
                    rows={4}
                    defaultValue={defaults?.description ?? ''}
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="country">Country code</Label>
                    <Input
                        id="country"
                        name="country"
                        required
                        maxLength={2}
                        defaultValue={defaults?.country ?? 'RW'}
                        aria-describedby="country-help"
                    />
                    <p
                        id="country-help"
                        className="text-xs text-muted-foreground"
                    >
                        Two letters, e.g. RW
                    </p>
                    <InputError message={errors.country} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="city">City</Label>
                    <Input
                        id="city"
                        name="city"
                        defaultValue={defaults?.city ?? ''}
                        autoComplete="address-level2"
                    />
                    <InputError message={errors.city} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        defaultValue={defaults?.phone ?? ''}
                        autoComplete="tel"
                    />
                    <InputError message={errors.phone} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="email">Business email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        defaultValue={defaults?.email ?? ''}
                        autoComplete="email"
                    />
                    <InputError message={errors.email} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="logo">Logo</Label>
                <Input
                    id="logo"
                    name="logo"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                />
                <p className="text-xs text-muted-foreground">
                    PNG, JPG or WebP, up to 2 MB.
                </p>
                <InputError message={errors.logo} />
            </div>
        </>
    );
}
