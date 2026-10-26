import { Link, router } from '@inertiajs/react';
import { Minus, Package, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import cartRoutes from '@/routes/cart';
import { show as shopShow } from '@/routes/shops';
import type { DeliveryEstimate } from '@/types';

export type CartGroup = {
    shop: {
        id: number;
        name: string;
        slug: string;
        offers_pickup: boolean;
        offers_delivery: boolean;
        address: string;
        free_threshold: string | null;
        free_threshold_minor: number | null;
        currency: string;
    };
    items: {
        product_id: number;
        name: string;
        image_url: string | null;
        unit_price: string;
        unit_price_minor: number;
        quantity: number;
        stock: number;
        line_total: string;
    }[];
    fulfilment: 'pickup' | 'delivery';
    subtotal: string;
    delivery: (DeliveryEstimate & { standard_fee?: number }) | null;
    total: string;
    total_listed?: string | null;
    problems: string[];
};

const SAVE_DELAY_MS = 500;

/**
 * Totals for one shop with the quantities on screen: the same sums the
 * server makes (SummariseCart), so they change as the buyer types.
 */
function liveTotals(group: CartGroup, quantities: Record<number, number>) {
    const subtotal = group.items.reduce(
        (sum, item) =>
            sum +
            item.unit_price_minor *
                (quantities[item.product_id] ?? item.quantity),
        0,
    );
    const delivery = group.delivery;
    const isDelivery = group.fulfilment === 'delivery';
    const isFree =
        isDelivery &&
        Boolean(delivery?.deliverable) &&
        group.shop.free_threshold_minor !== null &&
        subtotal >= group.shop.free_threshold_minor;
    const fee =
        isDelivery && delivery?.deliverable && !isFree
            ? (delivery.standard_fee ?? delivery.fee)
            : 0;

    return { subtotal, fee, isFree, total: subtotal + fee };
}

/**
 * The cart, one block per shop: quantities with live line, delivery and
 * order totals; pickup or delivery; problems that block checkout. Used by
 * the cart page and the "Local order" drawer. Changes are saved shortly
 * after the buyer stops typing.
 */
export function CartGroups({
    groups,
    onSaved,
    compact = false,
}: {
    groups: CartGroup[];
    /** Called after the server saved a change (the drawer reloads its copy). */
    onSaved?: () => void;
    compact?: boolean;
}) {
    const { format, listed } = useMoney();
    const initial = useMemo(
        () =>
            Object.fromEntries(
                groups.flatMap((group) =>
                    group.items.map((item) => [item.product_id, item.quantity]),
                ),
            ) as Record<number, number>,
        [groups],
    );
    const [quantities, setQuantities] = useState(initial);
    const timers = useRef<Record<number, number>>({});

    useEffect(() => setQuantities(initial), [initial]);

    useEffect(
        () => () =>
            Object.values(timers.current).forEach((id) =>
                window.clearTimeout(id),
            ),
        [],
    );

    const save = (productId: number, quantity: number) =>
        router.patch(
            cartRoutes.items.update.url(productId),
            { quantity },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onSaved?.(),
            },
        );

    const changeQuantity = (productId: number, value: number, max: number) => {
        const quantity = Math.max(
            1,
            Math.min(Number.isFinite(value) ? value : 1, Math.max(1, max)),
        );
        setQuantities((current) => ({ ...current, [productId]: quantity }));
        window.clearTimeout(timers.current[productId]);
        timers.current[productId] = window.setTimeout(
            () => save(productId, quantity),
            SAVE_DELAY_MS,
        );
    };

    const remove = (productId: number) => {
        window.clearTimeout(timers.current[productId]);
        save(productId, 0);
    };

    const setFulfilment = (shopId: number, fulfilment: 'pickup' | 'delivery') =>
        router.put(
            cartRoutes.fulfilment.url(shopId),
            { fulfilment },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onSaved?.(),
            },
        );

    return (
        <div className="space-y-4">
            {groups.map((group) => {
                const totals = liveTotals(group, quantities);
                const currency = group.shop.currency;

                return (
                    <section
                        key={group.shop.id}
                        aria-labelledby={`cart-shop-${group.shop.id}`}
                        className={cn(
                            'space-y-4',
                            !compact && 'rounded-lg border p-4',
                        )}
                    >
                        <h2
                            id={`cart-shop-${group.shop.id}`}
                            className="font-semibold"
                        >
                            <Link
                                href={shopShow(group.shop.slug)}
                                className="hover:underline"
                            >
                                {group.shop.name}
                            </Link>
                        </h2>

                        <ul className="divide-y">
                            {group.items.map((item) => {
                                const quantity =
                                    quantities[item.product_id] ??
                                    item.quantity;

                                return (
                                    <li
                                        key={item.product_id}
                                        className="flex flex-wrap items-center gap-3 py-3 text-sm"
                                    >
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt=""
                                                className="size-12 rounded border object-cover"
                                            />
                                        ) : (
                                            <span className="flex size-12 items-center justify-center rounded border text-muted-foreground">
                                                <Package
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">
                                                {item.name}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {format(
                                                    item.unit_price_minor,
                                                    currency,
                                                )}{' '}
                                                each
                                            </p>
                                        </div>
                                        <div className="flex items-center rounded-md border">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                aria-label={`One less ${item.name}`}
                                                disabled={quantity <= 1}
                                                onClick={() =>
                                                    changeQuantity(
                                                        item.product_id,
                                                        quantity - 1,
                                                        item.stock,
                                                    )
                                                }
                                            >
                                                <Minus />
                                            </Button>
                                            <Input
                                                type="number"
                                                inputMode="numeric"
                                                min={1}
                                                max={item.stock}
                                                value={quantity}
                                                aria-label={`Quantity of ${item.name}`}
                                                className="h-8 w-14 border-0 text-center shadow-none"
                                                onChange={(event) =>
                                                    changeQuantity(
                                                        item.product_id,
                                                        Number.parseInt(
                                                            event.target.value,
                                                            10,
                                                        ),
                                                        item.stock,
                                                    )
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                aria-label={`One more ${item.name}`}
                                                disabled={
                                                    quantity >= item.stock
                                                }
                                                onClick={() =>
                                                    changeQuantity(
                                                        item.product_id,
                                                        quantity + 1,
                                                        item.stock,
                                                    )
                                                }
                                            >
                                                <Plus />
                                            </Button>
                                        </div>
                                        <span
                                            className="w-28 text-right font-medium tabular-nums"
                                            aria-live="polite"
                                        >
                                            {format(
                                                item.unit_price_minor *
                                                    quantity,
                                                currency,
                                            )}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${item.name}`}
                                            onClick={() =>
                                                remove(item.product_id)
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>

                        <fieldset className="space-y-2 rounded-md border p-3">
                            <legend className="px-1 font-mono text-xs tracking-wide text-muted-foreground uppercase">
                                How do you want it?
                            </legend>
                            {group.shop.offers_pickup && (
                                <label className="flex cursor-pointer items-start gap-3 text-sm">
                                    <input
                                        type="radio"
                                        name={`fulfilment-${group.shop.id}`}
                                        className="mt-1"
                                        checked={group.fulfilment === 'pickup'}
                                        onChange={() =>
                                            setFulfilment(
                                                group.shop.id,
                                                'pickup',
                                            )
                                        }
                                    />
                                    <span>
                                        <span className="block font-medium">
                                            Pick up in person
                                        </span>
                                        <span className="text-muted-foreground">
                                            At {group.shop.address}. No fee; pay
                                            online or in cash when you collect.
                                        </span>
                                    </span>
                                </label>
                            )}
                            {group.shop.offers_delivery && (
                                <label className="flex cursor-pointer items-start gap-3 text-sm">
                                    <input
                                        type="radio"
                                        name={`fulfilment-${group.shop.id}`}
                                        className="mt-1"
                                        checked={
                                            group.fulfilment === 'delivery'
                                        }
                                        onChange={() =>
                                            setFulfilment(
                                                group.shop.id,
                                                'delivery',
                                            )
                                        }
                                    />
                                    <span>
                                        <span className="block font-medium">
                                            Deliver to me
                                        </span>
                                        <span className="text-muted-foreground">
                                            {group.delivery
                                                ? group.delivery.deliverable
                                                    ? `${group.delivery.distance_km.toFixed(1)} km · fee ${totals.isFree ? 'free' : format(group.delivery.standard_fee ?? group.delivery.fee, currency)}`
                                                    : 'Outside this shop’s delivery area.'
                                                : 'Set your location to see the fee.'}
                                        </span>
                                    </span>
                                </label>
                            )}
                        </fieldset>

                        <dl className="ml-auto max-w-xs space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd className="tabular-nums">
                                    {format(totals.subtotal, currency)}
                                </dd>
                            </div>
                            {group.fulfilment === 'delivery' && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Delivery
                                    </dt>
                                    <dd className="tabular-nums">
                                        {!group.delivery?.deliverable
                                            ? '—'
                                            : totals.isFree
                                              ? 'Free'
                                              : format(totals.fee, currency)}
                                    </dd>
                                </div>
                            )}
                            <div className="flex justify-between font-semibold">
                                <dt>Total</dt>
                                <dd className="tabular-nums" aria-live="polite">
                                    {format(totals.total, currency)}
                                </dd>
                            </div>
                            {listed(totals.total, currency) && (
                                <p className="text-right text-xs text-muted-foreground">
                                    Charged {listed(totals.total, currency)}
                                </p>
                            )}
                            {group.fulfilment === 'delivery' &&
                                group.shop.free_threshold_minor !== null &&
                                !totals.isFree && (
                                    <p className="text-xs text-muted-foreground">
                                        Free delivery from{' '}
                                        {format(
                                            group.shop.free_threshold_minor,
                                            currency,
                                        )}
                                    </p>
                                )}
                        </dl>

                        {group.problems.length > 0 && (
                            <ul
                                role="alert"
                                className="space-y-1 rounded-md border border-amber-500/40 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                            >
                                {group.problems.map((problem) => (
                                    <li key={problem}>{problem}</li>
                                ))}
                            </ul>
                        )}
                    </section>
                );
            })}
        </div>
    );
}
