<?php

namespace App\Models;

use App\Enums\ContactTopic;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message from the public contact form, read by the marketplace's admins.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property ContactTopic $topic
 * @property string $message
 * @property string|null $ip_address
 * @property CarbonImmutable|null $handled_at
 * @property int|null $handled_by
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $handler
 */
#[Hidden(['ip_address'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'topic', 'message', 'ip_address'];

    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
