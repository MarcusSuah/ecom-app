<?php

use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\VerificationReviewed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('allows only verifiers and admins to review applications', function (Role $role, bool $allowed) {
    $verification = Verification::factory()->create();

    expect(Gate::forUser(userWithRole($role))->allows('review', $verification))->toBe($allowed);
})->with([
    'buyer' => [Role::BUYER, false],
    'supplier' => [Role::SUPPLIER, false],
    'shop owner' => [Role::SHOP_OWNER, false],
    'verifier' => [Role::VERIFIER, true],
    'admin' => [Role::ADMIN, true],
]);

it('refuses a review from a user without the permission', function () {
    $verification = Verification::factory()->create();

    $this->actingAs(userWithRole(Role::SUPPLIER))
        ->post(route('admin.verifications.review', $verification), ['decision' => 'approved'])
        ->assertForbidden();

    expect($verification->fresh()->status)->toBe(VerificationStatus::PENDING);
});

it('verifies the company and notifies the owner when approved', function () {
    Notification::fake();
    $verifier = userWithRole(Role::VERIFIER);
    $verification = Verification::factory()->create();

    $this->actingAs($verifier)
        ->post(route('admin.verifications.review', $verification), ['decision' => 'approved'])
        ->assertRedirect(route('admin.verifications.index'));

    $verification->refresh();
    expect($verification->status)->toBe(VerificationStatus::APPROVED)
        ->and($verification->reviewer_id)->toBe($verifier->id)
        ->and($verification->reviewed_at)->not->toBeNull()
        ->and($verification->company->fresh()->load('latestVerification')->isVerified())->toBeTrue();

    Notification::assertSentTo($verification->company->owner, VerificationReviewed::class);
});

it('requires a reason to reject and sends it to the owner', function () {
    Notification::fake();
    $verifier = userWithRole(Role::VERIFIER);
    $verification = Verification::factory()->create();

    $this->actingAs($verifier)
        ->post(route('admin.verifications.review', $verification), ['decision' => 'rejected'])
        ->assertSessionHasErrors(['rejection_reason' => 'Give the reason for rejecting this application.']);

    $this->actingAs($verifier)
        ->post(route('admin.verifications.review', $verification), ['decision' => 'rejected', 'rejection_reason' => 'Certificate is expired.'])
        ->assertRedirect();

    expect($verification->fresh())
        ->status->toBe(VerificationStatus::REJECTED)
        ->rejection_reason->toBe('Certificate is expired.');

    Notification::assertSentTo(
        $verification->company->owner,
        VerificationReviewed::class,
        fn (VerificationReviewed $notification, array $channels, User $owner) => $notification->toArray($owner)['tenant_id'] === $this->tenant->id
            && str_contains($notification->toArray($owner)['message'], 'Certificate is expired.'),
    );
});

it('does not review an application twice', function () {
    $verification = Verification::factory()->approved()->create();

    $this->actingAs(userWithRole(Role::VERIFIER))
        ->post(route('admin.verifications.review', $verification), ['decision' => 'rejected', 'rejection_reason' => 'Changed my mind'])
        ->assertSessionHasErrors(['decision' => 'This application has already been reviewed.']);

    expect($verification->fresh()->status)->toBe(VerificationStatus::APPROVED);
});

it('only counts a company as verified while its latest application is approved', function (array $history, bool $verified) {
    $company = Company::factory()->create();
    foreach ($history as $status) {
        Verification::factory()->for($company)->status($status)->create();
    }

    $company->load('latestVerification');
    expect($company->isVerified())->toBe($verified)
        ->and(Company::query()->verified()->whereKey($company->id)->exists())->toBe($verified);
})->with([
    'never applied' => [[], false],
    'pending' => [[VerificationStatus::PENDING], false],
    'rejected' => [[VerificationStatus::REJECTED], false],
    'approved' => [[VerificationStatus::APPROVED], true],
    'rejected then approved' => [[VerificationStatus::REJECTED, VerificationStatus::APPROVED], true],
    'approved then a newer rejection' => [[VerificationStatus::APPROVED, VerificationStatus::REJECTED], false],
]);

it('stores a new application with its documents on the private disk', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('company.verification.store'), [
            'registration_number' => 'RDB-123456',
            'documents' => [UploadedFile::fake()->create('certificate.pdf', 300, 'application/pdf')],
        ])
        ->assertRedirect(route('company.edit'));

    $verification = $company->verifications()->sole();
    expect($verification->status)->toBe(VerificationStatus::PENDING)
        ->and($verification->documents)->toHaveCount(1);
    Storage::disk('local')->assertExists($verification->documents[0]['path']);
});

it('allows one pending application at a time and a new one after a rejection', function (VerificationStatus $latest, bool $canApply) {
    Storage::fake('local');
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->create();
    Verification::factory()->for($company)->status($latest)->create();

    $response = $this->actingAs($owner)->post(route('company.verification.store'), [
        'registration_number' => 'RDB-654321',
        'documents' => [UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf')],
    ]);

    $canApply ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('registration_number');
    expect($company->verifications()->count())->toBe($canApply ? 2 : 1);
})->with([
    'pending' => [VerificationStatus::PENDING, false],
    'approved' => [VerificationStatus::APPROVED, false],
    'rejected' => [VerificationStatus::REJECTED, true],
]);

it('rejects documents that are not PDF, JPG or PNG or are over 5 MB', function (UploadedFile $document, string $message) {
    Storage::fake('local');
    $owner = User::factory()->create();
    Company::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('company.verification.store'), ['registration_number' => 'RDB-1', 'documents' => [$document]])
        ->assertSessionHasErrors(['documents.0' => $message]);
})->with([
    'wrong type' => [fn () => UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), 'Documents must be PDF, JPG or PNG files.'],
    'too large' => [fn () => UploadedFile::fake()->create('scan.pdf', 5121, 'application/pdf'), 'Each document must be 5 MB or smaller.'],
]);
