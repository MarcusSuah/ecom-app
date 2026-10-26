import { Head, useForm } from '@inertiajs/react';
import { LocateFixed } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import settingsRoutes from '@/routes/shop/settings';
import type { Neighbourhood } from '@/types';

type Hours = Record<string, { open: string; close: string } | null>;

type Props = {
    shop: {
        lat: number;
        lng: number;
        address_text: string;
        landmark: string;
        offers_pickup: boolean;
        offers_delivery: boolean;
        currency: string;
        base_fee: string;
        per_km_rate: string;
        free_threshold: string;
        delivery_radius_km: string;
        opening_hours: Hours | null;
    } | null;
    defaults: { currency: string };
    currencies: string[];
    neighbourhoods: Neighbourhood[];
};

const days: [string, string][] = [
    ['mon', 'Monday'],
    ['tue', 'Tuesday'],
    ['wed', 'Wednesday'],
    ['thu', 'Thursday'],
    ['fri', 'Friday'],
    ['sat', 'Saturday'],
    ['sun', 'Sunday'],
];

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function ShopSettings({
    shop,
    defaults,
    currencies,
    neighbourhoods,
}: Props) {
    const form = useForm({
        lat: shop ? String(shop.lat) : '',
        lng: shop ? String(shop.lng) : '',
        address_text: shop?.address_text ?? '',
        landmark: shop?.landmark ?? '',
        offers_pickup: shop?.offers_pickup ?? true,
        offers_delivery: shop?.offers_delivery ?? true,
        currency: shop?.currency ?? defaults.currency,
        base_fee: shop?.base_fee ?? '1000',
        per_km_rate: shop?.per_km_rate ?? '300',
        free_threshold: shop?.free_threshold ?? '',
        delivery_radius_km: shop?.delivery_radius_km ?? '5',
        opening_hours:
            shop?.opening_hours ??
            (Object.fromEntries(
                days.map(([key]) => [key, { open: '08:00', close: '20:00' }]),
            ) as Hours),
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(settingsRoutes.update.url(), { preserveScroll: true });
    };

    const setDay = (
        day: string,
        value: { open: string; close: string } | null,
    ) => setData('opening_hours', { ...data.opening_hours, [day]: value });

    const locate = () =>
        navigator.geolocation?.getCurrentPosition((position) => {
            setData((current) => ({
                ...current,
                lat: position.coords.latitude.toFixed(7),
                lng: position.coords.longitude.toFixed(7),
            }));
        });

    return (
        <>
            <Head title="Shop settings" />
            <form
                onSubmit={submit}
                className="mx-auto w-full max-w-3xl space-y-8 p-4"
                noValidate
            >
                <Heading
                    title="Shop settings"
                    description="Where you are, how customers get their orders, and what delivery costs."
                />

                <section
                    aria-labelledby="location-heading"
                    className="space-y-4"
                >
                    <h2 id="location-heading" className="text-lg font-semibold">
                        Location
                    </h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-48 flex-1 gap-1">
                            <Label htmlFor="neighbourhood">
                                Start from a neighbourhood
                            </Label>
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
                                        setData((current) => ({
                                            ...current,
                                            lat: String(place.lat),
                                            lng: String(place.lng),
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
                            onClick={locate}
                        >
                            <LocateFixed aria-hidden="true" />
                            I am at the shop now
                        </Button>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor="lat">Latitude</Label>
                            <Input
                                id="lat"
                                inputMode="decimal"
                                value={data.lat}
                                onChange={(event) =>
                                    setData('lat', event.target.value)
                                }
                                aria-invalid={Boolean(errors.lat)}
                            />
                            <InputError message={errors.lat} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="lng">Longitude</Label>
                            <Input
                                id="lng"
                                inputMode="decimal"
                                value={data.lng}
                                onChange={(event) =>
                                    setData('lng', event.target.value)
                                }
                                aria-invalid={Boolean(errors.lng)}
                            />
                            <InputError message={errors.lng} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="address_text">Address</Label>
                            <Input
                                id="address_text"
                                value={data.address_text}
                                onChange={(event) =>
                                    setData('address_text', event.target.value)
                                }
                                aria-invalid={Boolean(errors.address_text)}
                            />
                            <InputError message={errors.address_text} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="landmark">Landmark</Label>
                            <Input
                                id="landmark"
                                value={data.landmark}
                                onChange={(event) =>
                                    setData('landmark', event.target.value)
                                }
                            />
                        </div>
                    </div>
                </section>

                <section
                    aria-labelledby="delivery-heading"
                    className="space-y-4"
                >
                    <h2 id="delivery-heading" className="text-lg font-semibold">
                        Pickup and delivery
                    </h2>
                    <div className="flex flex-wrap gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.offers_pickup}
                                onCheckedChange={(checked) =>
                                    setData('offers_pickup', checked === true)
                                }
                            />
                            Customers can pick up
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.offers_delivery}
                                onCheckedChange={(checked) =>
                                    setData('offers_delivery', checked === true)
                                }
                            />
                            We deliver
                        </label>
                    </div>
                    <InputError message={errors.offers_pickup} />
                    <div className="grid gap-4 sm:grid-cols-3">
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
                                    <option key={currency}>{currency}</option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="base_fee">Base delivery fee</Label>
                            <Input
                                id="base_fee"
                                inputMode="decimal"
                                value={data.base_fee}
                                onChange={(event) =>
                                    setData('base_fee', event.target.value)
                                }
                                aria-invalid={Boolean(errors.base_fee)}
                            />
                            <InputError message={errors.base_fee} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="per_km_rate">
                                Per km after the first
                            </Label>
                            <Input
                                id="per_km_rate"
                                inputMode="decimal"
                                value={data.per_km_rate}
                                onChange={(event) =>
                                    setData('per_km_rate', event.target.value)
                                }
                                aria-invalid={Boolean(errors.per_km_rate)}
                            />
                            <InputError message={errors.per_km_rate} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="free_threshold">
                                Free delivery from
                            </Label>
                            <Input
                                id="free_threshold"
                                inputMode="decimal"
                                value={data.free_threshold}
                                onChange={(event) =>
                                    setData(
                                        'free_threshold',
                                        event.target.value,
                                    )
                                }
                                placeholder="Never free"
                            />
                            <InputError message={errors.free_threshold} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="delivery_radius_km">
                                Delivery radius (km)
                            </Label>
                            <Input
                                id="delivery_radius_km"
                                inputMode="decimal"
                                value={data.delivery_radius_km}
                                onChange={(event) =>
                                    setData(
                                        'delivery_radius_km',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    errors.delivery_radius_km,
                                )}
                            />
                            <InputError message={errors.delivery_radius_km} />
                        </div>
                    </div>
                </section>

                <section aria-labelledby="hours-heading" className="space-y-3">
                    <h2 id="hours-heading" className="text-lg font-semibold">
                        Opening hours
                    </h2>
                    <ul className="space-y-2">
                        {days.map(([key, name]) => {
                            const hours = data.opening_hours[key];

                            return (
                                <li
                                    key={key}
                                    className="grid grid-cols-[7rem_auto_1fr] items-center gap-3 text-sm"
                                >
                                    <span>{name}</span>
                                    <label className="flex items-center gap-2">
                                        <Checkbox
                                            checked={hours !== null}
                                            onCheckedChange={(checked) =>
                                                setDay(
                                                    key,
                                                    checked === true
                                                        ? {
                                                              open: '08:00',
                                                              close: '20:00',
                                                          }
                                                        : null,
                                                )
                                            }
                                        />
                                        Open
                                    </label>
                                    {hours ? (
                                        <div className="flex items-center gap-2">
                                            <Input
                                                type="time"
                                                aria-label={`${name} opening time`}
                                                value={hours.open}
                                                onChange={(event) =>
                                                    setDay(key, {
                                                        ...hours,
                                                        open: event.target
                                                            .value,
                                                    })
                                                }
                                                className="w-32"
                                            />
                                            <span aria-hidden="true">–</span>
                                            <Input
                                                type="time"
                                                aria-label={`${name} closing time`}
                                                value={hours.close}
                                                onChange={(event) =>
                                                    setDay(key, {
                                                        ...hours,
                                                        close: event.target
                                                            .value,
                                                    })
                                                }
                                                className="w-32"
                                            />
                                            <InputError
                                                message={
                                                    fieldErrors[
                                                        `opening_hours.${key}.open`
                                                    ] ??
                                                    fieldErrors[
                                                        `opening_hours.${key}.close`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Closed
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>

                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : 'Save settings'}
                </Button>
            </form>
        </>
    );
}

ShopSettings.layout = {
    breadcrumbs: [{ title: 'Shop settings', href: settingsRoutes.edit() }],
};
