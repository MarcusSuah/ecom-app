import { Head, router, useForm } from '@inertiajs/react';
import { LocateFixed } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import addressRoutes from '@/routes/addresses';
import type { CustomerLocation, Neighbourhood } from '@/types';

type SavedAddress = CustomerLocation & { id: number; is_default: boolean };

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function Addresses({
    addresses,
    neighbourhoods,
}: {
    addresses: SavedAddress[];
    neighbourhoods: Neighbourhood[];
}) {
    const form = useForm({
        label: '',
        lat: '',
        lng: '',
        description: '',
        landmark: '',
        is_default: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(addressRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Delivery addresses" />
            <div className="mx-auto w-full max-w-3xl space-y-8 p-4">
                <Heading
                    title="Delivery addresses"
                    description="Saved places you can deliver to on this marketplace."
                />

                {addresses.length > 0 && (
                    <ul className="divide-y rounded-md border">
                        {addresses.map((address) => (
                            <li
                                key={address.id}
                                className="flex flex-wrap items-center gap-3 p-4 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 font-medium">
                                        {address.label}
                                        {address.is_default && (
                                            <Badge variant="secondary">
                                                Default
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {[
                                            address.description,
                                            address.landmark &&
                                                `near ${address.landmark}`,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') ||
                                            `${address.lat}, ${address.lng}`}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            addressRoutes.use.url(address.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Deliver here
                                </Button>
                                {!address.is_default && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                addressRoutes.default.url(
                                                    address.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Make default
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            addressRoutes.destroy.url(
                                                address.id,
                                            ),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Delete
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-lg border p-4"
                    noValidate
                >
                    <h2 className="font-semibold">Add an address</h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-48 flex-1 gap-1">
                            <Label htmlFor="neighbourhood">Neighbourhood</Label>
                            <select
                                id="neighbourhood"
                                className={selectClass}
                                defaultValue=""
                                onChange={(event) => {
                                    const place = neighbourhoods.find(
                                        (item) =>
                                            item.name === event.target.value,
                                    );
                                    if (place) {
                                        form.setData((current) => ({
                                            ...current,
                                            lat: String(place.lat),
                                            lng: String(place.lng),
                                            description:
                                                current.description ||
                                                place.name,
                                        }));
                                    }
                                }}
                            >
                                <option value="">Choose…</option>
                                {neighbourhoods.map((place) => (
                                    <option key={place.name}>
                                        {place.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                navigator.geolocation?.getCurrentPosition(
                                    (position) =>
                                        form.setData((current) => ({
                                            ...current,
                                            lat: position.coords.latitude.toFixed(
                                                7,
                                            ),
                                            lng: position.coords.longitude.toFixed(
                                                7,
                                            ),
                                        })),
                                )
                            }
                        >
                            <LocateFixed aria-hidden="true" />
                            Use my location
                        </Button>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor="label">Name</Label>
                            <Input
                                id="label"
                                value={form.data.label}
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                                placeholder="Home, Office…"
                                aria-invalid={Boolean(form.errors.label)}
                            />
                            <InputError message={form.errors.label} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="landmark">Landmark</Label>
                            <Input
                                id="landmark"
                                value={form.data.landmark}
                                onChange={(event) =>
                                    form.setData('landmark', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-1 sm:col-span-2">
                            <Label htmlFor="description">Directions</Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Street, building, gate colour…"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="lat">Latitude</Label>
                            <Input
                                id="lat"
                                inputMode="decimal"
                                value={form.data.lat}
                                onChange={(event) =>
                                    form.setData('lat', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.lat)}
                            />
                            <InputError message={form.errors.lat} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="lng">Longitude</Label>
                            <Input
                                id="lng"
                                inputMode="decimal"
                                value={form.data.lng}
                                onChange={(event) =>
                                    form.setData('lng', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.lng)}
                            />
                            <InputError message={form.errors.lng} />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_default}
                            onCheckedChange={(checked) =>
                                form.setData('is_default', checked === true)
                            }
                        />
                        Make this my default
                    </label>
                    <Button type="submit" disabled={form.processing}>
                        Save address
                    </Button>
                </form>
            </div>
        </>
    );
}

Addresses.layout = {
    breadcrumbs: [{ title: 'Delivery addresses', href: addressRoutes.index() }],
};
