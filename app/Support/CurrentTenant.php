<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Context;
use RuntimeException;

/**
 * Holds the marketplace (tenant) the current request or job runs for.
 * Registered as a scoped singleton, so it resets between requests and jobs.
 *
 * Making a tenant current also points Spatie Permission at it, because
 * roles are assigned per tenant ("teams").
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * The current tenant, for code that cannot run without one.
     */
    public function require(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('No marketplace (tenant) is active for this operation.');
    }

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        setPermissionsTeamId($tenant?->id);

        if ($tenant) {
            Context::addHidden('tenant_id', $tenant->id);
        } else {
            Context::forgetHidden('tenant_id');
        }
    }

    /**
     * Run a callback with another tenant active, then restore the previous one.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(?Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }
}
