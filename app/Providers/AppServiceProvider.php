<?php

namespace App\Providers;

use App\Http\Middleware\IdentifyTenant;
use App\Models\Company;
use App\Models\Product;
use App\Models\ShopProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\FakeSubscriptionGateway;
use App\Services\Billing\SubscriptionGateway;
use App\Services\Currency\ExchangeRateProvider;
use App\Services\Currency\FixedExchangeRateProvider;
use App\Services\Currency\OpenExchangeRateApiProvider;
use App\Services\Sms\AfricasTalkingSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\Sms\TwilioSmsSender;
use App\Support\CurrencyConverter;
use App\Support\CurrentTenant;
use App\Support\DisplayCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
        $this->app->scoped(CurrencyConverter::class);
        $this->app->scoped(DisplayCurrency::class);

        $this->app->bind(ExchangeRateProvider::class, fn (): ExchangeRateProvider => match (config('currency.provider')) {
            'fixed' => new FixedExchangeRateProvider((array) config('currency.providers.fixed.rates')),
            'open_er_api' => new OpenExchangeRateApiProvider((string) config('currency.providers.open_er_api.base_url')),
            default => throw new InvalidArgumentException('Unsupported exchange rate provider ['.config('currency.provider').'].'),
        });

        $this->app->bind(SubscriptionGateway::class, fn (): SubscriptionGateway => match (config('billing.gateway')) {
            'fake' => new FakeSubscriptionGateway((string) config('billing.gateways.fake.webhook_secret')),
            default => throw new InvalidArgumentException('Unsupported billing gateway ['.config('billing.gateway').'].'),
        });

        $this->app->bind(SmsSender::class, fn (): SmsSender => match (config('services.sms.driver')) {
            'log' => new LogSmsSender,
            'africastalking' => new AfricasTalkingSmsSender(
                (string) config('services.sms.africastalking.username'),
                (string) config('services.sms.africastalking.api_key'),
                config('services.sms.africastalking.sender_id'),
                (bool) config('services.sms.africastalking.sandbox'),
            ),
            'twilio' => new TwilioSmsSender(
                (string) config('services.sms.twilio.account_sid'),
                (string) config('services.sms.twilio.auth_token'),
                (string) config('services.sms.twilio.from'),
            ),
            default => throw new InvalidArgumentException('Unsupported SMS driver ['.config('services.sms.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureErrorPages();

        Relation::enforceMorphMap([
            'user' => User::class,
            'tenant' => Tenant::class,
            'company' => Company::class,
            'product' => Product::class,
            'shop_product' => ShopProduct::class,
        ]);
    }

    /**
     * Platform admins run every marketplace, so every ability is granted to them.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isPlatformAdmin() ? true : null);
    }

    /**
     * Public forms that send messages to people.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('contact', fn (Request $request): array => [
            Limit::perMinute(3)->by('contact:'.$request->ip()),
            Limit::perDay(20)->by('contact-day:'.$request->ip()),
        ]);
    }

    /**
     * Branded error pages instead of the framework's, whenever debug output is
     * off. Shared data is left out on purpose: it queries the database,
     * which may be what failed. URLs that match no route never pass
     * IdentifyTenant, so the marketplace is looked up here for its name.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $request = $response->request;
            $wantsJson = $request->is('api/*') || ($request->expectsJson() && ! $request->hasHeader('X-Inertia'));

            if (config('app.debug') || $wantsJson || ! in_array($response->statusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            $tenant = app(CurrentTenant::class)->get()
                ?? rescue(fn () => app(IdentifyTenant::class)->forHost($request->getHost()), report: false);

            return $response->render('error', [
                'status' => $response->statusCode(),
                'marketplace' => $tenant->name ?? config('app.name'),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
