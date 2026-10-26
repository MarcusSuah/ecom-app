<?php

namespace App\Actions\Companies;

use App\Enums\CompanyType;
use App\Enums\PlanLimit;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCompany
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private StoreCompanyLogo $storeCompanyLogo,
    ) {}

    /**
     * Create the user's company on the current marketplace and give them the
     * matching role (supplier or shop owner). One company per user per
     * marketplace, and the marketplace's own plan caps how many it can hold.
     *
     * @param  array<string, mixed>  $profile  Validated profile fields (see StoreCompanyRequest::profileRules)
     */
    public function handle(User $owner, CompanyType $type, array $profile, ?UploadedFile $logo = null): Company
    {
        $tenant = $this->currentTenant->require();

        if ($owner->companies()->exists()) {
            throw ValidationException::withMessages(['name' => 'You already have a company on this marketplace.']);
        }

        if (! $tenant->withinPlanLimit(PlanLimit::MAX_COMPANIES, $tenant->companies()->count())) {
            throw ValidationException::withMessages(['name' => 'This marketplace is not accepting new sellers right now. Please try again later.']);
        }

        try {
            return DB::transaction(function () use ($owner, $type, $profile, $logo): Company {
                $company = new Company($profile);
                $company->type = $type;
                $company->owner()->associate($owner);
                $company->logo = $logo ? $this->storeCompanyLogo->handle($logo) : null;
                $company->save();

                $owner->assignRole($type->ownerRole()->value);

                return $company;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'You already have a company on this marketplace.']);
        }
    }
}
