import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LocationPicker } from '@/components/location-picker';
import { Pagination } from '@/components/pagination';
import {
    DeliveryFeeExplainer,
    ShopCard,
    type ShopCardData,
} from '@/components/shop-card';
import { Switch } from '@/components/ui/switch';
import { index as shopsIndex } from '@/routes/shops';
import type {
    CustomerLocation,
    Neighbourhood,
    Paginated,
    SharedData,
} from '@/types';

type Props = {
    shops: Paginated<ShopCardData>;
    filters: { delivers: boolean };
    location: CustomerLocation | null;
    neighbourhoods: Neighbourhood[];
    roadFactor: number;
};

export default function ShopsIndex({
    shops,
    filters,
    location,
    neighbourhoods,
    roadFactor,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const [needsLocation, setNeedsLocation] = useState(false);

    return (
        <>
            <Head title="Local shops" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Local shops</h1>
                        <p className="text-muted-foreground">
                            Shops near you, for pickup or delivery.
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Switch
                            checked={filters.delivers}
                            onCheckedChange={(checked) => {
                                // Delivery areas need a location; ask for it rather than ignoring the switch.
                                if (checked && !location) {
                                    setNeedsLocation(true);

                                    return;
                                }

                                router.get(
                                    shopsIndex.url(),
                                    checked ? { delivers: 1 } : {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    },
                                );
                            }}
                        />
                        Delivers to my area only
                    </label>
                </div>

                {needsLocation && !location && (
                    <p
                        role="alert"
                        className="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                    >
                        Set where you want delivery first, then switch this on
                        to see only the shops that deliver there.
                    </p>
                )}

                <LocationPicker
                    key={needsLocation ? 'open' : 'closed'}
                    location={location}
                    neighbourhoods={neighbourhoods}
                    startOpen={needsLocation}
                />

                {shops.data.length === 0 ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {filters.delivers
                            ? 'No shop delivers to this location yet. Try pickup.'
                            : 'No shops yet.'}
                    </p>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {shops.data.map((shop) => (
                            <li key={shop.id}>
                                <ShopCard shop={shop} />
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination page={shops} />

                <section
                    aria-labelledby="fees-heading"
                    className="rounded-lg bg-muted p-4 text-sm"
                >
                    <h2 id="fees-heading" className="mb-1 font-semibold">
                        How delivery fees work
                    </h2>
                    <DeliveryFeeExplainer roadFactor={roadFactor} />
                    {!auth.user && (
                        <p className="mt-2 text-muted-foreground">
                            Log in to add items to your cart.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}
