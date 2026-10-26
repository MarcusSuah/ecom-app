<?php

namespace App\Actions\Companies;

use App\Enums\PlanLimit;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

class SetCompanyFeatured
{
    /**
     * Only admins call this. A company can only be featured while its seller
     * plan includes featured listings; un-featuring is always allowed.
     */
    public function handle(Company $company, bool $featured): Company
    {
        if ($featured && ! $company->planLimit(PlanLimit::FEATURED_LISTING)) {
            throw ValidationException::withMessages(['is_featured' => "{$company->name}'s plan does not include featured listings."]);
        }

        $company->is_featured = $featured;
        $company->save();

        return $company;
    }
}
