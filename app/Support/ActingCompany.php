<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Lets staff with companies.manage (the super admin) work in a business's
 * seller screens, adding products, photos and shop items for it, without
 * signing in as its owner. The choice lasts for the session, on this
 * marketplace only.
 */
class ActingCompany
{
    public const SESSION_KEY = 'acting_company';

    public const PERMISSION = 'companies.manage';

    public function start(Company $company): void
    {
        $this->request()->session()->put(self::SESSION_KEY, ['tenant_id' => $company->tenant_id, 'company_id' => $company->id]);
    }

    public function stop(): void
    {
        $this->request()->session()->forget(self::SESSION_KEY);
    }

    /**
     * The business the user is managing on this marketplace, if any.
     */
    public function for(User $user, int $tenantId): ?Company
    {
        $request = $this->request();

        if (! $request->hasSession()) {
            return null;
        }

        $acting = $request->session()->get(self::SESSION_KEY);

        if (! is_array($acting) || ($acting['tenant_id'] ?? null) !== $tenantId || ! $user->can(self::PERMISSION)) {
            return null;
        }

        return Company::query()->whereKey((int) ($acting['company_id'] ?? 0))->first();
    }

    /**
     * Resolved on each call: this object may outlive one request (tests, Octane).
     */
    private function request(): Request
    {
        return app(Request::class);
    }
}
