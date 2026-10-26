export type User = {
    id: number;
    slug: string;
    name: string;
    email: string;
    phone?: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    /** Roles on the current marketplace only. */
    roles: string[];
    permissions: string[];
    /** Runs the whole platform; passes every permission check. */
    is_platform_admin: boolean;
    [key: string]: unknown;
};

export type CompanySummary = {
    id: number;
    name: string;
    slug: string;
    type: 'supplier' | 'shop';
    type_label: string;
    city: string | null;
    country: string;
    logo_url: string | null;
    is_featured: boolean;
    is_verified: boolean | null;
    verification_status: 'pending' | 'approved' | 'rejected' | null;
    created_at: string | null;
};

export type TenantSummary = {
    id: number;
    name: string;
    slug: string;
    currency: string;
    /** The platform's own marketplace: no platform plan. */
    is_system?: boolean;
};

export type Auth = {
    user: User;
    /** The signed-in user's company on the current marketplace. */
    company: CompanySummary | null;
    /** Set while a super admin manages another business on its behalf. */
    actingCompany?: { name: string; slug: string } | null;
};

/** Search and link-preview tags for the page (App\Support\PageMeta). */
export type PageMeta = {
    title: string | null;
    description: string;
    image: string | null;
    url: string;
    type: string;
};

export type DisplayCurrency = {
    /** Prices are shown in this currency; buyers pay in the listing's own. */
    display: string;
    options: string[];
    /** e.g. "1 USD = 1,450 RWF"; null until a rate is known. */
    rate_label: string | null;
    /** Major-unit rate from each currency into the display one; null when unknown. */
    rates: Record<string, number | null>;
    /** Minor units per major unit, e.g. RWF 1, USD 100. */
    subunits: Record<string, number>;
};

export type MarketplaceSettings = {
    /** The marketplace's own logo, when uploaded under Settings. */
    logo_url?: string | null;
    tagline: string | null;
    about: string | null;
    support_phone: string | null;
    whatsapp_number: string | null;
    address: string | null;
    business_hours: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    x_url: string | null;
    linkedin_url: string | null;
    support_email: string | null;
};

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    /** The marketplace this request runs for. */
    tenant: TenantSummary | null;
    /** Unread notifications on this marketplace. */
    unreadNotificationsCount: number;
    /** Unread chat messages on this marketplace. */
    unreadMessagesCount: number;
    /** Items in the cart on this marketplace. */
    cartCount: number;
    /** When an untouched local order is emptied; null when it is empty. */
    cartExpiresAt?: string | null;
    /** "Payment protection" (or "Escrow" once allowed). */
    paymentProtectionLabel: string;
    currency: DisplayCurrency;
    meta: PageMeta;
    /** Top-level categories for the storefront search and category rail. */
    storefrontCategories: { name: string; slug: string; count: number }[];
    /** Contact details and links from Admin > Settings. */
    marketplace: MarketplaceSettings;
    [key: string]: unknown;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
