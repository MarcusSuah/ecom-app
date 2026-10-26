# Mix Ventures Marketplace — Build Plan

## How to use this file

1. Open your coding agent (e.g. Claude Code) in the project folder.
2. For each phase, paste the kickoff prompt below with the phase number filled in.
3. Review the agent's end-of-phase report, commit, then start the next phase.

### Kickoff prompt (paste this, change N)

```
Read docs/BUILD_PLAN.md.
Implement Phase N only, following the "Shared context" section and the Phase N scope.
Before writing code, inspect what earlier phases actually built (migrations, models,
routes, tests) and adapt to it rather than assuming the plan was followed exactly.
If something in the plan conflicts with the existing code, tell me before changing it.
Do not build anything listed as out of scope. When finished, run the full test suite
and `php artisan migrate:fresh --seed`, then give me the end-of-phase report.
```

---

## Shared context (applies to every phase)

### The product
A two-sided marketplace based in Kigali, Rwanda, with two halves on one platform.

1. **Wholesale (B2B).** Verified suppliers list products with a minimum order quantity
   (MOQ), lead time and tiered unit prices. Buyers browse and filter, view supplier
   profiles and post a Request for Quotation (RFQ). The RFQ goes to matching verified
   suppliers, who reply with tier prices, lead time and sample terms. Buyers compare
   quotes side by side and chat with a supplier.
2. **Local shops (B2C).** Nearby shops sell single items for pickup or delivery.
   Delivery fee = shop base fee + per-km rate after the first km, free above the shop's
   order threshold. Distance = haversine × 1.3 road allowance. Customers set their
   location with a map pin and can filter to "delivers to my area only".

Cross-cutting: payments held until the buyer confirms receipt, RWF/USD currency
switching, real-time chat, and a public landing page matching the prototype.

### Multi-tenancy and subscriptions (added in Phase 1)

The platform is multi-tenant with **two subscription layers**:

- **Tenant = marketplace.** Each marketplace (Mix Ventures is the first) has its own
  companies, categories, seller plans, staff roles and, from later phases, products,
  RFQs, shops and orders. A marketplace is served from `{slug}.{TENANCY_CENTRAL_DOMAIN}`
  or its own custom domain; any other host falls back to `TENANCY_FALLBACK_TENANT`.
- **Single database.** Every tenant-owned table has a `tenant_id` and its model uses the
  `BelongsToTenant` trait (global scope + auto-fill). `IdentifyTenant` runs before route
  model binding, so a record from another marketplace is a 404.
- **Accounts are shared.** `users` has no tenant. Roles are assigned per tenant through
  Spatie Permission "teams" (`tenant_id` on the pivot). The first time a signed-in user
  uses a marketplace they become a buyer there. One company per user per marketplace.
- **Platform admins** (`users.is_platform_admin`) pass every Gate check and manage
  marketplaces and marketplace plans under `/platform`.
- **Layer 1 — marketplace plans.** The platform sells `plans` with `audience = marketplace`
  (no tenant) to tenants. A marketplace without a current plan, or a suspended one, is
  closed to everyone except its admins and platform admins (`marketplace.open` middleware).
- **Layer 2 — seller plans.** Each marketplace sells `plans` with `audience = seller` to its
  supplier and shop companies. A company with no subscription uses the default seller plan.
- **Limits.** `App\Enums\PlanLimit` lists what plans control: `max_companies`,
  `custom_domain` (marketplace); `max_products`, `monthly_quotes`, `commission_percent`,
  `featured_listing` (seller). Use `$subscriber->planLimit()` / `withinPlanLimit()`.
  **New phases must enforce the limits that apply to them**: Phase 2 `max_products`,
  Phase 3 `monthly_quotes`, Phase 5 `commission_percent` (per seller plan, falling back to
  the configured platform percentage).
- **Billing.** `subscriptions` (polymorphic subscriber: tenant or company) and
  `subscription_invoices`. Payments go through the `SubscriptionGateway` interface
  (`fake` driver). Phase 5 added MTN MoMo / Flutterwave for **order** payments
  (`PaymentGateway`); subscription billing still uses the fake driver until a
  `SubscriptionGateway` adapter over them is written (MoMo needs a UUID reference per charge). Admins can
  record offline payments. `billing:process-renewals` runs daily.
- **New tenant-scoped tables** in later phases must have `tenant_id`, use
  `BelongsToTenant`, scope unique indexes by tenant, and get a cross-tenant test.
  Queued jobs carry the tenant in hidden Context (`tenant_id`); restore it with
  `CurrentTenant::runAs()` in the job.

### Stack
- Laravel 13 with the official React starter kit (Inertia + React + TypeScript + Tailwind + shadcn/ui)
- spatie/laravel-permission for roles (teams enabled); Sanctum for a future mobile API
- MySQL; Pest for tests
- Laravel Scout + Meilisearch (Phase 2+), Laravel Reverb (Phase 3+), queues and scheduler
- Leaflet + OpenStreetMap for maps (Phase 4+)

### Conventions
- Money is stored as integers (RWF whole francs; USD in cents) with a `currency`
  column next to every price. Never use floats for money. Use the `App\Support\Money`
  helper for formatting and conversion.
- Coordinates are `decimal(10,7)` lat/lng.
- Form Requests for validation, Policies for authorization, PHP backed enums for
  statuses and types, action classes in `app/Actions` for business logic, thin controllers.
- Every list page is paginated. Every owner-scoped resource is protected by a Policy.
- External services (SMS, payments, exchange rates) sit behind an interface with a
  `log`/`fake` driver for local development and tests. No credentials in code.
- Public counts and stats are always computed from the database (cached), never hardcoded.
- A company is "verified" only when its latest verification is approved. Nothing else
  can set this.

### Roles
`buyer`, `supplier`, `shop_owner`, `verifier`, `admin` — assigned per marketplace.
New sign-ups are buyers. Platform admins are a flag on the account, not a role.

### Full data model (each phase creates its own tables)
| Phase | Tables |
|---|---|
| 1 | tenants, users, companies, verifications, categories, plans, subscriptions, subscription_invoices (+ Spatie tables, notifications) |
| 2 | products, price_tiers, product images (media) |
| 3 | rfqs, rfq_recipients, quotes, quote_tiers, conversations, conversation_participants, messages |
| 4 | shops (delivery settings), shop_products, addresses, carts, cart_items, orders, order_items |
| 5 | payments, payouts, disputes, order_status_history |
| 6 | currency_rates, contact_messages |

### End-of-phase report (every phase)
- What was built, with the main routes and screens
- How to log in as each seeded role
- Decisions you made that I should review
- Anything skipped or left as a TODO, and why
- Test results
- A short proposed plan for the next phase, adjusted to what now exists

---

## Phase 1 — Foundation: accounts, companies, verification, categories (+ tenancy and subscriptions)

### Scope
1. **Setup.** Laravel 13 with the React starter kit, Spatie Permission, Sanctum and Pest.
   README with setup steps.
2. **Roles.** Seed the five roles. New sign-ups get `buyer`.
3. **Company onboarding.** A logged-in user can create one company of type `supplier` or
   `shop`. This assigns the `supplier` or `shop_owner` role. Owners can edit their
   company profile.
   - companies: tenant_id, owner_id, type, name, slug, description, country, city, phone,
     email, logo, is_featured (admin only), timestamps
4. **Verification.**
   - verifications: tenant_id, company_id, status (pending|approved|rejected),
     registration_number, documents (private disk; PDF/JPG/PNG; max 5 MB each),
     reviewer_id, reviewed_at, rejection_reason, notes
   - Owners submit an application. Verifiers and admins see a pending queue, view the
     documents and approve or reject with a reason.
   - Owners can resubmit after a rejection. Only one pending application at a time.
   - Database notifications to the owner on approve/reject.
   - A "Verified" badge component, shown only for approved companies.
5. **Categories.** Admin CRUD with sort order and optional parent. Seed 10: Food &
   Beverages, Agriculture, Construction Materials, Electronics, Apparel & Textiles,
   Home & Kitchen, Health & Beauty, Packaging, Industrial Equipment, Office & Stationery.
6. **Admin dashboard.** Counts for users, companies by type, verified suppliers and
   pending verifications, all computed from the database.
7. **Tenancy and subscriptions** as described in Shared context.
8. **Seeders.** One user per role (password: `password`). Ten sample companies: a mix of
   suppliers and shops, some verified, some pending, one rejected.

### Tests
- Creating a company assigns the right role
- Only verifiers and admins can approve or reject
- A company cannot be verified without an approved verification
- Owners cannot view other companies' documents
- A user cannot create a second company
- Tenant isolation, plan limits, subscription lifecycle, webhook signature and idempotency

### Out of scope
Products, shop delivery settings, RFQs, chat, orders, payments, currency, landing page.

---

## Phase 2 — Wholesale catalogue, search and supplier profiles

### Scope
1. **Products** (supplier companies only).
   - products: company_id, category_id, name, slug, description, unit (piece|kg|carton|
     litre|metre|set), moq, lead_time_min_days, lead_time_max_days, currency, origin_country,
     sample_available, sample_price, status (draft|active|archived), timestamps
   - Up to 8 images per product (resized thumbnails).
2. **Price tiers.**
   - price_tiers: product_id, min_qty, unit_price
   - Rules: at least one tier; the first tier's min_qty equals the MOQ; min_qty values are
     strictly increasing; unit prices do not increase as quantity rises.
   - An action `PriceForQuantity` returns the tier, unit price and total for a quantity,
     or an error below the MOQ.
3. **Supplier dashboard.** Product CRUD with a tier editor that shows validation errors
   inline, plus draft/publish/archive.
4. **Public catalogue** at `/products`, using Scout + Meilisearch (database driver in tests).
   - Full-text search plus filters: category, verified suppliers only, max MOQ, price range
   - Sort: relevance, lowest price, lowest MOQ, newest
   - Only active products appear. Unverified suppliers' products appear but without the badge.
5. **Product page.** All tiers as a table, a quantity input that shows unit price and total
   live, MOQ, lead time, sample terms, supplier card, and a "Request a quote" button
   (links to a placeholder route for Phase 3).
6. **Categories page.** The 10 categories with active product counts.
7. **Supplier profile** at `/suppliers/{slug}`: verification status, country/city, member
   since, top categories (by product count), products grid. Show response time as "—"
   for now; Phase 3 fills it in.
8. **Supplier directory** at `/suppliers` with verified filter. Admins can toggle
   `is_featured`.
9. **Seeders.** About 200 sample products across the seeded suppliers, with realistic tiers.

### Tests
- Tier validation rules
- `PriceForQuantity` at boundaries and below the MOQ
- Search filters (verified only, max MOQ)
- Only the owner can edit a product; buyers cannot create products

### Out of scope
RFQs, chat, ordering, local shops, currency conversion.

---

## Phase 3 — RFQs, quotes, comparison and chat

### Scope
1. **RFQs.**
   - rfqs: buyer_id, category_id, title (what they need), quantity, unit, deliver_city,
     needed_by, notes, status (open|closed|awarded|expired), expires_at (default 14 days),
     optional product_id when started from a product page
   - Full RFQ form. A small quick form (product + quantity) that pre-fills the full form;
     if the user is a guest, keep the draft in the session through login.
2. **Matching.** Action `MatchSuppliersForRfq`: verified suppliers with at least one active
   product in the category whose MOQ ≤ requested quantity, capped at 20. If the RFQ came
   from a product, always include that product's supplier if verified.
   - rfq_recipients: rfq_id, company_id, notified_at, viewed_at, declined_at
   - A queued job notifies each match by database notification, email and SMS. SMS goes
     through an `SmsSender` interface with `log` and Africa's Talking/Twilio drivers.
3. **Supplier RFQ inbox.** List of received RFQs, mark viewed on open, decline with a
   reason, or submit one quote per RFQ (editable until awarded).
   - quotes: rfq_id, company_id, currency, lead_time_min_days, lead_time_max_days,
     sample_terms, valid_until, message, status (submitted|withdrawn|accepted|rejected)
   - quote_tiers: quote_id, min_qty, unit_price (same rules as price tiers)
4. **Buyer comparison.** A side-by-side table: price at the requested quantity, total, lead
   time, sample terms, verified badge, supplier response time. The buyer can accept one
   quote (RFQ becomes awarded, others rejected) or close the RFQ.
5. **Chat** with Laravel Reverb. *(Built with polling every 5 s instead, at the owner's request;
   `MessageSent` is already broadcast on private `conversations.{id}` channels for when Reverb is added.)*
   - conversations (optionally linked to an rfq or product), conversation_participants,
     messages (body, attachment, read_at)
   - "Open chat" from a quote, product page or supplier profile. Real-time delivery,
     unread counts in the header, image/PDF attachments.
6. **Response time.** Median time from `notified_at` to quote submission over the last 90
   days, cached and shown on supplier profiles and in the comparison table.
7. **Scheduler.** Expire open RFQs past `expires_at`.

### Tests
- Matching respects verification, category and MOQ
- A supplier cannot quote on an RFQ they did not receive
- One quote per supplier per RFQ
- Accepting a quote rejects the others
- Only participants can read a conversation
- Response time calculation

### Out of scope
Turning an accepted quote into a paid order (Phase 5), local shops, currency conversion.

---

## Phase 4 — Local shops, delivery fees, cart and orders

### Scope
1. **Shop settings** (shop companies only).
   - shops: company_id, lat, lng, address_text, landmark, offers_pickup, offers_delivery,
     base_fee, per_km_rate, free_threshold, delivery_radius_km, opening_hours (JSON), currency
   - Location set with a Leaflet map pin. *(Built without a map at the owner's request: browser
     geolocation, a Kigali neighbourhood list in `config/marketplace.php`, saved addresses and
     latitude/longitude fields. A map can replace the picker later.)*
2. **Shop products.**
   - shop_products: shop_id, category_id, name, description, price, stock, image, is_active
3. **Delivery fee.** Action `CalculateDeliveryFee(shop, lat, lng, subtotal)`:
   ```php
   $km  = haversine($shop->lat, $shop->lng, $lat, $lng) * config('marketplace.road_factor', 1.3);
   if ($km > $shop->delivery_radius_km) { /* not deliverable */ }
   $fee = $subtotal >= $shop->free_threshold
       ? 0
       : $shop->base_fee + (int) round(max(0, $km - 1) * $shop->per_km_rate);
   ```
   Return distance, fee, whether it is free and whether the location is deliverable.
4. **Customer location.**
   - addresses: user_id, label, lat, lng, description, landmark, is_default
   - Guests pick a pin stored in the session. Logged-in users can save addresses.
5. **Local shops page** at `/shops`: sorted by distance from the chosen location; each card
   shows distance, delivery fee estimate, free-delivery threshold, pickup/delivery
   availability and open/closed status. A "Delivers to my area only" filter. A shop page
   lists its products.
6. **Cart.** One cart holding items from several shops, grouped by shop. For each shop the
   customer picks pickup or delivery; fees are recalculated live. Stock is checked at checkout.
7. **Orders.** Checkout creates one order per shop.
   - orders: buyer_id, seller_company_id, type (local|wholesale), fulfilment
     (pickup|delivery), address snapshot (JSON), subtotal, delivery_fee, total, currency,
     status, placed_at
   - order_items: order_id, purchasable (polymorphic), name snapshot, unit_price, quantity, line_total
   - For now orders are placed with status `pending_payment` and a temporary "Confirm
     order (test)" button that moves them to `placed`. Phase 5 replaces this with payment.
8. **Shop owner orders dashboard.** Accept, preparing, ready for pickup / out for delivery,
   completed, cancelled (restock on cancel).
9. **Seeders.** About 15 shops placed around Kigali with realistic coordinates and 10–30
   products each.

### Tests
- Fee within the first km is the base fee only
- Fee scales per km beyond the first
- Free delivery at and above the threshold
- Outside the radius is not deliverable
- "Delivers to my area" filter
- Checkout splits the cart into one order per shop and decrements stock

### Out of scope
Real payments, wholesale orders, currency conversion.

---

## Phase 5 — Payments, payment protection and order lifecycle

### Scope
1. **Wholesale orders.** Create an order from an accepted quote (quantity and tier price
   from the quote) or directly from a product page at its tier price (quantity ≥ MOQ).
   Same `orders` table with `type = wholesale`.
2. **Payment gateway.** A `PaymentGateway` interface (`initiate`, `verifyWebhook`,
   `refund`) with drivers: `fake` (local and tests), `mtn_momo` and `flutterwave`
   (structured against their sandbox APIs; credentials from env).
   - payments: order_id, provider, provider_reference, amount, currency, status
     (initiated|held|released|refunded|failed), held_at, released_at, raw payload
   - Webhooks are signature-verified and idempotent.
   - Add matching `mtn_momo` and `flutterwave` drivers for the `SubscriptionGateway`.
3. **Hold and release.**
   - A successful payment moves the order to `paid` and the payment to `held`.
   - The buyer confirms receipt, which releases the payment and creates a payout record.
   - Auto-release after a configurable number of days after delivery if no dispute is open.
   - Platform commission as a percentage from the seller's plan (`commission_percent`),
     falling back to a configurable default, recorded on the payout.
   - payouts: company_id, order_id, gross, commission, net, status (pending|paid), paid_at,
     reference. Admins mark payouts as paid manually for now.
4. **Disputes.**
   - disputes: order_id, opened_by, reason, details, status (open|resolved_release|
     resolved_refund), resolved_by, resolution_note
   - The buyer can open one before release. Admins resolve by releasing or refunding.
5. **Order state machine.** One service that controls all transitions for both order types
   and writes `order_status_history`. Invalid transitions throw.
   - Local: pending_payment → paid → accepted → preparing → ready_for_pickup |
     out_for_delivery → delivered → completed (or cancelled / refunded)
   - Wholesale: pending_payment → paid → in_production → shipped → delivered → completed
6. **Notifications** to buyer and seller on every status change (database + email; SMS for
   payment confirmed and out for delivery).
7. Remove the Phase 4 test confirmation button.
8. **UI wording.** Call this "Payment protection" in the interface. Leave a config flag to
   rename it "Escrow" once the legal and licensing position is confirmed.

### Tests
- Webhook idempotency and signature rejection
- Funds release only on confirmation, auto-release or dispute resolution
- Commission and net amounts
- Invalid state transitions are rejected
- A dispute blocks auto-release

### Out of scope
Currency conversion, landing page, content pages.

---

## Phase 6 — Currency, landing page, content pages and production polish

### Scope
1. **Currency.**
   - currency_rates: base, quote, rate, source, fetched_at
   - A daily scheduled job fetches rates through an `ExchangeRateProvider` interface; admins
     can set a manual override. A currency switcher (RWF default, USD) in the header,
     remembered per user/session.
   - Prices display converted with a small "approx." marker when not in the listing
     currency. Charges always happen in the order's own currency.
   - Footer shows the current rate, e.g. "1 USD = 1,450 RWF".
2. **Landing page** matching the prototype, sections in this order:
   - Header: logo "Mix Ventures Marketplace", currency switcher, "Request quotes" link
   - Hero: eyebrow "Wholesale, sourced locally and globally"; heading "Buy in bulk from
     suppliers you can check."; supporting line about tiered prices, MOQs, lead times and
     one inquiry to several suppliers; buttons Shop local / Browse wholesale / Post a request
   - Live stats: verified suppliers, listed products, countries (computed and cached)
   - Quick quote card "Get quotes in 48 hours" with product and quantity (pre-fills the RFQ form)
   - Local shops: location picker, "Delivers to my area only" filter, shop cards, and the
     delivery fee explanation
   - Wholesale categories: the 10 categories
   - Wholesale products: verified-only toggle, max MOQ, sort, product cards with tier pricing
   - Featured suppliers: registration status, response time, top categories
   - RFQ section "One request, several quotes." with the three steps and the full form
     (what you need, category, quantity, deliver to, needed by, notes)
   - Footer: Buy (Browse products, Request quotes, Find suppliers), Sell (List your products,
     Get verified, Seller guide), Support (Payment protection, Shipping and customs, Contact us)
   - The marketplace name and branding come from the current tenant.
3. **Content pages** for every footer link. Contact form stores contact_messages and
   notifies admins.
4. **Polish.** Responsive down to 360px, accessibility pass (labels, focus states,
   contrast), SEO meta and Open Graph tags, `sitemap.xml`, image optimisation, query
   caching on hot pages, custom 404/500 pages, rate limiting on forms.
5. **Production notes** in the README: queue worker, scheduler, Reverb, Meilisearch,
   storage, mail and SMS configuration, backups, wildcard DNS and TLS for tenant subdomains.
6. **Seed clean-up.** A separate demo seeder so production can start empty (done in Phase 1:
   `DemoSeeder` does not run in production).

### Tests
- Rate conversion and rounding
- Stats reflect the database
- Smoke tests that every public page returns 200
- Contact form validation and rate limiting

### Out of scope
Mobile app, multi-language, advanced analytics.

---

## Phase 7 — Operations and reports

### Scope
1. **Reports with CSV export** (streamed, no extra package), for a chosen period:
   - Sellers (`/seller/reports`): gross sales, orders, refunds, commission and net; downloads
     for orders, order lines and payouts.
   - Marketplace admins (`/admin/reports`, permission `reports.view`): sales, commission,
     refunds and payouts, with a per-seller breakdown; downloads for orders, payouts and
     commission by seller.
   - Platform admins (`/platform/analytics`): every marketplace side by side (sales, orders,
     commission, sellers, members, plan and monthly plan revenue) with a trend; CSV download.
2. **PDF documents** (barryvdh/laravel-dompdf), branded with the marketplace:
   - Order invoice (unpaid) / receipt (paid) for the buyer, the seller and admins.
   - Subscription invoices for sellers and marketplaces.
   - Payout statements for a seller over a period.
   - The buyer's receipt is attached to the "payment received" email.
3. **Seller emails:** a low-stock alert when an order takes an item to low stock or out of
   stock (once per crossing, for shop items and wholesale products with stock).

### Tests
- Report totals reflect the database for the period and stay within the marketplace
- CSV downloads have the expected header and rows
- PDFs are only available to the people allowed to see them and render
- Low-stock alerts fire once when stock crosses the threshold

### Out of scope
Scheduled report emails, accounting-system integrations, tax/VAT invoices.
