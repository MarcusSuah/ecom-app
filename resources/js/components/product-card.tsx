import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import { ListedPrice } from '@/components/approx-price';
import { useStorefrontPanels } from '@/components/storefront/storefront-panels';
import { VerifiedMark } from '@/components/storefront/verified-mark';
import { hueStyle, initials } from '@/lib/hue';
import { show as productShow } from '@/routes/products';
import type { ProductCard as ProductCardData } from '@/types';

const stockPill: Record<string, string> = {
    in_stock: 'sf-pill sf-pill-ok',
    low_stock: 'sf-pill sf-pill-no',
    out_of_stock: 'sf-pill sf-pill-bad',
    made_to_order: 'sf-pill',
};

/**
 * A wholesale listing in the storefront style: the category's colour (or
 * the featured photo), the from-price per unit, MOQ and lead time, stock and
 * sales, and the supplier with its verification. Opening it shows the
 * product modal over the page (a new tab still gets the full page).
 */
export function ProductCard({ product }: { product: ProductCardData }) {
    const mark = initials(product.category.name);
    const { openProduct } = useStorefrontPanels();
    const open = (event: MouseEvent) => {
        if (
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.button !== 0
        ) {
            return;
        }

        event.preventDefault();
        openProduct(product.slug);
    };

    return (
        <article className="sf-card h-full">
            <Link
                href={productShow(product.slug)}
                onClick={open}
                className="sf-thumb"
                style={hueStyle(product.category.slug)}
                aria-label={`View ${product.name}`}
            >
                {product.thumbnail_url && (
                    <img
                        src={product.thumbnail_url}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        width={400}
                        height={300}
                    />
                )}
                <span className="sf-tag">{mark}</span>
                {!product.thumbnail_url && (
                    <span className="sf-glyph" aria-hidden="true">
                        {mark}
                    </span>
                )}
            </Link>
            <div className="flex flex-1 flex-col gap-2 px-3.5 pt-3 pb-3.5">
                <h3 className="text-[16.5px] leading-tight font-semibold tracking-[-0.01em]">
                    <Link
                        href={productShow(product.slug)}
                        onClick={open}
                        className="hover:underline"
                    >
                        {product.name}
                    </Link>
                </h3>
                {product.from_price && (
                    <div>
                        <div className="sf-price">
                            {product.from_price} <small>/ {product.unit}</small>
                        </div>
                        <ListedPrice value={product.from_price_listed} />
                    </div>
                )}
                <div className="sf-meta">
                    <span className="sf-mono">
                        MOQ {product.moq.toLocaleString()}
                    </span>
                    <span>{product.lead_time}</span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    <span
                        className={stockPill[product.stock_status] ?? 'sf-pill'}
                    >
                        {product.stock_label}
                    </span>
                    {product.units_sold > 0 && (
                        <span className="sf-pill">
                            {product.units_sold.toLocaleString()} sold
                        </span>
                    )}
                </div>
                <div className="sf-sup">
                    {product.supplier.is_verified && <VerifiedMark />}
                    <span className="min-w-0 truncate">
                        {product.supplier.name}
                    </span>
                    <span className="sf-meta ml-auto whitespace-nowrap">
                        {product.supplier.country}
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-1.5">
                    <Link
                        href={productShow(product.slug)}
                        onClick={open}
                        className="sf-btn sf-btn-sm"
                    >
                        Details
                    </Link>
                    <button
                        type="button"
                        onClick={() => openProduct(product.slug)}
                        className="sf-btn sf-btn-sm sf-btn-primary"
                        disabled={product.stock_status === 'out_of_stock'}
                    >
                        Add to inquiry list
                    </button>
                </div>
            </div>
        </article>
    );
}
