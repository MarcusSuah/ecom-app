import type { CompanySummary } from '@/types/auth';
import type { ProductCard } from '@/types/marketplace';

/** One search across the marketplace (App\Actions\Products\SearchMarketplace). */
export type SearchResults = {
    products: ProductCard[];
    items: {
        id: number;
        name: string;
        price: string;
        image_url: string | null;
        category: string;
        stock_status: string;
        stock_label: string;
        units_sold: number;
        shop: { name: string; slug: string };
    }[];
    shops: CompanySummary[];
    suppliers: CompanySummary[];
    categories: {
        id: number;
        name: string;
        slug: string;
        parent: string | null;
    }[];
    total: number;
};
