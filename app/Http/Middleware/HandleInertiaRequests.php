<?php

namespace App\Http\Middleware;

use App\Actions\Cart\ManageCart;
use App\Actions\Products\CategoryTree;
use App\Models\Cart;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Support\CurrencyConverter;
use App\Support\CurrentTenant;
use App\Support\DisplayCurrency;
use App\Support\MarketplaceSettings;
use App\Support\Money;
use App\Support\PageMeta;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private CurrentTenant $currentTenant,
        private DisplayCurrency $displayCurrency,
        private CurrencyConverter $converter,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $this->currentTenant->get();
        $company = $user && $tenant ? $user->currentCompany()?->load('latestVerification') : null;

        return [
            ...parent::share($request),
            'name' => $tenant->name ?? config('app.name'),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'currency' => $tenant->default_currency,
                // The platform's own marketplace has no platform plan.
                'is_system' => $tenant->isSystem(),
            ] : null,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar_url,
                    'is_platform_admin' => $user->isPlatformAdmin(),
                    // Roles and permissions on the current marketplace only.
                    'roles' => $user->getRoleNames(),
                    // Platform admins pass every check on the server (Gate::before),
                    // so give the UI the full list too.
                    'permissions' => $user->isPlatformAdmin()
                        ? Permission::pluck('name')
                        : $user->getAllPermissions()->pluck('name'),
                ] : null,
                'company' => $company?->toSummary(),
                // Set while a super admin manages a business on its behalf.
                'actingCompany' => $user && $company && $company->owner_id !== $user->id ? ['name' => $company->name, 'slug' => $company->slug] : null,
            ],
            'unreadNotificationsCount' => $user && $tenant
                ? $user->unreadNotifications()->where('data->tenant_id', $tenant->id)->count()
                : 0,
            // Chat messages from the other participant not read yet (this marketplace only).
            'unreadMessagesCount' => $user && $tenant
                ? Message::query()
                    ->whereHas('conversation.participants', fn ($query) => $query->whereKey($user->id))
                    ->where('user_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->count()
                : 0,
            'cartCount' => fn (): int => (int) ($this->cart($user, $tenant !== null)?->items()->sum('quantity') ?? 0),
            // When an untouched local order is emptied (the page refreshes then).
            'cartExpiresAt' => fn (): ?string => ($cart = $this->cart($user, $tenant !== null)) && $cart->items()->exists()
                ? $cart->expiresAt()?->toISOString()
                : null,
            // Contact details and links set by the marketplace admin (Admin > Settings).
            'marketplace' => fn (): array => [
                ...app(MarketplaceSettings::class)->all(),
                'logo_url' => app(MarketplaceSettings::class)->logoUrl(),
                'support_email' => $tenant?->support_email,
            ],
            // Top-level categories for the storefront search and category rail (cached).
            // Checked when resolved: platform screens clear the marketplace after sharing.
            'storefrontCategories' => fn (): array => $this->currentTenant->id() !== null
                ? array_map(fn (array $category) => ['name' => $category['name'], 'slug' => $category['slug'], 'count' => $category['product_count']], app(CategoryTree::class)->handle())
                : [],
            // Pages override this with their own "meta" prop.
            'meta' => fn (): array => PageMeta::make(null, ($tenant->name ?? config('app.name')).': buy in bulk from verified suppliers, compare quotes and order from local shops, with payment protection.'),
            // Prices are shown in this currency; buyers are charged in the listing's own.
            'currency' => fn (): array => [
                'display' => $this->displayCurrency->code(),
                'options' => Money::currencies(),
                'rate_label' => $this->rateLabel(),
                'rates' => $this->displayCurrency->rates(),
                'subunits' => collect(Money::currencies())->mapWithKeys(fn (string $code) => [$code => Money::subunits($code)])->all(),
            ],
            // "Payment protection" until the legal position allows "Escrow" (config/payments.php).
            'paymentProtectionLabel' => (string) config('payments.protection_label'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The signed-in member's cart on this marketplace, emptied first when
     * it has been idle too long.
     */
    private function cart(?User $user, bool $onMarketplace): ?Cart
    {
        if (! $user || ! $onMarketplace) {
            return null;
        }

        $cart = Cart::query()->where('user_id', $user->id)->first();

        return $cart ? app(ManageCart::class)->expireIfIdle($cart) : null;
    }

    /**
     * e.g. "1 USD = 1,450 RWF" for the footer, using the first non-base currency.
     */
    private function rateLabel(): ?string
    {
        $quote = collect(Money::currencies())->first(fn (string $currency) => $currency !== strtoupper((string) config('currency.base')));

        return $quote ? $this->converter->label($quote) : null;
    }
}
