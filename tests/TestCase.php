<?php

namespace Tests;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The marketplace every feature test runs in. It is the fallback tenant,
     * so requests to the test host (localhost) resolve to it, and it has a
     * current platform plan, so it is open.
     */
    public Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->setUpMarketplace();
        }
    }

    protected function setUpMarketplace(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create(['slug' => config('tenancy.fallback_tenant')]);
        $plan = Plan::factory()->create();
        Subscription::factory()->forSubscriber($this->tenant, $plan)->create();

        $this->tenant->makeCurrent();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
