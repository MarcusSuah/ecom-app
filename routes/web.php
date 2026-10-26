<?php

use App\Http\Controllers\AccountApprovalController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CurrencyRateController as AdminCurrencyRateController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SellerPlanController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Catalog\CategoryController as CatalogCategoryController;
use App\Http\Controllers\Catalog\ContactController;
use App\Http\Controllers\Catalog\ContentPageController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Catalog\ProductController as CatalogProductController;
use App\Http\Controllers\Catalog\SearchController as CatalogSearchController;
use App\Http\Controllers\Catalog\ShopController as CatalogShopController;
use App\Http\Controllers\Catalog\SitemapController;
use App\Http\Controllers\Catalog\SupplierController as CatalogSupplierController;
use App\Http\Controllers\CompanyBillingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Platform\AnalyticsController as PlatformAnalyticsController;
use App\Http\Controllers\Platform\MarketplacePlanController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\ReportController as SellerReportController;
use App\Http\Controllers\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Shop\SettingsController as ShopSettingsController;
use App\Http\Controllers\Supplier\ProductController as SupplierProductController;
use App\Http\Controllers\Supplier\RfqController as SupplierRfqController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WholesaleOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketplace
|--------------------------------------------------------------------------
| Every web request runs for one marketplace (tenant), found from the host
| by IdentifyTenant. "marketplace.open" closes a marketplace to visitors
| while it is suspended or has no current platform plan.
*/

Route::middleware(['marketplace.open', 'approved'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
    Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

    // Footer pages: information, and the contact form (stored, admins notified).
    Route::get('contact', [ContactController::class, 'create'])->name('contact.create');
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
    Route::get('{page}', ContentPageController::class)->whereIn('page', array_keys(ContentPageController::PAGES))->name('pages.show');

    // The header's currency switcher (display only; charges stay in the order's currency).
    Route::put('currency', [CurrencyController::class, 'update'])->middleware('throttle:30,1')->name('currency.update');

    // Public wholesale catalogue.
    Route::get('search', [CatalogSearchController::class, 'index'])->name('search');
    Route::get('search/suggest', [CatalogSearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
    Route::get('products', [CatalogProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [CatalogProductController::class, 'show'])->name('products.show');
    Route::get('products/{product}/quick-view', [CatalogProductController::class, 'quickView'])->middleware('throttle:120,1')->name('products.quick-view');
    Route::get('categories', [CatalogCategoryController::class, 'index'])->name('categories.index');
    Route::get('suppliers', [CatalogSupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/{company}', [CatalogSupplierController::class, 'show'])->name('suppliers.show');
    // Local shops, with distance and delivery fees from the customer's location.
    Route::get('shops', [CatalogShopController::class, 'index'])->name('shops.index');
    Route::get('shops/{company}', [CatalogShopController::class, 'show'])->name('shops.show');
    Route::put('location', [LocationController::class, 'update'])->name('location.update');

    // Anyone can send a request; guests follow it from a private link and sign in only to accept a quote.
    Route::get('rfqs/create', [RfqController::class, 'create'])->name('rfqs.create');
    Route::post('rfqs', [RfqController::class, 'store'])->middleware('throttle:10,1')->name('rfqs.store');
    Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1')->name('inquiries.store');
    Route::get('rfqs/track/{token}', [RfqController::class, 'track'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{48}')->name('rfqs.track');

    Route::get('account/pending', [AccountApprovalController::class, 'show'])->middleware(['auth', 'verified'])->withoutMiddleware('approved')->name('account.pending');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');

        // The signed-in user's own company on this marketplace.
        Route::get('company/create', [CompanyController::class, 'create'])->name('company.create');
        Route::post('company', [CompanyController::class, 'store'])->name('company.store');
        Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('company', [CompanyController::class, 'update'])->name('company.update');
        Route::post('company/verification', [VerificationController::class, 'store'])->name('company.verification.store');

        Route::get('company/billing', [CompanyBillingController::class, 'show'])->name('company.billing.show');
        Route::post('company/billing/subscribe', [CompanyBillingController::class, 'subscribe'])->name('company.billing.subscribe');
        Route::post('company/billing/cancel', [CompanyBillingController::class, 'cancel'])->name('company.billing.cancel');
        Route::post('company/billing/invoices/{invoice}/pay', [CompanyBillingController::class, 'payInvoice'])->name('company.billing.invoices.pay');

        // Saved delivery addresses (AddressPolicy).
        Route::get('addresses', [LocationController::class, 'index'])->name('addresses.index');
        Route::post('addresses', [LocationController::class, 'store'])->name('addresses.store');
        Route::post('addresses/{address}/use', [LocationController::class, 'useAddress'])->name('addresses.use');
        Route::post('addresses/{address}/default', [LocationController::class, 'makeDefault'])->name('addresses.default');
        Route::delete('addresses/{address}', [LocationController::class, 'destroy'])->name('addresses.destroy');

        // Cart (one per marketplace, items from several shops) and checkout.
        Route::delete('acting-company', [AdminCompanyController::class, 'stopManaging'])->name('acting-company.destroy');
        Route::get('cart', [CartController::class, 'show'])->name('cart.show');
        Route::get('cart/summary', [CartController::class, 'summary'])->name('cart.summary');
        Route::post('cart/items/{shopProduct}', [CartController::class, 'add'])->name('cart.items.store');
        Route::patch('cart/items/{shopProduct}', [CartController::class, 'update'])->name('cart.items.update');
        Route::put('cart/shops/{shop}/fulfilment', [CartController::class, 'fulfilment'])->name('cart.fulfilment');
        Route::post('checkout', [CartController::class, 'checkout'])->middleware('throttle:10,1')->name('checkout');

        // A buyer's orders (OrderPolicy).
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        // Invoice before payment, receipt after (OrderPolicy::document).
        Route::get('orders/{order}/document.pdf', [DocumentController::class, 'order'])->middleware('throttle:30,1')->name('orders.document');
        Route::get('quotes/{quote}/document.pdf', [DocumentController::class, 'quote'])->middleware('throttle:30,1')->name('quotes.document');
        Route::get('billing/invoices/{invoice}.pdf', [DocumentController::class, 'subscriptionInvoice'])->middleware('throttle:30,1')->name('billing.invoices.pdf');
        Route::post('orders/{order}/pay', [OrderController::class, 'pay'])->middleware('throttle:10,1')->name('orders.pay');
        Route::post('orders/{order}/cash', [OrderController::class, 'payCash'])->name('orders.pay-cash');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/confirm-receipt', [OrderController::class, 'confirmReceipt'])->name('orders.confirm-receipt');
        Route::post('orders/{order}/dispute', [OrderController::class, 'dispute'])->name('orders.dispute');
        Route::get('disputes/{dispute}/photos/{index}', [OrderController::class, 'disputePhoto'])->whereNumber('index')->name('disputes.photos.show');

        // Wholesale orders from an accepted quote or a product page.
        Route::post('rfqs/{rfq}/quotes/{quote}/order', [WholesaleOrderController::class, 'fromQuote'])->name('rfqs.quotes.order');
        Route::post('products/{product}/order', [WholesaleOrderController::class, 'fromProduct'])->name('products.order');

        // Orders a supplier or shop received, and its payouts.
        Route::prefix('seller')->name('seller.')->group(function () {
            Route::get('orders', [SellerOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [SellerOrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}/status', [SellerOrderController::class, 'updateStatus'])->name('orders.status');
            Route::post('orders/{order}/refund', [SellerOrderController::class, 'refund'])->name('orders.refund');
            Route::post('orders/{order}/cash-collected', [SellerOrderController::class, 'collectCash'])->name('orders.cash-collected');
            Route::post('orders/{order}/cancel', [SellerOrderController::class, 'cancelCash'])->name('orders.cancel');
            Route::get('payouts', [SellerOrderController::class, 'payouts'])->name('payouts.index');
            Route::get('payouts/statement.pdf', [DocumentController::class, 'payoutStatement'])->middleware('throttle:30,1')->name('payouts.statement');
            Route::get('reports', [SellerReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{type}.csv', [SellerReportController::class, 'export'])->whereIn('type', SellerReportController::EXPORTS)->middleware('throttle:30,1')->name('reports.export');
        });

        // A local shop's settings and items.
        Route::prefix('shop')->name('shop.')->group(function () {
            Route::get('settings', [ShopSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [ShopSettingsController::class, 'update'])->name('settings.update');

            Route::get('products', [ShopProductController::class, 'index'])->name('products.index');
            Route::get('products/create', [ShopProductController::class, 'create'])->name('products.create');
            Route::post('products', [ShopProductController::class, 'store'])->name('products.store');
            Route::get('products/{shopProduct}/edit', [ShopProductController::class, 'edit'])->name('products.edit');
            Route::post('products/{shopProduct}', [ShopProductController::class, 'update'])->name('products.update');
            Route::delete('products/{shopProduct}', [ShopProductController::class, 'destroy'])->name('products.destroy');

        });

        // A buyer's requests and quote comparison (RfqPolicy).
        Route::get('rfqs', [RfqController::class, 'index'])->name('rfqs.index');
        Route::get('rfqs/{rfq}', [RfqController::class, 'show'])->whereNumber('rfq')->name('rfqs.show');
        Route::post('rfqs/{rfq}/close', [RfqController::class, 'close'])->name('rfqs.close');
        Route::post('rfqs/{rfq}/quotes/{quote}/accept', [RfqController::class, 'accept'])->name('rfqs.quotes.accept');

        // Requests a supplier received (RfqPolicy::respond).
        Route::prefix('supplier/rfqs')->name('supplier.rfqs.')->group(function () {
            Route::get('/', [SupplierRfqController::class, 'index'])->name('index');
            Route::get('{rfq}', [SupplierRfqController::class, 'show'])->name('show');
            Route::post('{rfq}/decline', [SupplierRfqController::class, 'decline'])->name('decline');
            Route::put('{rfq}/quote', [SupplierRfqController::class, 'saveQuote'])->name('quote');
            Route::post('{rfq}/quote/withdraw', [SupplierRfqController::class, 'withdraw'])->name('quote.withdraw');
        });

        // Chat between a member and a company (ConversationPolicy).
        Route::get('messages', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('messages', [ConversationController::class, 'store'])->name('conversations.store');
        Route::post('messages/open', [ConversationController::class, 'open'])->middleware('throttle:30,1')->name('conversations.open');
        Route::get('messages/{conversation}/feed', [ConversationController::class, 'feed'])->whereNumber('conversation')->middleware('throttle:120,1')->name('conversations.feed');
        Route::get('messages/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('conversations.show');
        Route::post('messages/{conversation}', [ConversationController::class, 'send'])->whereNumber('conversation')
            ->middleware('throttle:30,1')->name('conversations.messages.store');
        Route::get('messages/attachments/{message}', [ConversationController::class, 'attachment'])->name('messages.attachment');

        // A supplier's own products (ProductPolicy).
        Route::prefix('supplier/products')->name('supplier.products.')->group(function () {
            Route::get('/', [SupplierProductController::class, 'index'])->name('index');
            Route::get('create', [SupplierProductController::class, 'create'])->name('create');
            Route::post('/', [SupplierProductController::class, 'store'])->name('store');
            Route::get('{product}/edit', [SupplierProductController::class, 'edit'])->name('edit');
            Route::put('{product}', [SupplierProductController::class, 'update'])->name('update');
            Route::delete('{product}', [SupplierProductController::class, 'destroy'])->name('destroy');
            Route::patch('{product}/status', [SupplierProductController::class, 'updateStatus'])->name('status');
            Route::post('{product}/images', [SupplierProductController::class, 'storeImages'])->name('images.store');
            Route::delete('{product}/images/{image}', [SupplierProductController::class, 'destroyImage'])->name('images.destroy');
        });

        // Owners and reviewers only (VerificationPolicy::view).
        Route::get('verifications/{verification}/documents/{index}', [VerificationController::class, 'document'])
            ->whereNumber('index')
            ->name('verifications.documents.show');

        /*
        |----------------------------------------------------------------------
        | Marketplace administration
        |----------------------------------------------------------------------
        | Permission names here must match RolePermissionSeeder exactly.
        */
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/', AdminDashboardController::class)
                ->middleware('permission:admin.dashboard')->name('dashboard');

            // Payment protection: disputes and payouts to sellers.
            Route::middleware('permission:disputes.resolve')->group(function () {
                Route::get('disputes', [AdminDisputeController::class, 'index'])->name('disputes.index');
                Route::get('disputes/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
                Route::post('disputes/{dispute}/resolve', [AdminDisputeController::class, 'resolve'])->name('disputes.resolve');
            });
            Route::middleware('permission:payouts.manage')->group(function () {
                Route::get('payouts', [AdminPayoutController::class, 'index'])->name('payouts.index');
                Route::post('payouts/{payout}/paid', [AdminPayoutController::class, 'markPaid'])->name('payouts.paid');
            });

            // Exchange rate used to show approximate prices.
            Route::middleware('permission:currency.manage')->group(function () {
                Route::get('currency', [AdminCurrencyRateController::class, 'index'])->name('currency.index');
                Route::put('currency', [AdminCurrencyRateController::class, 'update'])->name('currency.update');
                Route::delete('currency/{quote}', [AdminCurrencyRateController::class, 'destroy'])->whereAlpha('quote')->name('currency.destroy');
            });

            // Marketplace settings (name, contact details, tagline, social links).
            Route::middleware('permission:settings.manage')->group(function () {
                Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
                Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
                Route::post('settings/logo', [AdminSettingsController::class, 'updateLogo'])->name('settings.logo.update');
                Route::delete('settings/logo', [AdminSettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
            });

            // Announcements to members.
            Route::middleware('permission:notifications.send')->group(function () {
                Route::get('notifications', [AdminAnnouncementController::class, 'index'])->name('notifications.index');
                Route::post('notifications', [AdminAnnouncementController::class, 'store'])->middleware('throttle:10,1')->name('notifications.store');
            });

            // Sales, commission and payouts for a period, with CSV downloads.
            Route::middleware('permission:reports.view')->group(function () {
                Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
                Route::get('reports/{type}.csv', [AdminReportController::class, 'export'])->whereIn('type', AdminReportController::EXPORTS)->middleware('throttle:30,1')->name('reports.export');
            });

            // Monitoring: changes people made, and what members do.
            Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->middleware('permission:audit-logs.view')->name('audit-logs.index');
            Route::get('activity', [AdminActivityController::class, 'index'])->middleware('permission:activity.view')->name('activity.index');
            Route::middleware('permission:activity.manage')->group(function () {
                Route::post('activity/users/{user}/block', [AdminActivityController::class, 'blockUser'])->name('activity.users.block');
                Route::delete('activity/users/{user}/block', [AdminActivityController::class, 'unblockUser'])->name('activity.users.unblock');
                Route::post('activity/blocked-ips', [AdminActivityController::class, 'blockIp'])->name('activity.ips.block');
                Route::delete('activity/blocked-ips/{blockedIp}', [AdminActivityController::class, 'unblockIp'])->name('activity.ips.unblock');
            });

            // Contact form inbox.
            Route::middleware('permission:contact.manage')->group(function () {
                Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
                Route::post('contact-messages/{contactMessage}/handled', [AdminContactMessageController::class, 'markHandled'])->name('contact-messages.handled');
            });

            // Verification queue (VerificationPolicy).
            Route::get('verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
            Route::get('verifications/{verification}', [AdminVerificationController::class, 'show'])->name('verifications.show');
            Route::post('verifications/{verification}/review', [AdminVerificationController::class, 'review'])->name('verifications.review');

            // Categories (CategoryPolicy).
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Companies (CompanyPolicy).
            Route::get('companies', [AdminCompanyController::class, 'index'])->name('companies.index');
            Route::put('companies/{company}/featured', [AdminCompanyController::class, 'feature'])->name('companies.feature');
            Route::post('companies/{company}/plan', [AdminCompanyController::class, 'activatePlan'])->name('companies.plan');
            Route::post('companies/{company}/suspension', [AdminCompanyController::class, 'suspend'])->name('companies.suspend');
            Route::delete('companies/{company}/suspension', [AdminCompanyController::class, 'reinstate'])->name('companies.reinstate');
            Route::post('companies/{company}/manage', [AdminCompanyController::class, 'manage'])->name('companies.manage');

            // Seller plans (PlanPolicy).
            Route::get('plans', [SellerPlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [SellerPlanController::class, 'store'])->name('plans.store');
            Route::put('plans/{plan}', [SellerPlanController::class, 'update'])->name('plans.update');
            Route::delete('plans/{plan}', [SellerPlanController::class, 'destroy'])->name('plans.destroy');

            // This marketplace's own plan with the platform.
            Route::middleware('permission:billing.manage')->group(function () {
                Route::get('billing', [AdminBillingController::class, 'show'])->name('billing.show');
                Route::post('billing/subscribe', [AdminBillingController::class, 'subscribe'])->name('billing.subscribe');
                Route::post('billing/cancel', [AdminBillingController::class, 'cancel'])->name('billing.cancel');
                Route::post('billing/invoices/{invoice}/pay', [AdminBillingController::class, 'payInvoice'])->name('billing.invoices.pay');
            });

            // Users
            Route::middleware('permission:users.view')->group(function () {
                Route::get('/users', [UserController::class, 'index'])->name('users.index');
                Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            });
            Route::middleware('permission:users.update')->group(function () {
                Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
                Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
                Route::post('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
                Route::post('/users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');
                Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
            });
            Route::delete('/users/{user}', [UserController::class, 'destroy'])
                ->middleware('permission:users.delete')
                ->name('users.destroy');

            // Roles
            Route::get('/roles', [RoleController::class, 'index'])
                ->middleware('permission:roles.view')->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])
                ->middleware('permission:roles.create')->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])
                ->middleware('permission:roles.update')->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
                ->middleware('permission:roles.delete')->name('roles.destroy');

            // Permissions
            Route::get('/permissions', [PermissionController::class, 'index'])
                ->middleware('permission:permissions.view')->name('permissions.index');
            Route::post('/permissions', [PermissionController::class, 'store'])
                ->middleware('permission:permissions.create')->name('permissions.store');
            Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
                ->middleware('permission:permissions.update')->name('permissions.update');
            Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
                ->middleware('permission:permissions.delete')->name('permissions.destroy');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Platform
|--------------------------------------------------------------------------
| Run by platform admins across every marketplace. Reachable from any
| marketplace host; EnsurePlatformAdmin sets the host's tenant aside.
*/

Route::middleware(['auth', 'verified', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', fn () => to_route('platform.tenants.index'))->name('home');

    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('analytics', [PlatformAnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/marketplaces.csv', [PlatformAnalyticsController::class, 'export'])->middleware('throttle:30,1')->name('analytics.export');
    Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::post('tenants/{tenant}/plan', [TenantController::class, 'activatePlan'])->name('tenants.plan');

    Route::get('plans', [MarketplacePlanController::class, 'index'])->name('plans.index');
    Route::post('plans', [MarketplacePlanController::class, 'store'])->name('plans.store');
    Route::put('plans/{plan}', [MarketplacePlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [MarketplacePlanController::class, 'destroy'])->name('plans.destroy');
});

// Signature-checked; exempt from CSRF in bootstrap/app.php.
Route::post('webhooks/billing', BillingWebhookController::class)->name('webhooks.billing');
// MoMo calls back with PUT on a signed URL; Flutterwave and fake POST.
Route::match(['post', 'put'], 'webhooks/payments/{provider}', PaymentWebhookController::class)->name('webhooks.payments');

require __DIR__.'/settings.php';
