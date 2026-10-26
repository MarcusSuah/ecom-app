# Mix Ventures Marketplace

A multi-tenant B2B and B2C marketplace platform (Laravel 13, Inertia, React, TypeScript,
Tailwind, shadcn/ui). Each marketplace (tenant) has its own suppliers, shops, categories and
seller plans, and pays the platform a subscription. See [docs/BUILD_PLAN.md](docs/BUILD_PLAN.md)
for the phased plan.

## Requirements

- PHP 8.4, Composer
- Node 22+ and npm
- MySQL 8 (tests use in-memory SQLite)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env
php artisan migrate --seed
php artisan storage:link
npm install
npm run build        # or: composer run dev
```

`composer run dev` starts the web server, queue worker, logs and Vite together.

### Tenancy settings (.env)

| Variable | Default | Meaning |
|---|---|---|
| `TENANCY_CENTRAL_DOMAIN` | `localhost` | Marketplaces are served from `{slug}.{central domain}` |
| `TENANCY_FALLBACK_TENANT` | `mix-ventures` | Marketplace used for any other host (e.g. `localhost`, an IP) |
| `BILLING_GATEWAY` | `fake` | Subscription payment driver (`fake` completes every payment at once) |
| `BILLING_FAKE_WEBHOOK_SECRET` | `fake-webhook-secret` | HMAC secret for fake webhooks |
| `BILLING_GRACE_DAYS` | `7` | Days a past-due subscription stays usable |

### Search

The catalogue uses Laravel Scout. With `SCOUT_DRIVER=database` (the default, and what the
tests use) searches run as SQL on the `products` table and need no extra service. For
Meilisearch set `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` and
`SCOUT_QUEUE=true`, then run:

```bash
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Product"
```

One index serves every marketplace; every search filters on `tenant_id`.

Run `php artisan storage:link` so product photos and logos are served from `public/storage`.

### Local shops and delivery

Delivery fee = shop base fee + per-km rate for every km after the first, free at or above the
shop's threshold; distance = haversine × `MARKETPLACE_ROAD_FACTOR` (default 1.3). Opening hours
are read in `MARKETPLACE_TIMEZONE` (default `Africa/Kigali`). Locations are picked with the
browser's location, the Kigali neighbourhood list in `config/marketplace.php`, or saved addresses.

Orders start as "awaiting payment" and reach the shop once paid (see Payments below).

### Payments and payment protection

Buyers pay into the platform; the money is held until the buyer confirms receipt, until
`PAYMENTS_AUTO_RELEASE_DAYS` (default 3) after delivery with no dispute, or until an admin
resolves a dispute. On release a payout is recorded for the seller, net of the seller plan's
`commission_percent` (or `PAYMENTS_DEFAULT_COMMISSION_PERCENT`, default 5). Payouts are sent
outside the app and marked paid at `/admin/payouts`.

| Variable | Default | Meaning |
|---|---|---|
| `PAYMENT_PROVIDERS` | `fake` | Comma-separated providers offered at checkout: `fake`, `mtn_momo`, `flutterwave` |
| `PAYMENTS_FAKE_WEBHOOK_SECRET` | `fake-payments-secret` | HMAC secret for fake webhooks (`X-Fake-Signature`) |
| `MTN_MOMO_BASE_URL`, `MTN_MOMO_ENVIRONMENT` | sandbox | MoMo API host and target environment |
| `MTN_MOMO_COLLECTION_USER_ID` / `_API_KEY` / `_SUBSCRIPTION_KEY` | | Collections product credentials |
| `MTN_MOMO_DISBURSEMENT_USER_ID` / `_API_KEY` / `_SUBSCRIPTION_KEY` | | Disbursements product credentials (refunds) |
| `MTN_MOMO_CURRENCY_OVERRIDE` | | e.g. `EUR`, which the MoMo sandbox requires |
| `FLUTTERWAVE_SECRET_KEY`, `FLUTTERWAVE_WEBHOOK_HASH` | | API secret and the webhook "secret hash" set in the dashboard |
| `PAYMENTS_PROTECTION_LABEL` | `Payment protection` | Name shown to buyers for the held-funds guarantee |

Providers post results to `/webhooks/payments/{provider}`. Set that URL as the Flutterwave
webhook; MoMo receives a signed callback URL with each request. Webhooks are verified (MoMo
and Flutterwave are re-checked against their APIs) and safe to receive more than once.

### Currency

Visitors pick RWF or USD in the header; the choice is kept for the session and on their
account. Storefront prices, product tiers, live totals and the cart are then shown in that
currency, with "Charged RWF …" underneath when converted: orders are always charged in the
listing's own currency.

Rates are stored as "1 USD = x RWF". `currency:fetch-rates` (daily, and once while seeding)
fills the shared rate from `EXCHANGE_RATE_PROVIDER`:

| Variable | Default | Meaning |
|---|---|---|
| `EXCHANGE_RATE_PROVIDER` | `fixed` | `fixed` (no network) or `open_er_api` (free daily rates from open.er-api.com, no key) |
| `EXCHANGE_RATE_FIXED_RWF` | `1450` | RWF per USD for the `fixed` provider |

A failed fetch keeps the previous rate. Marketplace admins can override the rate for their own
marketplace at `/admin/currency`.

### Content pages, contact form and SEO

The footer links to `/sell`, `/get-verified`, `/seller-guide`, `/payment-protection`,
`/shipping-and-customs` and `/contact`. The pages quote live figures (seller plans, commission,
release days). Contact messages are stored per marketplace. Users with `contact.manage` (admins)
are notified and answer them at `/admin/contact-messages`. The form allows 3 messages a
minute and 20 a day per IP, and has a honeypot field.

Each marketplace serves its own `/sitemap.xml` (cached for an hour) and `/robots.txt`, which
points to the sitemap and blocks the private areas. Pages carry description, canonical and Open
Graph tags. They are printed by the root view, so crawlers get them even without SSR. With
`APP_DEBUG=false`, 403, 404, 419, 429, 500 and 503 errors show a branded page. JSON requests
keep JSON errors.

### Superadmin, monitoring and settings

`PlatformSeeder` creates one default superadmin (`SUPERADMIN_EMAIL`, default
`superadmin@example.com`; `SUPERADMIN_PASSWORD`, default `password` outside production). In
production without `SUPERADMIN_PASSWORD`, a random password is generated and written to the
log. The `super_admin` role always holds every permission, including ones created later.

Marketplace admins have these pages:

- **Settings** (`/admin/settings`): name, contact details, tagline and social links for the
  storefront.
- **Notifications** (`/admin/notifications`): announcements to members.
- **Audit log** (`/admin/audit-logs`): changes people made, with before and after values.
- **Activity log** (`/admin/activity`): sign-ins, failed attempts, pages and changes, with
  device and IP. Admins can block an account or an IP address here.

Activity entries are deleted once they are `ACTIVITY_RETENTION_MONTHS` (6) months old (checked daily). Sign-in locations are
looked up only when `ACTIVITY_GEOIP_ENABLED=true` and `ACTIVITY_GEOIP_ENDPOINT` is an HTTPS
URL with `{ip}`.

### Requests for quotes from guests

Visitors can send a request without an account. They give a name and email, and get a
private link (also emailed) to follow the quotes. Each new quote is emailed to them. Signing
in with the same email, once verified, moves the request into their account, where they can
accept a quote, chat and order. Only the cart and checkout need an account.

### Product photos and stock

Suppliers and shops add photos in the product form, with a preview before saving. The starred
photo is featured on cards, and the rest show on the product page or the shop item view.
Wholesale products can have a stock count; leave it empty for made-to-order. Orders reduce
stock, cancellations and refunds put it back, and paid orders count as "sold". A product is
out of stock, and cannot be ordered, once its stock cannot fill one minimum order.
The seller gets an email and in-app notice when an order takes a listing into low stock
(under three minimum orders, or 5 items or fewer for shops) and again when it runs out.

### New accounts and the storefront

- **Approval:** new sign-ups wait for a marketplace admin (Users → approve or turn down, with an
  email either way). Until then every page sends them to a waiting page; guests can still browse.
  Admins can switch this off under Settings → Accounts and tax.
- **Staying on the storefront:** after signing in people land on the home page. Product details
  open in a modal, and the header's *Inquiry list* and *Local order* buttons open drawers. The
  inquiry list is kept in the browser (guests too) and sends one request per product to its
  supplier. The local order is the cart, with quantities and totals that update as you type.
- **Chat:** "Chat with supplier" opens a dialog with the seller's details and the conversation.
- **Photos:** any size is accepted. The browser shrinks large photos before upload, and the
  server brings every photo to the gallery format (main photo 600–1200 px, 400 px square thumbnail).
  Listings take up to 10 photos. Users set their own profile photo under Settings → Profile.
- **Home page and search:** every product, shop item, shop, supplier and category is listed,
  with "Show more" for the next page. The header search suggests matches from all of them while
  you type; Enter opens `/search` with every match. Other searches update as you type.
- **Suspending a business:** admins suspend or reinstate a company (Companies). A suspended
  business, or one whose owner is deactivated, blocked or not approved, is hidden from the
  storefront, search and requests.
- **Managing a business as the super admin:** Companies → *Manage* opens that business's seller
  screens (products, photos, shop items, company profile) without signing in as its owner; a
  banner offers *Stop managing*. Only the `companies.manage` permission (super admin) allows it.
- **Logo:** upload the marketplace logo under Settings; it replaces the mark in the storefront
  header and footer and the dashboard.
- **Stock:** carts, orders, quote requests and the inquiry list never take more than is in stock.
- **Cash on pickup:** for pickup orders the buyer can choose to pay the shop in cash instead of
  online. The shop accepts and prepares the order, then confirms *Cash received* at handover, which
  records the payment and payout (commission still owed) and completes the order.
- **Idle baskets:** the local order and the inquiry list are emptied after 10 minutes without a
  change (`Cart::IDLE_MINUTES`); the page then refreshes and shows 0.
- **Reporting a problem:** buyers can add up to 6 photos as proof (3 suggested), kept privately and
  shown to the buyer, the seller and dispute staff.
- **Documents:** receipts, invoices, payout statements and quotes (PDF) carry the marketplace logo.
- **The platform's own marketplace** (`TENANCY_FALLBACK_TENANT`) is always open and is never
  invoiced for a platform plan.

### Reports and documents

PDFs are rendered with `barryvdh/laravel-dompdf`, branded with the marketplace's settings.

- **Sellers:** *Reports* shows sales, refunds, commission and payouts for any date range. CSV
  downloads cover orders, order items and payouts, and the payout statement is a PDF.
- **Marketplace staff (`reports.view`):** *Reports* shows the same totals for the whole
  marketplace with the top sellers. CSV downloads cover orders, items, payouts and sales by seller.
- **Platform admins:** *Analytics* puts every marketplace, business (supplier) and store (shop)
  side by side: sales, revenue, expenses, net and the VAT included in sales, converted to USD,
  with CSV downloads. VAT uses each marketplace's rate (Settings, 18% by default).
- **Orders:** the order page offers the invoice (before payment) or the receipt (after), and the
  buyer's "paid" email has the receipt attached. Plan invoices have a PDF link in Billing.

Reports show each order's own currency, or everything converted into RWF or USD (the switch
above the totals) at the rate in use; the rate, its source and since when are printed with the
report and in the orders CSV. Platform analytics is always converted (USD by default). Every rate
change (daily fetch, manual rate or a return to the daily rate) is kept with who made it under
Exchange rates; the rate form starts from the rate in use. CSV cells starting with `=`, `+`, `-` or `@` get a leading `'` so spreadsheets do
not run them as formulas.

### SMS and chat

RFQ notifications go out by database notification, email and SMS. `SMS_DRIVER=log` (default)
writes texts to the log; for real delivery use `africastalking` (`AFRICASTALKING_USERNAME`,
`AFRICASTALKING_API_KEY`, optional `AFRICASTALKING_SENDER_ID`, `AFRICASTALKING_SANDBOX`) or
`twilio` (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM`).

Notifications are sent by a queued job, so run a worker (`php artisan queue:work`, included in
`composer run dev`). Chat pages refresh by polling every few seconds. Messages are also
broadcast on private `conversations.{id}` channels, so adding Laravel Reverb later only needs
the server, `BROADCAST_CONNECTION=reverb` and an Echo listener on the chat page.

Locally, `http://localhost:8000` is Mix Ventures and `http://kigali-fresh.localhost:8000` is
the second demo marketplace (most browsers resolve `*.localhost` to 127.0.0.1).

Seeding also runs `DemoMediaSeeder` (about four minutes): 8 generated photos for every product
and shop item, a logo per company, and descriptions, cities and phones where missing. Run
`php artisan db:seed --class=DemoMediaSeeder` again any time to top up new listings.

## Demo accounts

All passwords are `password`. Seeded only outside production.

| Email | What it is |
|---|---|
| `superadmin@example.com` | Default superadmin, created in every environment: platform admin (`/platform`) with the `super_admin` role holding every permission |
| `admin@example.com` | Admin of Mix Ventures: `/admin` |
| `verifier@example.com` | Verifier of Mix Ventures: `/admin/verifications` |
| `supplier@example.com` | Supplier (verified, Pro plan, featured) on Mix Ventures; shop owner on Kigali Fresh |
| `shop@example.com` | Shop owner (verified) on Mix Ventures |
| `buyer@example.com` | Buyer on Mix Ventures, with orders at every payment stage (one disputed, one due for auto-release) |
| `admin@kigalifresh.example` | Admin of Kigali Fresh Market |

Other company owners: `supplier2@` … `supplier6@`, `shop2@` … `shop4@example.com`.

## Tests

```bash
php artisan test --compact
```

## Scheduled tasks

Run the scheduler every minute in production (`* * * * * php artisan schedule:run`).
It runs `billing:process-renewals` daily, which invoices ended periods, renews free plans
and expires unpaid or cancelled subscriptions; `rfqs:expire` hourly, which closes
requests for quotation past their 14 days; `payments:release-due` hourly, which releases
held payments for orders delivered more than `PAYMENTS_AUTO_RELEASE_DAYS` ago; and
`currency:fetch-rates` daily at 02:00.

## Production notes

**Environment.** `APP_ENV=production`, `APP_DEBUG=false` (this turns on the branded error
pages), a real `APP_URL`, and `php artisan config:cache route:cache view:cache` on each
deploy. Run `php artisan migrate --force --seed` once. In production this creates the roles,
the marketplace plans, the fallback marketplace and a first exchange rate, with no demo data.

**Domains and TLS.** Point a wildcard DNS record (`*.your-domain`) and a wildcard TLS
certificate (for example Let's Encrypt with a DNS-01 challenge) at the app, and set
`TENANCY_CENTRAL_DOMAIN=your-domain`. A marketplace on its own domain needs that domain's DNS
pointed at the app and a certificate for it; enter the domain on the platform's marketplace
screen. Set `SESSION_DOMAIN` only if sessions must be shared across subdomains (normally
leave it empty, so each marketplace has its own).

**Queue worker.** Notifications, mail, SMS and (with `SCOUT_QUEUE=true`) search indexing are
queued. Run `php artisan queue:work --tries=3 --max-time=3600` under Supervisor or systemd,
and `php artisan queue:restart` after each deploy.

**Scheduler.** Add `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`.
Tasks use `onOneServer()`, so with several servers use a shared cache store (Redis or the
database).

**Search.** Use Meilisearch in production (see Search above). After deploying index changes,
run `php artisan scout:sync-index-settings`.

**Real-time chat (optional).** Chat polls by default. For Reverb, install
`laravel/reverb`, set `BROADCAST_CONNECTION=reverb` and the `REVERB_*` keys, run
`php artisan reverb:start` under Supervisor behind your TLS proxy, and add an Echo listener on
the chat page.

**Storage.** Run `php artisan storage:link`. Product photos and logos are on the `public`
disk; verification documents are on the private `local` disk (`storage/app/private`) and must
never be web-served. To use S3, set `FILESYSTEM_DISK` and the `AWS_*` keys.

**Mail and SMS.** Set `MAIL_MAILER` (for example `smtp`, `ses` or `postmark`) with
`MAIL_FROM_ADDRESS`, and `SMS_DRIVER` with its keys (see SMS above). Use the live MoMo and
Flutterwave keys, and register `https://your-domain/webhooks/payments/flutterwave` in the
Flutterwave dashboard.

**Backups.** Back up the database nightly (orders, payments and payouts are the record of who
is owed what) and `storage/app` (uploads and verification documents). Test a restore. Keep
`APP_KEY` safe: encrypted columns and sessions depend on it.

**Exchange rates.** Set `EXCHANGE_RATE_PROVIDER=open_er_api` for daily rates, or keep `fixed`
and let marketplace admins set their own.
#   e c o m - a p p  
 #   e c o m - a p p  
 