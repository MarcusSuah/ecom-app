export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
    first_page_url: string | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    last_page_url: string | null;
};

export type VerificationStatus = 'pending' | 'approved' | 'rejected';

export type VerificationDocument = {
    index: number;
    name: string;
    mime: string;
    size: number;
};

export type Verification = {
    id: number;
    status: VerificationStatus;
    registration_number: string;
    notes: string | null;
    rejection_reason: string | null;
    reviewed_at: string | null;
    reviewer: { id: number; name: string } | null;
    created_at: string | null;
    documents: VerificationDocument[];
};

export type PlanLimitValue = {
    key: string;
    label: string;
    is_switch: boolean;
    value: number | boolean | null;
};

export type Plan = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    currency: string;
    interval: 'month' | 'year';
    formatted_price: string;
    trial_days: number;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
    limits: PlanLimitValue[];
};

export type SubscriptionStatus =
    | 'pending'
    | 'trialing'
    | 'active'
    | 'past_due'
    | 'cancelled'
    | 'expired';

export type Subscription = {
    id: number;
    status: SubscriptionStatus;
    plan: Plan;
    trial_ends_at: string | null;
    current_period_ends_at: string | null;
    ends_at: string | null;
    cancelled_at: string | null;
    is_cancellable: boolean;
};

export type Invoice = {
    id: number;
    number: string;
    amount: string;
    status: 'pending' | 'paid' | 'failed' | 'void';
    provider: string | null;
    period_starts_at: string;
    period_ends_at: string;
    paid_at: string | null;
};

export type BillingOverview = {
    subscription: Subscription | null;
    effectivePlan: Plan | null;
    plans: Plan[];
    invoices: Invoice[];
};

export type ProductCard = {
    id: number;
    name: string;
    slug: string;
    unit: string;
    unit_label: string;
    moq: number;
    currency: string;
    from_price: string | null;
    /** The from price in the listing's own currency, when shown converted. */
    from_price_listed: string | null;
    from_price_minor: number | null;
    lead_time: string;
    stock: number | null;
    stock_status: string;
    stock_label: string;
    units_sold: number;
    thumbnail_url: string | null;
    category: { name: string; slug: string };
    supplier: {
        name: string;
        slug: string;
        is_verified: boolean;
        city: string | null;
        country: string;
    };
};

export type ProductStatus = 'draft' | 'active' | 'archived';

export type CustomerLocation = {
    lat: number;
    lng: number;
    label: string;
    description: string | null;
    landmark: string | null;
    address_id: number | null;
};

export type Neighbourhood = { name: string; lat: number; lng: number };

export type DeliveryEstimate = {
    distance_km: number;
    fee: number;
    formatted_fee: string;
    is_free: boolean;
    deliverable: boolean;
};

export type OrderSummary = {
    id: number;
    number: string;
    type: string;
    fulfilment: 'pickup' | 'delivery';
    payment_method: 'online' | 'cash_on_pickup';
    payment_method_label: string;
    status: string;
    status_label: string;
    subtotal: string;
    delivery_fee: string;
    total: string;
    address: {
        label: string;
        description: string | null;
        landmark: string | null;
        distance_km: number | null;
    } | null;
    placed_at: string | null;
    created_at: string | null;
};
