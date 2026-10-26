import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LocationPicker } from '@/components/location-picker';
import { CartGroups } from '@/components/cart/cart-groups';
import type { CartGroup } from '@/components/cart/cart-groups';
import { Button } from '@/components/ui/button';
import { checkout } from '@/routes';
import cartRoutes from '@/routes/cart';
import { index as shopsIndex } from '@/routes/shops';
import type { CustomerLocation, Neighbourhood } from '@/types';

type Props = {
    groups: CartGroup[];
    location: CustomerLocation | null;
    savedAddresses: { id: number; label: string }[];
    neighbourhoods: Neighbourhood[];
};

export default function Cart({
    groups,
    location,
    savedAddresses,
    neighbourhoods,
}: Props) {
    const errors = usePage().props.errors as Record<string, string | undefined>;
    const [placing, setPlacing] = useState(false);
    const hasProblems = groups.some((group) => group.problems.length > 0);
    const needsLocation = groups.some(
        (group) => group.fulfilment === 'delivery',
    );

    const placeOrders = () => {
        setPlacing(true);
        router.post(
            checkout.url(),
            {},
            { preserveScroll: true, onFinish: () => setPlacing(false) },
        );
    };

    return (
        <>
            <Head title="Cart" />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <h1 className="text-xl font-semibold">Your cart</h1>

                {groups.length === 0 ? (
                    <p className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        Your cart is empty.{' '}
                        <Link
                            href={shopsIndex()}
                            className="text-primary hover:underline"
                        >
                            Browse local shops
                        </Link>
                    </p>
                ) : (
                    <>
                        {needsLocation && (
                            <LocationPicker
                                location={location}
                                neighbourhoods={neighbourhoods}
                                savedAddresses={savedAddresses}
                            />
                        )}

                        <CartGroups groups={groups} />

                        {errors.cart && (
                            <p
                                role="alert"
                                className="text-sm text-red-600 dark:text-red-400"
                            >
                                {errors.cart}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <p className="text-sm text-muted-foreground">
                                {groups.length > 1
                                    ? `You will place ${groups.length} orders, one per shop.`
                                    : 'You will place one order.'}
                            </p>
                            <Button
                                onClick={placeOrders}
                                disabled={hasProblems || placing}
                            >
                                {placing ? 'Placing…' : 'Place order'}
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Cart.layout = {
    breadcrumbs: [{ title: 'Cart', href: cartRoutes.show() }],
};
