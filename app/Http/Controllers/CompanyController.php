<?php

namespace App\Http\Controllers;

use App\Actions\Companies\CreateCompany;
use App\Actions\Companies\UpdateCompanyProfile;
use App\Enums\CompanyType;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Requests\Verifications\SubmitVerificationRequest;
use App\Models\Company;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own company on the current marketplace.
 */
class CompanyController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->currentCompany()) {
            return to_route('company.edit');
        }

        return Inertia::render('company/create', [
            'types' => collect(CompanyType::cases())->map(fn (CompanyType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $createCompany->handle(
            $request->user(),
            $request->companyType(),
            $request->safe()->except(['type', 'logo']),
            $request->file('logo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your company has been created. Apply for verification to get the Verified badge.']);

        return to_route('company.edit');
    }

    public function edit(Request $request): Response|RedirectResponse
    {
        $company = $this->ownCompany($request);

        if (! $company) {
            return to_route('company.create');
        }

        Gate::authorize('update', $company);

        $company->load('latestVerification');

        return Inertia::render('company/edit', [
            'company' => [
                ...$company->toSummary(),
                'description' => $company->description,
                'phone' => $company->phone,
                'email' => $company->email,
            ],
            'canSubmitVerification' => $company->canSubmitVerification(),
            'maxDocuments' => SubmitVerificationRequest::MAX_DOCUMENTS,
            'verifications' => $company->verifications()
                ->with('reviewer:id,name')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Verification $verification) => $verification->toSummary()),
        ]);
    }

    public function update(UpdateCompanyRequest $request, UpdateCompanyProfile $updateCompanyProfile): RedirectResponse
    {
        $updateCompanyProfile->handle($request->user()->currentCompany(), $request->safe()->except('logo'), $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company profile saved.']);

        return to_route('company.edit');
    }

    private function ownCompany(Request $request): ?Company
    {
        return $request->user()->currentCompany();
    }
}
