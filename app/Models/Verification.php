<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\VerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's application to be verified. Documents live on the private
 * "local" disk and are only served through VerificationDocumentController.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $company_id
 * @property VerificationStatus $status
 * @property string $registration_number
 * @property list<array{path: string, name: string, mime: string, size: int}> $documents
 * @property int|null $reviewer_id
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property-read Company $company
 * @property-read User|null $reviewer
 */
#[Fillable(['registration_number', 'documents', 'notes'])]
class Verification extends Model
{
    /** @use HasFactory<VerificationFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    public const DOCUMENT_DISK = 'local';

    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'documents' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isPending(): bool
    {
        return $this->status === VerificationStatus::PENDING;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'registration_number' => $this->registration_number,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'reviewer' => $this->relationLoaded('reviewer') && $this->reviewer
                ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name]
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'documents' => collect($this->documents)->map(fn (array $document, int $index) => [
                'index' => $index,
                'name' => $document['name'],
                'mime' => $document['mime'],
                'size' => $document['size'],
            ])->values()->all(),
        ];
    }
}
