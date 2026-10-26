<?php

namespace Database\Seeders;

use App\Actions\Currency\FetchExchangeRates;
use App\Actions\Tenants\ProvisionMarketplaceDefaults;
use App\Enums\BillingInterval;
use App\Enums\PlanAudience;
use App\Enums\PlanLimit;
use App\Enums\Role as RoleEnum;
use App\Models\CurrencyRate;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * What production needs to start: the marketplace plans the platform sells,
 * the fallback marketplace (TENANCY_FALLBACK_TENANT) with its defaults, and
 * a first exchange rate.
 */
class PlatformSeeder extends Seeder
{
    public function run(ProvisionMarketplaceDefaults $provisionDefaults, FetchExchangeRates $fetchExchangeRates): void
    {
        $plans = [
            ['name' => 'Launch', 'price' => 4900, 'trial_days' => 30, 'description' => 'Open a marketplace and onboard your first sellers.', 'limits' => [PlanLimit::MAX_COMPANIES->value => 50, PlanLimit::CUSTOM_DOMAIN->value => false]],
            ['name' => 'Scale', 'price' => 19900, 'trial_days' => 0, 'description' => 'For growing marketplaces with their own domain.', 'limits' => [PlanLimit::MAX_COMPANIES->value => 500, PlanLimit::CUSTOM_DOMAIN->value => true]],
            ['name' => 'Enterprise', 'price' => 49900, 'trial_days' => 0, 'description' => 'Unlimited sellers.', 'limits' => [PlanLimit::MAX_COMPANIES->value => null, PlanLimit::CUSTOM_DOMAIN->value => true]],
        ];

        foreach ($plans as $position => $plan) {
            Plan::query()->forMarketplaces()->where('name', $plan['name'])->firstOr(fn () => Plan::create([
                ...$plan,
                'tenant_id' => null,
                'audience' => PlanAudience::MARKETPLACE,
                'currency' => 'USD',
                'interval' => BillingInterval::MONTH,
                'sort_order' => ($position + 1) * 10,
            ]));
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => config('tenancy.fallback_tenant')],
            ['name' => 'Mix Ventures Marketplace', 'default_currency' => 'RWF', 'support_email' => 'support@mixventures.rw'],
        );

        $provisionDefaults->handle($tenant);

        $this->superAdmin($tenant);

        // A first rate so approximate prices show straight away; the daily job keeps it fresh.
        if (CurrencyRate::query()->shared()->doesntExist()) {
            try {
                $fetchExchangeRates->handle();
            } catch (RuntimeException $exception) {
                Log::warning($exception->getMessage().' Run php artisan currency:fetch-rates later.');
            }
        }
    }

    /**
     * The default superadmin: a platform admin with the super_admin role
     * (every permission) on the fallback marketplace.
     */
    private function superAdmin(Tenant $tenant): User
    {
        $email = (string) config('marketplace.superadmin.email');
        $password = config('marketplace.superadmin.password');

        if (! is_string($password) || $password === '') {
            $password = app()->isProduction() ? Str::password(20) : 'password';

            if (app()->isProduction() && User::query()->where('email', $email)->doesntExist()) {
                Log::warning("Superadmin {$email} was created with the generated password {$password}. Change it after signing in.");
            }
        }

        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => (string) config('marketplace.superadmin.name'),
            'password' => $password,
        ]);
        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        app(CurrentTenant::class)->runAs($tenant, fn () => $user->assignRole(RoleEnum::SUPER_ADMIN->value));

        return $user;
    }
}
