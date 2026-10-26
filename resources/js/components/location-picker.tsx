import { router } from '@inertiajs/react';
import { LocateFixed, MapPin } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateLocation } from '@/routes/location';
import addressRoutes from '@/routes/addresses';
import type { CustomerLocation, Neighbourhood } from '@/types';

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

type Draft = {
    label: string;
    lat: string;
    lng: string;
    description: string;
    landmark: string;
};

/**
 * Sets where the customer wants delivery, without a map: the browser's
 * location, a Kigali neighbourhood, a saved address or typed coordinates.
 */
export function LocationPicker({
    location,
    neighbourhoods,
    savedAddresses = [],
    startOpen = false,
    onSaved,
}: {
    location: CustomerLocation | null;
    neighbourhoods: Neighbourhood[];
    savedAddresses?: { id: number; label: string }[];
    startOpen?: boolean;
    /** Called once the server stored the location (the cart drawer reloads). */
    onSaved?: () => void;
}) {
    const [open, setOpen] = useState(startOpen || location === null);
    const [draft, setDraft] = useState<Draft>({
        label: location?.label ?? '',
        lat: location ? String(location.lat) : '',
        lng: location ? String(location.lng) : '',
        description: location?.description ?? '',
        landmark: location?.landmark ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [locating, setLocating] = useState(false);

    const save = (values: Draft) =>
        router.put(updateLocation.url(), values, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                onSaved?.();
            },
            onError: (response) => setErrors(response),
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        save(draft);
    };

    const pickNeighbourhood = (name: string) => {
        const place = neighbourhoods.find((item) => item.name === name);

        if (place) {
            setDraft({
                ...draft,
                label: place.name,
                lat: String(place.lat),
                lng: String(place.lng),
            });
        }
    };

    const useBrowserLocation = () => {
        if (!navigator.geolocation) {
            setErrors({
                lat: 'Your browser cannot share its location. Pick a neighbourhood instead.',
            });

            return;
        }

        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                const next = {
                    ...draft,
                    label: draft.label || 'My location',
                    lat: position.coords.latitude.toFixed(7),
                    lng: position.coords.longitude.toFixed(7),
                };
                setDraft(next);
                save(next);
            },
            () => {
                setLocating(false);
                setErrors({
                    lat: 'We could not read your location. Pick a neighbourhood instead.',
                });
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    if (!open && location) {
        return (
            <div className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                <MapPin className="size-4 text-primary" aria-hidden="true" />
                <span>
                    Delivering to{' '}
                    <span className="font-medium">{location.label}</span>
                    {location.landmark ? ` (near ${location.landmark})` : ''}
                </span>
                <Button
                    variant="link"
                    size="sm"
                    className="h-auto p-0"
                    onClick={() => setOpen(true)}
                >
                    Change
                </Button>
            </div>
        );
    }

    return (
        <section
            aria-labelledby="location-heading"
            className="space-y-4 rounded-lg border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="location-heading" className="font-semibold">
                    Where should we deliver?
                </h2>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={useBrowserLocation}
                    disabled={locating}
                >
                    <LocateFixed aria-hidden="true" />
                    {locating ? 'Finding you…' : 'Use my location'}
                </Button>
            </div>

            {savedAddresses.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {savedAddresses.map((address) => (
                        <Button
                            key={address.id}
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    addressRoutes.use.url(address.id),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => onSaved?.(),
                                    },
                                )
                            }
                        >
                            {address.label}
                        </Button>
                    ))}
                </div>
            )}

            <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1 sm:col-span-2">
                    <Label htmlFor="neighbourhood">Neighbourhood</Label>
                    <select
                        id="neighbourhood"
                        className={selectClass}
                        defaultValue=""
                        onChange={(event) =>
                            pickNeighbourhood(event.target.value)
                        }
                    >
                        <option value="">Choose a neighbourhood…</option>
                        {neighbourhoods.map((place) => (
                            <option key={place.name}>{place.name}</option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="location-label">Name for this place</Label>
                    <Input
                        id="location-label"
                        value={draft.label}
                        onChange={(event) =>
                            setDraft({ ...draft, label: event.target.value })
                        }
                        placeholder="Home, Office…"
                    />
                    <InputError message={errors.label} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="location-landmark">Landmark</Label>
                    <Input
                        id="location-landmark"
                        value={draft.landmark}
                        onChange={(event) =>
                            setDraft({ ...draft, landmark: event.target.value })
                        }
                        placeholder="e.g. next to the church"
                    />
                </div>
                <details className="sm:col-span-2">
                    <summary className="cursor-pointer text-sm text-muted-foreground">
                        Exact coordinates
                    </summary>
                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor="location-lat">Latitude</Label>
                            <Input
                                id="location-lat"
                                inputMode="decimal"
                                value={draft.lat}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        lat: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="location-lng">Longitude</Label>
                            <Input
                                id="location-lng"
                                inputMode="decimal"
                                value={draft.lng}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        lng: event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                </details>
                <InputError
                    message={errors.lat ?? errors.lng}
                    className="sm:col-span-2"
                />
                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit">Use this location</Button>
                    {location && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                    )}
                </div>
            </form>
        </section>
    );
}
