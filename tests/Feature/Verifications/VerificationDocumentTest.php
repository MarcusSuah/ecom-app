<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Facades\Storage;

function verificationWithStoredDocument(Company $company): Verification
{
    Storage::disk('local')->put('verifications/'.$company->id.'/certificate.pdf', '%PDF-1.4 sample');

    return Verification::factory()->for($company)->create([
        'documents' => [[
            'path' => 'verifications/'.$company->id.'/certificate.pdf',
            'name' => 'certificate.pdf',
            'mime' => 'application/pdf',
            'size' => 15,
        ]],
    ]);
}

it('lets the owner and reviewers download the documents', function (?Role $reviewerRole) {
    Storage::fake('local');
    $owner = User::factory()->create();
    $verification = verificationWithStoredDocument(Company::factory()->for($owner, 'owner')->create());

    $this->actingAs($reviewerRole ? userWithRole($reviewerRole) : $owner)
        ->get(route('verifications.documents.show', ['verification' => $verification, 'index' => 0]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
})->with([
    'owner' => [null],
    'verifier' => [Role::VERIFIER],
    'admin' => [Role::ADMIN],
]);

it('does not let owners view another company\'s documents', function () {
    Storage::fake('local');
    $verification = verificationWithStoredDocument(Company::factory()->create());
    $otherOwner = User::factory()->create();
    Company::factory()->for($otherOwner, 'owner')->create();

    $this->actingAs($otherOwner)
        ->get(route('verifications.documents.show', ['verification' => $verification, 'index' => 0]))
        ->assertForbidden();
});

it('returns not found for a document index that does not exist', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $verification = verificationWithStoredDocument(Company::factory()->for($owner, 'owner')->create());

    $this->actingAs($owner)
        ->get(route('verifications.documents.show', ['verification' => $verification, 'index' => 3]))
        ->assertNotFound();
});
