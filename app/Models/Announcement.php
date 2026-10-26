<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A notification an admin sent to a group of the marketplace's members.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $sent_by
 * @property string $title
 * @property string $body
 * @property string|null $link_url
 * @property AnnouncementAudience $audience
 * @property int $recipients_count
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $sender
 */
#[Fillable(['title', 'body', 'link_url', 'audience'])]
class Announcement extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'recipients_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
