<?php

namespace App\Actions\Companies;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateCompanyProfile
{
    public function __construct(private StoreCompanyLogo $storeCompanyLogo) {}

    /**
     * The company type is not fillable: it decides the owner's role and
     * which plans and features apply, so it is fixed at creation.
     *
     * @param  array<string, mixed>  $profile  Validated profile fields (see StoreCompanyRequest::profileRules)
     */
    public function handle(Company $company, array $profile, ?UploadedFile $logo = null): Company
    {
        $company->fill($profile);

        if ($logo) {
            $previousLogo = $company->logo;
            $company->logo = $this->storeCompanyLogo->handle($logo);

            if ($previousLogo) {
                Storage::disk('public')->delete($previousLogo);
            }
        }

        $nameChanged = $company->isDirty('name');
        $company->save();

        if ($nameChanged) {
            // Product search documents include the supplier name.
            $company->syncProductsToSearch();
        }

        return $company;
    }
}
