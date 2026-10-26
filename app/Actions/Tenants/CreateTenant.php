<?php

namespace App\Actions\Tenants;

use App\Actions\Billing\StartSubscription;
use App\Enums\Role;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateTenant
{
    public function __construct(
        private ProvisionMarketplaceDefaults $provisionDefaults,
        private StartSubscription $startSubscription,
        private CurrentTenant $currentTenant,
    ) {}

    /**
     * Open a new marketplace: defaults, its first admin and its platform plan.
     * A new admin account gets a password-reset link instead of a password.
     *
     * @param  array{name: string, slug: string, domain: string|null, default_currency: string, support_email: string|null, admin_name: string, admin_email: string, plan_id: int}  $attributes
     */
    public function handle(array $attributes): Tenant
    {
        [$tenant, $admin, $isNewAdmin] = DB::transaction(function () use ($attributes): array {
            $tenant = Tenant::create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'domain' => $attributes['domain'],
                'default_currency' => $attributes['default_currency'],
                'support_email' => $attributes['support_email'],
            ]);

            $this->provisionDefaults->handle($tenant);

            $admin = User::query()->where('email', $attributes['admin_email'])->first();
            $isNewAdmin = $admin === null;
            $admin ??= User::create([
                'name' => $attributes['admin_name'],
                'email' => $attributes['admin_email'],
                'password' => Str::password(32),
            ]);

            $this->currentTenant->runAs($tenant, function () use ($admin, $tenant, $attributes): void {
                $admin->assignRole(Role::ADMIN->value);

                $this->startSubscription->handle(
                    $tenant,
                    Plan::query()->forMarketplaces()->whereKey($attributes['plan_id'])->firstOrFail(),
                    route('admin.billing.show'),
                );
            });

            return [$tenant, $admin, $isNewAdmin];
        });

        if ($isNewAdmin) {
            Password::sendResetLink(['email' => $admin->email]);
        }

        return $tenant;
    }
}
