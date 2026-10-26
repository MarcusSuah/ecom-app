import { Link } from '@inertiajs/react';
import { Bike, Clock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { VerifiedBadge } from '@/components/verified-badge';
import { hueStyle, initials } from '@/lib/hue';
import { show as shopShow } from '@/routes/shops';
import type { DeliveryEstimate } from '@/types';

/** One shop as App\Actions\Shops\FindShops returns it. */
export type ShopCardData = {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    is_verified: boolean;
    address: string;
    landmark: string | null;
    offers_pickup: boolean;
    offers_delivery: boolean;
    is_open: boolean;
    free_threshold: string | null;
    base_fee: string;
    per_km_rate: string;
    delivery_radius_km: number;
    products_count: number;
    delivery: DeliveryEstimate | null;
};

export function ShopCard({ shop }: { shop: ShopCardData }) {
    return (
        <article
            className="sf-card h-full gap-3 p-4"
            style={hueStyle(shop.slug)}
        >
            <div className="flex items-start gap-3">
                <span className="sf-avatar" aria-hidden="true">
                    {shop.logo_url ? (
                        <img
                            src={shop.logo_url}
                            alt=""
                            loading="lazy"
                            decoding="async"
                            width={46}
                            height={46}
                            className="size-full object-cover"
                        />
                    ) : (
                        initials(shop.name)
                    )}
                </span>
                <div className="min-w-0">
                    <h3 className="font-medium">
                        <Link
                            href={shopShow(shop.slug)}
                            className="hover:underline focus-visible:outline-none"
                        >
                            {shop.name}
                        </Link>
                    </h3>
                    <p className="truncate text-sm text-muted-foreground">
                        {shop.address}
                    </p>
                    <div className="mt-1 flex flex-wrap gap-1">
                        <VerifiedBadge verified={shop.is_verified} />
                        <Badge
                            variant="outline"
                            className={
                                shop.is_open
                                    ? 'border-emerald-600/40 text-emerald-700 dark:text-emerald-300'
                                    : ''
                            }
                        >
                            <Clock aria-hidden="true" />
                            {shop.is_open ? 'Open now' : 'Closed'}
                        </Badge>
                    </div>
                </div>
            </div>

            <dl className="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <dt className="text-muted-foreground">Distance</dt>
                    <dd className="font-medium">
                        {shop.delivery
                            ? `${shop.delivery.distance_km.toFixed(1)} km`
                            : '—'}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Delivery</dt>
                    <dd className="font-medium">
                        {!shop.offers_delivery
                            ? 'Pickup only'
                            : shop.delivery
                              ? shop.delivery.deliverable
                                  ? `from ${shop.delivery.formatted_fee}`
                                  : 'Out of range'
                              : `from ${shop.base_fee}`}
                    </dd>
                </div>
                <div className="col-span-2 flex flex-wrap gap-1">
                    {shop.offers_pickup && (
                        <Badge variant="secondary">Pickup</Badge>
                    )}
                    {shop.offers_delivery && (
                        <Badge variant="secondary">
                            <Bike aria-hidden="true" />
                            Delivery within {shop.delivery_radius_km} km
                        </Badge>
                    )}
                    {shop.free_threshold && shop.offers_delivery && (
                        <Badge variant="outline">
                            Free delivery from {shop.free_threshold}
                        </Badge>
                    )}
                </div>
            </dl>
            <p className="mt-auto text-xs text-muted-foreground">
                {shop.products_count} items
            </p>
        </article>
    );
}

/** The fee rule, shared by the shops page and the landing page. */
export function DeliveryFeeExplainer({ roadFactor }: { roadFactor: number }) {
    return (
        <p>
            Each shop charges a base fee, plus a per-km rate for every kilometre
            after the first. Distance is the straight-line distance ×{' '}
            {roadFactor} to allow for roads. Orders above a shop's free-delivery
            amount are delivered free. Shops only deliver within their radius.
        </p>
    );
}
